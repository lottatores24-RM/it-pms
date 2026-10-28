<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Effort actually spent on a task, the Actual Cost input of EVM (FR-022).
 */
class RecordTaskEffortRequest extends FormRequest
{
    /**
     * The form sits on the task page next to other forms.
     *
     * @var string
     */
    protected $errorBag = 'actualEffort';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('recordEffort', $this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actual_effort_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actual_effort_points' => 'effort aktual',
        ];
    }
}
