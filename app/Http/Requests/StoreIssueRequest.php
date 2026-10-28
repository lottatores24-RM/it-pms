<?php

namespace App\Http\Requests;

use App\Enums\IssueSeverity;
use App\Enums\ProjectRole;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Issue::class, $this->route('project')]);
    }

    /**
     * Team Members may report issues but not choose the assignee (FR-015).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');
        $rules = self::issueRules($project);

        if (! $this->user()->can('assign', [Issue::class, $project])) {
            $rules['assigned_to'] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * Rules shared by create and update. Related task and risk must belong
     * to the same project.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function issueRules(Project $project): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::enum(IssueSeverity::class)],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('project_id', $project->id)
                    ->whereIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value]),
            ],
            'related_task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'related_risk_id' => ['nullable', 'integer', Rule::exists('risks', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function issueAttributes(): array
    {
        return [
            'severity' => 'severity',
            'assigned_to' => 'penanggung jawab',
            'related_task_id' => 'task terkait',
            'related_risk_id' => 'risiko terkait',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function issueMessages(): array
    {
        return [
            'assigned_to.exists' => 'Penanggung jawab harus anggota proyek dengan role Project Manager atau Team Member.',
            'assigned_to.prohibited' => 'Hanya Project Manager yang dapat menentukan penanggung jawab issue.',
            'related_task_id.exists' => 'Task terkait tidak ditemukan di proyek ini.',
            'related_risk_id.exists' => 'Risiko terkait tidak ditemukan di proyek ini.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::issueAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::issueMessages();
    }
}
