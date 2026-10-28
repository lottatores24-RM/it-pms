<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing issue details (Project Manager). Status changes go through
 * UpdateIssueStatusRequest so each transition is checked and logged.
 */
class UpdateIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('issue'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreIssueRequest::issueRules($this->route('project'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StoreIssueRequest::issueAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreIssueRequest::issueMessages();
    }
}
