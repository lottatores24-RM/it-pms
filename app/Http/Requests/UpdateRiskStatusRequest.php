<?php

namespace App\Http\Requests;

use App\Enums\RiskStatus;
use App\Models\Risk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRiskStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. An unknown
     * status only needs view access here; validation then rejects it.
     */
    public function authorize(): bool
    {
        /** @var Risk $risk */
        $risk = $this->route('risk');
        $target = RiskStatus::tryFrom((string) $this->input('status'));

        return $target === null
            ? $this->user()->can('view', $risk)
            : $this->user()->can('changeStatus', [$risk, $target]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(RiskStatus::class)],
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

                /** @var Risk $risk */
                $risk = $this->route('risk');
                $target = $this->enum('status', RiskStatus::class);

                if (! $risk->status->canTransitionTo($target)) {
                    $validator->errors()->add('status', "Status risiko tidak dapat diubah dari {$risk->status->value} ke {$target->value}.");
                }
            },
        ];
    }
}
