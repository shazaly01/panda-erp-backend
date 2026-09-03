<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Requests\Partner\StorePartnerRequest;
use App\Modules\Core\Http\Requests\Partner\UpdatePartnerRequest;
use App\Modules\Core\Http\Resources\PartnerResource;
use App\Modules\Core\Models\Partner;
use App\Modules\Core\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PartnerController extends Controller
{
    public function __construct(
        protected PartnerService $partnerService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Partner::class);

        $partners = $this->partnerService->paginate(
            $request->only(['search', 'role', 'type', 'status', 'tax_treatment']),
            (int) $request->input('per_page', 15)
        );

        return PartnerResource::collection($partners);
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $this->authorize('create', Partner::class);

        $partner = $this->partnerService->create($request->validated());

        return (new PartnerResource($partner))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Partner $partner): PartnerResource
    {
        $this->authorize('view', $partner);

        $partner->loadMissing(['currency', 'receivableAccount', 'payableAccount']);

        return new PartnerResource($partner);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): PartnerResource
    {
        $this->authorize('update', $partner);

        $updatedPartner = $this->partnerService->update($partner, $request->validated());

        return new PartnerResource($updatedPartner);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $this->authorize('delete', $partner);

        $this->partnerService->delete($partner);

        return response()->json([
            'message' => 'تم حذف الشريك بنجاح.'
        ], Response::HTTP_OK);
    }
}