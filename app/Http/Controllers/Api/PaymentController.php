<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct()
    {
        // تطبيق الصلاحيات تلقائيًا على كل الدوال
        $this->authorizeResource(Payment::class, 'payment');
    }

    public function index(): AnonymousResourceCollection
    {
        // يمكن فلترة الدفعات حسب المشروع إذا تم إرسال project_id في الطلب
        $query = Payment::with('project');

        if ($projectId = request('project_id')) {
            $query->where('project_id', $projectId);
        }

        $payments = $query->latest()->paginate(15);

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = Payment::create($request->validated());
        return response()->json([
            'message' => 'Payment created successfully.',
            'data' => PaymentResource::make($payment),
        ], Response::HTTP_CREATED);
    }

    public function show(Payment $payment): PaymentResource
    {
        // تحميل علاقة المشروع
        $payment->load('project');
        return PaymentResource::make($payment);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment->update($request->validated());
        return response()->json([
            'message' => 'Payment updated successfully.',
            'data' => PaymentResource::make($payment->fresh()->load('project')),
        ]);
    }

    public function destroy(Payment $payment): Response
    {
        $payment->delete();
        return response()->noContent();
    }
}
