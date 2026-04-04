<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // استخدام الـ Policy للتحقق من صلاحية التحديث
        // يفترض أن الرابط هو /projects/{project}
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'project_owner' => 'nullable|string|max:255',
            'contract_value' => 'nullable|numeric|min:0',
            'due_value' => 'required|numeric|min:0',
            'award_date' => 'nullable|date',
            // عادةً لا نسمح بتغيير الشركة المرتبط بها المشروع
            // 'company_id' => 'sometimes|required|integer|exists:companies,id',
        ];
    }
}
