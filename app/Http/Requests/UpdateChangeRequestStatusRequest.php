<?php

namespace App\Http\Requests;

use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A review step on a change request (FR-016). Every transition needs the
 * Project Manager's justification.
 */
class UpdateChangeRequestStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('changeRequest'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ChangeRequestStatus::class)],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
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

                /** @var ChangeRequest $changeRequest */
                $changeRequest = $this->route('changeRequest');
                $target = $this->enum('status', ChangeRequestStatus::class);

                if (! $changeRequest->status->canTransitionTo($target)) {
                    $validator->errors()->add('status', "Status change request tidak dapat diubah dari {$changeRequest->status->value} ke {$target->value}.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'justification' => 'justifikasi',
        ];
    }
}
