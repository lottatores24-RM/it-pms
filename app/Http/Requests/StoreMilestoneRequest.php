<?php

namespace App\Http\Requests;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Milestone::class, $this->route('project')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::milestoneRules($this->route('project'));
    }

    /**
     * Rules shared by create and update; the target date must fall inside
     * the project period (FR-012).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function milestoneRules(Project $project): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_date' => [
                'required',
                'date',
                'after_or_equal:'.$project->start_date->format('Y-m-d'),
                'before_or_equal:'.$project->end_date->format('Y-m-d'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function milestoneMessages(Project $project): array
    {
        $period = $project->start_date->translatedFormat('d M Y').' – '.$project->end_date->translatedFormat('d M Y');

        return [
            'target_date.after_or_equal' => "Tanggal target harus di dalam periode proyek ({$period}).",
            'target_date.before_or_equal' => "Tanggal target harus di dalam periode proyek ({$period}).",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::milestoneMessages($this->route('project'));
    }
}
