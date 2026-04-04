<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('payment'));
    }

    public function rules(): array
    {
        return [
            'amount' => 'sometimes|required|numeric|min:0',
            // --- [التصحيح هنا] ---
            'payment_date' => 'sometimes|required|date',
            // --- [نهاية التصحيح] ---
            'notes' => 'nullable|string',
        ];
    }
}
