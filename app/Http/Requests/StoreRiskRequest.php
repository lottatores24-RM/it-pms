<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Enums\RiskRating;
use App\Enums\RiskResponse;
use App\Models\Project;
use App\Models\Risk;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Risk::class, $this->route('project')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::riskRules($this->route('project'));
    }

    /**
     * Rules shared by create and update (FR-013). The score and level are
     * derived by the model and never accepted from the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function riskRules(Project $project): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'probability' => ['required', Rule::enum(RiskRating::class)],
            'impact' => ['required', Rule::enum(RiskRating::class)],
            'response_plan' => ['required', Rule::enum(RiskResponse::class)],
            'owner_id' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('project_id', $project->id)
                    ->whereIn('role_in_project', [ProjectRole::ProjectManager->value, ProjectRole::TeamMember->value]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function riskAttributes(): array
    {
        return [
            'category' => 'kategori',
            'probability' => 'probabilitas',
            'impact' => 'dampak',
            'response_plan' => 'rencana respons',
            'owner_id' => 'pemilik risiko',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::riskAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_id.exists' => 'Pemilik risiko harus anggota proyek dengan role Project Manager atau Team Member.',
        ];
    }
}
