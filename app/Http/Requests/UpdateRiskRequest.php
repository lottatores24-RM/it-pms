<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing risk details. The status is changed through its own action so each
 * transition is checked and logged (FR-014).
 */
class UpdateRiskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('risk'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreRiskRequest::riskRules($this->route('project'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StoreRiskRequest::riskAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_id.exists' => 'Pemilik risiko harus anggota proyek dengan role Project Manager atau Team Member.',
        ];
    }
}
