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

    /**
     * نقطة نهاية مخصصة للقوائم المنسدلة تعيد البيانات التشغيلية والمالية الأساسية
     */
    public function selector(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Partner::class);

        $role = (string) $request->input('role', 'supplier');

        $partners = $this->partnerService->getSelector($role);

        return response()->json([
            'data' => $partners,
        ], Response::HTTP_OK);
    }

    /**
     * البحث السريع عن شريك/عميل برقم الهاتف من شاشة الكاشير والمبيعات
     */
    public function findByPhone(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Partner::class);

        $phone = trim((string) $request->input('phone', ''));
        if ($phone === '') {
            return response()->json([
                'message' => 'يرجى إدخال رقم الهاتف للبحث.',
                'found' => false,
                'data' => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $role = (string) $request->input('role', 'customer');
        $partner = $this->partnerService->findByPhone($phone, $role);

        if (! $partner) {
            return response()->json([
                'found' => false,
                'data' => null,
            ], Response::HTTP_OK);
        }

        return response()->json([
            'found' => true,
            'data' => new PartnerResource($partner),
        ], Response::HTTP_OK);
    }

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

        $partner->loadMissing(['currency', 'priceList', 'receivableAccount', 'payableAccount']);

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