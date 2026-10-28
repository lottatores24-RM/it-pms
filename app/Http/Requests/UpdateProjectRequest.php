<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        $allowedStatuses = array_map(
            fn (ProjectStatus $status) => $status->value,
            [$project->status, ...$project->status->allowedTransitions()],
        );

        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9\-_]*$/', Rule::unique(Project::class)->ignore($project)],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'sponsor' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in($allowedStatuses)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString()]);
        }
    }

    /**
     * New dates must still cover the project's existing tasks and milestones,
     * otherwise FR-009/FR-012 date rules would be silently broken.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                /** @var Project $project */
                $project = $this->route('project');
                $start = Carbon::parse($this->input('start_date'))->startOfDay();
                $end = Carbon::parse($this->input('end_date'))->startOfDay();

                $latestTaskDue = $project->tasks()->max('due_date');
                $latestMilestone = $project->milestones()->max('target_date');
                $earliestMilestone = $project->milestones()->min('target_date');

                $latest = collect([$latestTaskDue, $latestMilestone])->filter()->map(fn ($date) => Carbon::parse($date))->max();

                if ($latest !== null && $end->lt($latest)) {
                    $validator->errors()->add('end_date', 'Tanggal selesai tidak boleh sebelum '.$latest->translatedFormat('d F Y').' (tenggat task/milestone terakhir).');
                }

                if ($earliestMilestone !== null && $start->gt(Carbon::parse($earliestMilestone))) {
                    $validator->errors()->add('start_date', 'Tanggal mulai tidak boleh setelah '.Carbon::parse($earliestMilestone)->translatedFormat('d F Y').' (milestone pertama).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'status.in' => 'Perubahan status ini tidak diizinkan dari status saat ini.',
        ];
    }
}
