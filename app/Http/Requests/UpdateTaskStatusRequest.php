<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Status workflow (FR-011), shared by the task page and the Kanban board.
 */
class UpdateTaskStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
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

                /** @var Task $task */
                $task = $this->route('task');
                $target = $this->enum('status', TaskStatus::class);

                if (! $task->status->canTransitionTo($target)) {
                    $validator->errors()->add('status', "Perpindahan status dari {$task->status->value} ke {$target->value} tidak diizinkan.");

                    return;
                }

                if ($task->status->isReopen($target) && ! $this->user()->can('update', $task)) {
                    $validator->errors()->add('status', 'Hanya Project Manager yang dapat membuka kembali task yang sudah Done.');
                }
            },
        ];
    }
}
