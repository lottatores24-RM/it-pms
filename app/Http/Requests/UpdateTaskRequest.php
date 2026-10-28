<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing task details (PM only). Status is changed through the workflow
 * endpoint so that every transition is validated and logged (FR-011).
 */
class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return StoreTaskRequest::taskRules($this->project());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreTaskRequest::taskMessages($this->project());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'milestone_id' => 'milestone',
            'effort_points' => 'effort points',
            'actual_effort_points' => 'effort aktual',
        ];
    }

    protected function project(): Project
    {
        return $this->route('project');
    }
}
