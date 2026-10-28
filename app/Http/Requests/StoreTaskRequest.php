<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Task::class, $this->project()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::taskRules($this->project());
    }

    /**
     * Rules shared by create and update (FR-009).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function taskRules(Project $project): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Only members who can work on tasks (PM / Team Member) can be assignees.
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('project_id', $project->id)
                    ->whereIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value]),
            ],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_date' => [
                'nullable',
                'date',
                'after_or_equal:'.$project->start_date->format('Y-m-d'),
                'before_or_equal:'.$project->end_date->format('Y-m-d'),
            ],
            'milestone_id' => [
                'nullable',
                'integer',
                Rule::exists('milestones', 'id')->where('project_id', $project->id)->whereNull('deleted_at'),
            ],
            'effort_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'actual_effort_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function taskMessages(Project $project): array
    {
        return [
            'assigned_to.exists' => 'Penanggung jawab harus anggota proyek dengan role Project Manager atau Team Member.',
            'due_date.after_or_equal' => 'Tenggat harus di dalam periode proyek ('.$project->start_date->translatedFormat('d M Y').' – '.$project->end_date->translatedFormat('d M Y').').',
            'due_date.before_or_equal' => 'Tenggat tidak boleh melewati tanggal selesai proyek ('.$project->end_date->translatedFormat('d M Y').').',
            'milestone_id.exists' => 'Milestone tidak ditemukan di proyek ini.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::taskMessages($this->project());
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
