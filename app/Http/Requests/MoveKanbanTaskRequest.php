<?php

namespace App\Http\Requests;

/**
 * Moving a card between Kanban columns (FR-017). Same workflow rules as
 * UpdateTaskStatusRequest (FR-011), authorized by the kanban permissions.
 */
class MoveKanbanTaskRequest extends UpdateTaskStatusRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('moveOnKanban', $this->route('task'));
    }
}
