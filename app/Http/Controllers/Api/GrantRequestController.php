<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GrantRequest\StoreGrantRequest;
use App\Http\Requests\GrantRequest\UpdateGrantRequest;
use App\Http\Resources\Api\GrantRequestResource;
use App\Models\GrantRequest;
use App\Models\GrantRequestItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class GrantRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GrantRequest::class);

        $grantRequests = GrantRequest::query()
            ->with(['creator', 'items.department'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('request_number', 'like', "%{$search}%")
                        ->orWhere('target_organization', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->filled('department_id'), function ($query) use ($request) {
                $query->whereHas('items', function ($q) use ($request) {
                    $q->where('department_id', $request->input('department_id'));
                });
            })
            ->latest('request_date')
            ->paginate($request->integer('per_page', 15));

        return GrantRequestResource::collection($grantRequests);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGrantRequest $request): JsonResponse
    {
        $this->authorize('create', GrantRequest::class);

        $validated = $request->validated();

        $grantRequest = DB::transaction(function () use ($validated) {
            $requestDate = Carbon::parse($validated['request_date'] ?? now());
            $dateString = $requestDate->format('Ymd');

            if (empty($validated['request_number'])) {
                $dailyCount = GrantRequest::query()
                    ->whereDate('request_date', $requestDate->toDateString())
                    ->lockForUpdate()
                    ->count() + 1;

                $sequence = str_pad((string) $dailyCount, 3, '0', STR_PAD_LEFT);
                $requestNumber = "REQ-{$dateString}-{$sequence}";

                while (GrantRequest::where('request_number', $requestNumber)->exists()) {
                    $dailyCount++;
                    $sequence = str_pad((string) $dailyCount, 3, '0', STR_PAD_LEFT);
                    $requestNumber = "REQ-{$dateString}-{$sequence}";
                }
            } else {
                $requestNumber = $validated['request_number'];
            }

            $grantRequest = GrantRequest::create([
                'request_number' => $requestNumber,
                'target_organization' => $validated['target_organization'],
                'title' => $validated['title'],
                'request_date' => $validated['request_date'],
                'status' => $validated['status'] ?? 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $grantRequest->items()->create([
                    'department_id' => $itemData['department_id'],
                    'item_name' => $itemData['item_name'],
                    'specifications' => $itemData['specifications'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'] ?? null,
                    'estimated_cost' => $itemData['estimated_cost'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            return $grantRequest;
        });

        $grantRequest->load(['creator', 'items.department']);

        return (new GrantRequestResource($grantRequest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(GrantRequest $grantRequest): GrantRequestResource
    {
        $this->authorize('view', $grantRequest);

        $grantRequest->load(['creator', 'items.department']);

        return new GrantRequestResource($grantRequest);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGrantRequest $request, GrantRequest $grantRequest): GrantRequestResource
    {
        $this->authorize('update', $grantRequest);

        $validated = $request->validated();

        DB::transaction(function () use ($grantRequest, $validated) {
            $grantRequest->update([
                'request_number' => $validated['request_number'],
                'target_organization' => $validated['target_organization'],
                'title' => $validated['title'],
                'request_date' => $validated['request_date'],
                'status' => $validated['status'] ?? $grantRequest->status,
                'notes' => $validated['notes'] ?? null,
            ]);

            $incomingItemIds = collect($validated['items'])
                ->pluck('id')
                ->filter()
                ->toArray();

            $grantRequest->items()
                ->whereNotIn('id', $incomingItemIds)
                ->delete();

            foreach ($validated['items'] as $itemData) {
                if (!empty($itemData['id'])) {
                    $grantRequest->items()
                        ->where('id', $itemData['id'])
                        ->update([
                            'department_id' => $itemData['department_id'],
                            'item_name' => $itemData['item_name'],
                            'specifications' => $itemData['specifications'] ?? null,
                            'quantity' => $itemData['quantity'],
                            'unit' => $itemData['unit'] ?? null,
                            'estimated_cost' => $itemData['estimated_cost'] ?? null,
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                } else {
                    $grantRequest->items()->create([
                        'department_id' => $itemData['department_id'],
                        'item_name' => $itemData['item_name'],
                        'specifications' => $itemData['specifications'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'] ?? null,
                        'estimated_cost' => $itemData['estimated_cost'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }
            }
        });

        $grantRequest->load(['creator', 'items.department']);

        return new GrantRequestResource($grantRequest);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GrantRequest $grantRequest): JsonResponse
    {
        $this->authorize('delete', $grantRequest);

        DB::transaction(function () use ($grantRequest) {
            $grantRequest->items()->delete();
            $grantRequest->delete();
        });

        return response()->json([
            'message' => 'تم حذف الطلب بنجاح',
        ], Response::HTTP_OK);
    }

    /**
     * Get print-ready data for the specified resource.
     */
    public function print(GrantRequest $grantRequest): GrantRequestResource
    {
        $this->authorize('print', $grantRequest);

        $grantRequest->load(['creator', 'items.department']);

        return new GrantRequestResource($grantRequest);
    }
}