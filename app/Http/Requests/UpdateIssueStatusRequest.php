<?php

namespace App\Http\Requests;

use App\Enums\IssueStatus;
use App\Models\Issue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIssueStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. An unknown
     * status only needs view access here; validation then rejects it.
     */
    public function authorize(): bool
    {
        /** @var Issue $issue */
        $issue = $this->route('issue');
        $target = IssueStatus::tryFrom((string) $this->input('status'));

        return $target === null
            ? $this->user()->can('view', $issue)
            : $this->user()->can('updateStatus', [$issue, $target]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(IssueStatus::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var Issue $issue */
                $issue = $this->route('issue');
                $target = $this->enum('status', IssueStatus::class);

                if (! $issue->status->canTransitionTo($target)) {
                    $validator->errors()->add('status', "Status issue tidak dapat diubah dari {$issue->status->value} ke {$target->value}.");
                }
            },
        ];
    }
}
