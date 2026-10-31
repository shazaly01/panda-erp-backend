<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\SequenceService;
use App\Modules\Sales\Http\Requests\Payment\StoreSalesInvoicePaymentRequest;
use App\Modules\Sales\Http\Requests\Payment\UpdateSalesInvoicePaymentRequest;
use App\Modules\Sales\Http\Resources\SalesInvoicePaymentResource;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoicePayment;
use App\Modules\Sales\Services\SalesAccountingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SalesInvoicePaymentController extends Controller
{
    public function __construct(
        protected SequenceService $sequenceService,
        protected SalesAccountingService $salesAccountingService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SalesInvoicePayment::class);

        $query = SalesInvoicePayment::query()
            ->with([
                'invoice.partner',
                'box',
                'bankAccount',
                'shift',
                'currency',
                'creator',
            ]);

        if ($request->filled('sales_invoice_id')) {
            $query->where('sales_invoice_id', (int) $request->input('sales_invoice_id'));
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

        return SalesInvoicePaymentResource::collection($payments);
    }

    public function store(StoreSalesInvoicePaymentRequest $request): JsonResponse
    {
        $this->authorize('create', SalesInvoicePayment::class);

        $validated = $request->validated();

        $payment = DB::transaction(function () use ($validated, $request): SalesInvoicePayment {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($validated['sales_invoice_id']);

            if ($invoice->status === 'cancelled') {
                throw new RuntimeException('لا يمكن تسجيل دفعة لفاتورة مبيعات ملغاة.');
            }

            $amount = round((float) $validated['amount'], 2);
            $remaining = (float) $invoice->remaining_amount;

            if ($amount > $remaining) {
                throw new RuntimeException("مبلغ السداد ({$amount}) يتجاوز المبلغ المتبقي على الفاتورة ({$remaining}).");
            }

            $paymentDate = ! empty($validated['payment_date'])
                ? Carbon::parse($validated['payment_date'])
                : Carbon::now();

            $paymentNumber = $this->sequenceService->generateNumber(
                'acc_receipt',
                $invoice->branch_id,
                null,
                $paymentDate->toDateString()
            );

            /** @var SalesInvoicePayment $payment */
            $payment = $invoice->payments()->create([
                'payment_number' => $paymentNumber,
                'shift_id' => $validated['shift_id'] ?? $invoice->shift_id,
                'payment_method' => $validated['payment_method'],
                'amount' => $amount,
                'currency_id' => $validated['currency_id'] ?? $invoice->currency_id,
                'exchange_rate' => $validated['exchange_rate'] ?? $invoice->exchange_rate,
                'box_id' => $validated['box_id'] ?? null,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'payment_date' => $paymentDate,
                'notes' => $validated['notes'] ?? null,
                'created_by' => (int) $request->user()->id,
            ]);

            $newPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $newRemaining = max(0.0, round((float) $invoice->total_amount - $newPaid, 2));

            $paymentStatus = 'unpaid';
            if ($newPaid >= (float) $invoice->total_amount && (float) $invoice->total_amount > 0) {
                $paymentStatus = 'paid';
            } elseif ($newPaid > 0) {
                $paymentStatus = 'partially_paid';
            }

            $invoice->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'payment_status' => $paymentStatus,
                'updated_by' => (int) $request->user()->id,
            ]);

            if ($invoice->posting_status === 'posted') {
                $paymentAccountId = $payment->box_id
                    ? (int) $payment->box_id
                    : (int) ($payment->bank_account_id ?? 0);

                if ($paymentAccountId > 0) {
                    $this->salesAccountingService->createInvoicePaymentJournalEntry(
                        invoice: $invoice,
                        amount: $amount,
                        paymentAccountId: $paymentAccountId,
                        referenceNumber: $payment->transaction_reference,
                        notes: $payment->notes
                    );
                }
            }

            return $payment->load([
                'invoice.partner',
                'box',
                'bankAccount',
                'shift',
                'currency',
                'creator',
            ]);
        });

        return (new SalesInvoicePaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(SalesInvoicePayment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->loadMissing([
            'invoice.partner',
            'box',
            'bankAccount',
            'shift',
            'currency',
            'creator',
        ]);

        return (new SalesInvoicePaymentResource($payment))->response();
    }

    public function update(UpdateSalesInvoicePaymentRequest $request, SalesInvoicePayment $payment): JsonResponse
    {
        $this->authorize('update', $payment);

        $validated = $request->validated();

        $updatedPayment = DB::transaction(function () use ($payment, $validated, $request): SalesInvoicePayment {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($payment->sales_invoice_id);

            if ($invoice->posting_status === 'posted') {
                throw new RuntimeException('لا يمكن تعديل دفعة لفاتورة مرحلة محاسبياً منعاً لاختلال القيود اليومية.');
            }

            $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : (float) $payment->amount;
            $otherPaymentsTotal = (float) $invoice->payments()->where('id', '!=', $payment->id)->sum('amount');
            $potentialTotalPaid = round($otherPaymentsTotal + $amount, 2);

            if ($potentialTotalPaid > (float) $invoice->total_amount) {
                $maxAllowed = max(0.0, round((float) $invoice->total_amount - $otherPaymentsTotal, 2));
                throw new RuntimeException("المبلغ المحدث يتجاوز المتبقي من الفاتورة. الحد الأقصى المسموح به هو: {$maxAllowed}");
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

            $newPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $newRemaining = max(0.0, round((float) $invoice->total_amount - $newPaid, 2));

            $paymentStatus = 'unpaid';
            if ($newPaid >= (float) $invoice->total_amount && (float) $invoice->total_amount > 0) {
                $paymentStatus = 'paid';
            } elseif ($newPaid > 0) {
                $paymentStatus = 'partially_paid';
            }

            $invoice->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'payment_status' => $paymentStatus,
                'updated_by' => (int) $request->user()->id,
            ]);

            return $payment->load([
                'invoice.partner',
                'box',
                'bankAccount',
                'shift',
                'currency',
                'creator',
            ]);
        });

        return (new SalesInvoicePaymentResource($updatedPayment))->response();
    }

    public function destroy(SalesInvoicePayment $payment, Request $request): JsonResponse
    {
        $this->authorize('delete', $payment);

        DB::transaction(function () use ($payment, $request): void {
            /** @var SalesInvoice $invoice */
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($payment->sales_invoice_id);

            if ($invoice->posting_status === 'posted') {
                throw new RuntimeException('لا يمكن حذف دفعة لفاتورة مرحلة محاسبياً.');
            }

            $payment->delete();

            $newPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $newRemaining = max(0.0, round((float) $invoice->total_amount - $newPaid, 2));

            $paymentStatus = 'unpaid';
            if ($newPaid >= (float) $invoice->total_amount && (float) $invoice->total_amount > 0) {
                $paymentStatus = 'paid';
            } elseif ($newPaid > 0) {
                $paymentStatus = 'partially_paid';
            }

            $invoice->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'payment_status' => $paymentStatus,
                'updated_by' => (int) $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'تم حذف دفعة السداد وتحديث حالة الفاتورة بنجاح.',
        ], Response::HTTP_OK);
    }
}