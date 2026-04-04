<?php

namespace App\Http\Requests\Payment;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            'project_id' => 'required|integer|exists:projects,id',
        ];
    }
}
