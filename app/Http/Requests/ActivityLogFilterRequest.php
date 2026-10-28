<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-string filters for the activity log (FR-019). Authorization depends on
 * the context (global vs. project) and is done in the controller.
 */
class ActivityLogFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', Rule::enum(ActivityAction::class)],
            'user_id' => ['nullable', 'integer'],
            'entity_type' => ['nullable', 'string', 'max:100', 'alpha'],
            'project_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), ['after_or_equal:from'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => 'aksi',
            'user_id' => 'user',
            'entity_type' => 'entitas',
            'project_id' => 'proyek',
            'from' => 'tanggal awal',
            'to' => 'tanggal akhir',
        ];
    }

    /**
     * Validated filters with empty values removed.
     *
     * @return array{action?: string, user_id?: int, entity_type?: string, project_id?: int, from?: string, to?: string}
     */
    public function filters(): array
    {
        $filters = array_filter($this->validated(), fn ($value) => $value !== null && $value !== '');

        foreach (['user_id', 'project_id'] as $key) {
            if (isset($filters[$key])) {
                $filters[$key] = (int) $filters[$key];
            }
        }

        return $filters;
    }
}
