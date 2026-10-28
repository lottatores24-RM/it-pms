<?php

namespace App\Http\Requests;

use App\Enums\MilestoneStatus;
use App\Models\Milestone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('milestone'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...StoreMilestoneRequest::milestoneRules($this->route('project')),
            'status' => ['required', Rule::enum(MilestoneStatus::class)],
            'confirm_reopen' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('status')) {
                    return;
                }

                /** @var Milestone $milestone */
                $milestone = $this->route('milestone');
                $target = $this->enum('status', MilestoneStatus::class);

                if (! $milestone->status->canTransitionTo($target)) {
                    $validator->errors()->add('status', "Status tidak dapat diubah dari {$milestone->status->value} ke {$target->value}.");

                    return;
                }

                if ($milestone->status->requiresConfirmationTo($target) && ! $this->boolean('confirm_reopen')) {
                    $validator->errors()->add('confirm_reopen', 'Centang konfirmasi untuk mengubah milestone yang sudah Completed.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreMilestoneRequest::milestoneMessages($this->route('project'));
    }
}
