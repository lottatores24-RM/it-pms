<?php

namespace App\Http\Requests;

use App\Enums\ProjectRole;
use App\Models\ProjectMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectMemberRequest extends FormRequest
{
    /**
     * Kept apart from the "add member" form errors on the same page.
     *
     * @var string
     */
    protected $errorBag = 'updateMember';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('projectMember'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_in_project' => ['required', Rule::enum(ProjectRole::class)],
        ];
    }

    /**
     * A project must always keep at least one Project Manager.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var ProjectMember $member */
                $member = $this->route('projectMember');

                $isDemotingManager = $member->role_in_project === ProjectRole::ProjectManager
                    && $this->enum('role_in_project', ProjectRole::class) !== ProjectRole::ProjectManager;

                if ($isDemotingManager && $member->project->managerCount() <= 1) {
                    $validator->errors()->add('role_in_project', 'Proyek harus memiliki minimal satu Project Manager. Jadikan anggota lain Project Manager terlebih dahulu.');
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
            'role_in_project' => 'role di proyek',
        ];
    }
}
