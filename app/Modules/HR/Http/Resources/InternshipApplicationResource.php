<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InternshipApplicationResource extends JsonResource
{
    /**
     * تحويل الموديل إلى مصفوفة جاهزة ومتكاملة للـ Vue.js 3 شاملة بيانات التتبع والباركود
     * تم تعديل جلب الرابط ليطابق الهوية البرمجية للمشروع ويقيل من القرص العام حصراً
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'academic_institution' => $this->academic_institution,
            'academic_major' => $this->academic_major,
            'required_training_hours' => $this->required_training_hours,
            'internship_start_date' => $this->internship_start_date?->format('Y-m-d'),
            'internship_end_date' => $this->internship_end_date?->format('Y-m-d'),

            // 🛡️ التطابق المعماري: إجبار النظام على القراءة من قرص الـ public لضمان ثبات الرابط على خادم Contabo
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,

            'tracking_code' => $this->tracking_code,
            'status' => $this->status,
            'status_label' => match($this->status) {
                'pending' => 'معلق',
                'approved' => 'مقبول',
                'rejected' => 'مرفوض',
                default => $this->status
            },
            'barcode' => $this->approved_barcode, // الباركود الذي سيقرأه كشك البوابة ويتحول لـ QR Code
            'can_edit' => $this->status === 'pending', // علم منطقي للتحكم في شاشة الفرونت إند وقفل التعديل آلياً
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
