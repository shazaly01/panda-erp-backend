<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Http\Resources\PositionResource;
use App\Modules\HR\Http\Requests\Position\StorePositionRequest;
use App\Modules\HR\Http\Requests\Position\UpdatePositionRequest;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Position::class, 'position');
    }

    public function index(): JsonResponse
    {
        $positions = Position::latest()->paginate(20);

         return PositionResource::collection($positions)->response();
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());

        return response()->json([
            'message' => 'تم إنشاء الوظيفة بنجاح',
            'data' => new PositionResource($position),
        ], 201);
    }

    public function show(Position $position): JsonResponse
    {
        return response()->json([
            'data' => new PositionResource($position)
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $position->update($request->validated());

        return response()->json([
            'message' => 'تم التحديث بنجاح',
            'data' => new PositionResource($position),
        ]);
    }

    public function destroy(Position $position): JsonResponse
    {
        if ($position->employees()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الوظيفة لأنها مسندة لبعض الموظفين.'
            ], 422);
        }

        $position->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
