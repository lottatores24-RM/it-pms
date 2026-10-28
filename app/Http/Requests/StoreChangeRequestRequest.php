<?php

namespace App\Http\Requests;

use App\Enums\ChangeRequestPriority;
use App\Models\ChangeRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [ChangeRequest::class, $this->route('project')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::changeRequestRules();
    }

    /**
     * Rules shared by create and update. Status is never mass-assigned; it
     * only changes through the review workflow.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function changeRequestRules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:5000'],
            'impact_scope' => ['nullable', 'string', 'max:5000'],
            'impact_schedule' => ['nullable', 'string', 'max:5000'],
            'impact_resource' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(ChangeRequestPriority::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function changeRequestAttributes(): array
    {
        return [
            'reason' => 'alasan perubahan',
            'impact_scope' => 'dampak ruang lingkup',
            'impact_schedule' => 'dampak jadwal',
            'impact_resource' => 'dampak sumber daya',
            'priority' => 'prioritas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::changeRequestAttributes();
    }
}
