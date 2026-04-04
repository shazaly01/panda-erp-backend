<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn() => $this->account->name),
            'account_code' => $this->whenLoaded('account', fn() => $this->account->code),

            'cost_center_id' => $this->cost_center_id,
            'cost_center_name' => $this->whenLoaded('costCenter', fn() => $this->costCenter->name),

            'debit' => (float) $this->debit,
            'credit' => (float) $this->credit,
            'description' => $this->description,
        ];
    }
}
