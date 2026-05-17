<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * تحويل الصلاحية إلى تنسيق JSON مهيكل
     */
    public function toArray(Request $request): array
    {
        // الاسم عادة يكون مثل: 'hr.employees.view' أو 'payment.create'
        $nameParts = explode('.', $this->name);

        return [
            'id'    => $this->id,
            'name'  => $this->name, // الاسم الكامل للبرمجة (hr.employees.view)

            // إضافة هذه الحقول تسهل على المبرمج في الواجهة الأمامية (Vue/React)
            // ليتمكن من عمل Filter أو Grouping بسهولة
            'group'  => $nameParts[0] ?? null,           // مثل: hr
            'action' => $nameParts[1] ?? ($nameParts[0] ?? null), // مثل: view
        ];
    }
}
