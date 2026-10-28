<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9\-_]*$/', Rule::unique(Project::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'sponsor' => ['nullable', 'string', 'max:150'],
            // An Administrator appoints the Project Manager; a Project Manager
            // creating a project becomes its manager (FR-006).
            'manager_id' => $this->user()->isAdmin()
                ? ['required', 'integer', Rule::in(User::projectManagerCandidates()->pluck('id')->all())]
                : ['prohibited'],
        ];
    }

    /**
     * Codes are stored upper-case so "prj-01" and "PRJ-01" cannot coexist.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => str($this->input('code'))->trim()->upper()->toString()]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode hanya boleh berisi huruf, angka, tanda hubung (-), dan garis bawah (_).',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'manager_id.required' => 'Pilih Project Manager untuk proyek ini.',
            'manager_id.in' => 'Project Manager harus user aktif dengan role Project Manager.',
            'manager_id.prohibited' => 'Hanya Administrator yang dapat menunjuk Project Manager lain.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'manager_id' => 'Project Manager',
        ];
    }
}
