<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkingScheduleLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'shift_id' => $this->shift_id,
            // نُرجع تفاصيل الوردية إذا كانت موجودة (وإذا تم تحميل العلاقة)
            'shift' => $this->whenLoaded('shift', function () {
                return [
                    'id' => $this->shift->id,
                    'name' => $this->shift->name,
                    'start_time' => $this->shift->start_time,
                    'end_time' => $this->shift->end_time,
                ];
            }),
            'is_off_day' => is_null($this->shift_id), // حقل مساعد للواجهة الأمامية
        ];
    }
}
