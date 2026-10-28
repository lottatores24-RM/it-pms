<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing change request details (Project Manager). Status changes go
 * through UpdateChangeRequestStatusRequest so each one carries a justification.
 */
class UpdateChangeRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('changeRequest'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreChangeRequestRequest::changeRequestRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StoreChangeRequestRequest::changeRequestAttributes();
    }
}
