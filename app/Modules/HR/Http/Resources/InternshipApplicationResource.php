<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InternshipApplicationResource extends JsonResource
{
    /**
     * تحويل الموديل إلى مصفوفة جاهزة ومتكاملة للـ Vue.js 3 شاملة بيانات التتبع والباركود والتوظيف والمعاينة
     */
    public function toArray(Request $request): array
    {
        $applicationType = $this->application_type ?? 'internship';
        $source = $this->source ?? 'online';

        return [
            'id' => $this->id,
            'application_type' => $applicationType,
            'application_type_label' => match ($applicationType) {
                'internship' => 'تدريب',
                'employment' => 'توظيف',
                default => $applicationType,
            },
            'source' => $source,
            'source_label' => match ($source) {
                'online' => 'تقديم إلكتروني',
                'manual' => 'إدخال يدوي',
                default => $source,
            },
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'academic_institution' => $this->academic_institution,
            'academic_major' => $this->academic_major,
            'required_training_hours' => $this->required_training_hours,
            'position_id' => $this->position_id,
            'position' => $this->whenLoaded('position'),
            'qualification' => $this->qualification,
            'experience_years' => $this->experience_years,
            'internship_start_date' => $this->internship_start_date?->format('Y-m-d'),
            'internship_end_date' => $this->internship_end_date?->format('Y-m-d'),

            // 🛡️ التطابق المعماري: القراءة من قرص الـ public لضمان ثبات الروابط
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'cv_url' => $this->cv_path ? Storage::disk('public')->url($this->cv_path) : null,

            'tracking_code' => $this->tracking_code,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'pending' => 'معلق',
                'interview' => 'معاينة',
                'approved' => 'مقبول',
                'rejected' => 'مرفوض',
                default => $this->status,
            },
            'barcode' => $this->approved_barcode, // الباركود الذي سيقرأه كشك البوابة ويتحول لـ QR Code
            'interview_date' => $this->interview_date?->format('Y-m-d H:i'),
            'interview_notes' => $this->interview_notes,
            'can_edit' => $this->status === 'pending', // علم منطقي للتحكم في شاشة الفرونت إند وقفل التعديل آلياً
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}