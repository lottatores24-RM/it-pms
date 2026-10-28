<?php

namespace App\Enums;

enum TaskStatus: string
{
    case ToDo = 'To Do';
    case InProgress = 'In Progress';
    case InReview = 'In Review';
    case Done = 'Done';

    /**
     * Allowed workflow transitions (FR-011).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ToDo => [self::InProgress],
            self::InProgress => [self::InReview, self::ToDo],
            self::InReview => [self::Done, self::InProgress],
            self::Done => [self::InProgress],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::ToDo => 'gray',
            self::InProgress => 'blue',
            self::InReview => 'yellow',
            self::Done => 'green',
        };
    }

    /**
     * Chart fill (FR-020). The statuses are ordered stages, so they share one
     * blue ramp from light (To Do) to dark (Done) instead of unrelated hues;
     * validated as an ordinal ramp against the white card surface.
     */
    public function chartColor(): string
    {
        return match ($this) {
            self::ToDo => '#86b6ef',
            self::InProgress => '#5598e7',
            self::InReview => '#2a78d6',
            self::Done => '#184f95',
        };
    }

    /**
     * Button label for moving from this status to the given one.
     */
    public function transitionLabel(self $status): string
    {
        return match ([$this, $status]) {
            [self::ToDo, self::InProgress] => 'Mulai kerjakan',
            [self::InProgress, self::InReview] => 'Ajukan review',
            [self::InProgress, self::ToDo] => 'Kembalikan ke To Do',
            [self::InReview, self::Done] => 'Tandai selesai',
            [self::InReview, self::InProgress] => 'Minta revisi',
            [self::Done, self::InProgress] => 'Buka kembali',
            default => $status->value,
        };
    }

    /**
     * Moving backwards in the workflow (rollback, revision or reopen).
     */
    public function isBackwardTo(self $status): bool
    {
        $order = array_flip(array_map(fn (self $case) => $case->value, self::cases()));

        return $order[$status->value] < $order[$this->value];
    }

    /**
     * Reopening a finished task is reserved for the Project Manager.
     */
    public function isReopen(self $status): bool
    {
        return $this === self::Done && $status === self::InProgress;
    }
}
