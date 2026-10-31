<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\SequenceService;
use App\Modules\Sales\Http\Requests\Payment\StoreSalesReturnPaymentRequest;
use App\Modules\Sales\Http\Requests\Payment\UpdateSalesReturnPaymentRequest;
use App\Modules\Sales\Http\Resources\SalesReturnPaymentResource;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnPayment;
use App\Modules\Sales\Services\SalesAccountingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SalesReturnPaymentController extends Controller
{
    public function __construct(
        protected SequenceService $sequenceService,
        protected SalesAccountingService $salesAccountingService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesReturnPayment::class);

        $query = SalesReturnPayment::query()
            ->with([
                'salesReturn.partner',
                'box',
                'bankAccount',
                'shift',
                'currency',
                'creator',
            ]);

        if ($request->filled('sales_return_id')) {
            $query->where('sales_return_id', (int) $request->input('sales_return_id'));
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', (int) $request->input('shift_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', (string) $request->input('payment_method'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->input('date_to'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $payments = $query->orderByDesc('id')->paginate($perPage);

        return SalesReturnPaymentResource::collection($payments);
    }

    public function store(StoreSalesReturnPaymentRequest $request): JsonResponse
    {
        $this->authorize('create', SalesReturnPayment::class);

        $validated = $request->validated();

        $payment = DB::transaction(function () use ($validated, $request): SalesReturnPayment {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::lockForUpdate()->findOrFail($validated['sales_return_id']);

            if ($salesReturn->status === 'cancelled') {
                throw new RuntimeException('لا يمكن تسجيل استرداد مالي لمرتجع مبيعات ملغى.');
            }

            $amount = round((float) $validated['amount'], 2);
            $remaining = (float) $salesReturn->remaining_amount;

            if ($amount > $remaining) {
                throw new RuntimeException("مبلغ الاسترداد ({$amount}) يتجاوز المبلغ المتبقي للمرتجع ({$remaining}).");
            }

            $paymentDate = ! empty($validated['payment_date'])
                ? Carbon::parse($validated['payment_date'])
                : Carbon::now();

            $paymentNumber = $this->sequenceService->generateNumber(
                'acc_payment',
                $salesReturn->branch_id,
                null,
                $paymentDate->toDateString()
            );

            /** @var SalesReturnPayment $payment */
            $payment = $salesReturn->refundPayments()->create([
                'payment_number' => $paymentNumber,
                'shift_id' => $validated['shift_id'] ?? $salesReturn->shift_id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'currency_id' => $validated['currency_id'] ?? $salesReturn->currency_id,
                'exchange_rate' => $validated['exchange_rate'] ?? $salesReturn->exchange_rate,
                'box_id' => $validated['box_id'] ?? null,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_date' => $paymentDate,
                'notes' => $validated['notes'] ?? null,
                'created_by' => (int) $request->user()->id,
            ]);

            $newRefunded = round((float) $salesReturn->refundPayments()->sum('amount'), 2);
            $newRemaining = max(0.0, round((float) $salesReturn->total_amount - $newRefunded, 2));

            $refundStatus = 'unpaid';
            if ($newRefunded >= (float) $salesReturn->total_amount && (float) $salesReturn->total_amount > 0) {
                $refundStatus = 'refunded';
            } elseif ($newRefunded > 0) {
                $refundStatus = 'partially_refunded';
            }

            $salesReturn->update([
                'refunded_amount' => $newRefunded,
                'remaining_amount' => $newRemaining,
                'refund_status' => $refundStatus,
                'updated_by' => (int) $request->user()->id,
            ]);

            if ($salesReturn->posting_status === 'posted') {
                $paymentAccountId = $payment->box_id
                    ? (int) $payment->box_id
                    : (int) ($payment->bank_account_id ?? 0);

                if ($paymentAccountId > 0) {
                    $this->salesAccountingService->createReturnPaymentJournalEntry(
                        return: $salesReturn,
                        amount: $amount,
                        paymentAccountId: $paymentAccountId,
                        referenceNumber: $payment->transaction_reference,
                        notes: $payment->notes
                    );
                }
            }

            return $payment->load([
                'salesReturn.partner',
                'box',
                'bankAccount',
                'shift',
                'currency',
                'creator',
            ]);
        });

        return (new SalesReturnPaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(SalesReturnPayment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->loadMissing([
            'salesReturn.partner',
            'box',
            'bankAccount',
            'shift',
            'currency',
            'creator',
        ]);

        return (new SalesReturnPaymentResource($payment))->response();
    }

    public function update(UpdateSalesReturnPaymentRequest $request, SalesReturnPayment $payment): JsonResponse
    {
        $this->authorize('update', $payment);

        $validated = $request->validated();

        $updatedPayment = DB::transaction(function () use ($payment, $validated, $request): SalesReturnPayment {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::lockForUpdate()->findOrFail($payment->sales_return_id);

            if ($salesReturn->posting_status === 'posted') {
                throw new RuntimeException('لا يمكن تعديل سند صرف لمرتجع مرحل محاسبياً منعاً لاختلال القيود اليومية.');
            }

            $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : (float) $payment->amount;
            $otherPaymentsTotal = (float) $salesReturn->refundPayments()->where('id', '!=', $payment->id)->sum('amount');
            $potentialTotalRefunded = round($otherPaymentsTotal + $amount, 2);

            if ($potentialTotalRefunded > (float) $salesReturn->total_amount) {
                $maxAllowed = max(0.0, round((float) $salesReturn->total_amount - $otherPaymentsTotal, 2));
                throw new RuntimeException("المبلغ المحدث يتجاوز إجمالي المرتجع. الحد الأقصى المسموح به هو: {$maxAllowed}");
            }

            $payment->update([
                'shift_id' => array_key_exists('shift_id', $validated) ? $validated['shift_id'] : $payment->shift_id,
                'payment_method' => $validated['payment_method'] ?? $payment->payment_method,
                'amount' => $amount,
                'currency_id' => array_key_exists('currency_id', $validated) ? $validated['currency_id'] : $payment->currency_id,
                'exchange_rate' => $validated['exchange_rate'] ?? $payment->exchange_rate,
                'box_id' => array_key_exists('box_id', $validated) ? $validated['box_id'] : $payment->box_id,
                'bank_account_id' => array_key_exists('bank_account_id', $validated) ? $validated['bank_account_id'] : $payment->bank_account_id,
                'transaction_reference' => array_key_exists('transaction_reference', $validated) ? $validated['transaction_reference'] : $payment->transaction_reference,
                'payment_date' => ! empty($validated['payment_date']) ? Carbon::parse($validated['payment_date']) : $payment->payment_date,
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $payment->notes,
            ]);

            $newRefunded = round((float) $salesReturn->refundPayments()->sum('amount'), 2);
            $newRemaining = max(0.0, round((float) $salesReturn->total_amount - $newRefunded, 2));

            $refundStatus = 'unpaid';
            if ($newRefunded >= (float) $salesReturn->total_amount && (float) $salesReturn->total_amount > 0) {
                $refundStatus = 'refunded';
            } elseif ($newRefunded > 0) {
                $refundStatus = 'partially_refunded';
            }

            $salesReturn->update([
                'refunded_amount' => $newRefunded,
                'remaining_amount' => $newRemaining,
                'refund_status' => $refundStatus,
                'updated_by' => (int) $request->user()->id,
            ]);

            return $payment->load([
                'salesReturn.partner',
                'box',
                'bankAccount',
                'shift',
                'currency',
                'creator',
            ]);
        });

        return (new SalesReturnPaymentResource($updatedPayment))->response();
    }

    public function destroy(SalesReturnPayment $payment, Request $request): JsonResponse
    {
        $this->authorize('delete', $payment);

        DB::transaction(function () use ($payment, $request): void {
            /** @var SalesReturn $salesReturn */
            $salesReturn = SalesReturn::lockForUpdate()->findOrFail($payment->sales_return_id);

            if ($salesReturn->posting_status === 'posted') {
                throw new RuntimeException('لا يمكن حذف سند استرداد لمرتجع مرحل محاسبياً.');
            }

            $payment->delete();

            $newRefunded = round((float) $salesReturn->refundPayments()->sum('amount'), 2);
            $newRemaining = max(0.0, round((float) $salesReturn->total_amount - $newRefunded, 2));

            $refundStatus = 'unpaid';
            if ($newRefunded >= (float) $salesReturn->total_amount && (float) $salesReturn->total_amount > 0) {
                $refundStatus = 'refunded';
            } elseif ($newRefunded > 0) {
                $refundStatus = 'partially_refunded';
            }

            $salesReturn->update([
                'refunded_amount' => $newRefunded,
                'remaining_amount' => $newRemaining,
                'refund_status' => $refundStatus,
                'updated_by' => (int) $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'تم حذف سند الاسترداد وتحديث حالة المرتجع بنجاح.',
        ], Response::HTTP_OK);
    }
}