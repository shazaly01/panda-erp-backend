<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests\Requisitions;

use Illuminate\Foundation\Http\FormRequest;

class RejectPurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition && ($this->user()?->can('reject', $requisition) ?? false);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rejection_reason' => 'سبب الرفض',
        ];
    }
}