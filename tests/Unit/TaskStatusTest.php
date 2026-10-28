<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    /**
     * Every from → to pair and whether FR-011 allows it.
     *
     * @return array<string, array{TaskStatus, TaskStatus, bool}>
     */
    public static function transitions(): array
    {
        $allowed = [
            'To Do>In Progress',
            'In Progress>In Review',
            'In Review>Done',
            'In Review>In Progress',
            'In Progress>To Do',
            'Done>In Progress',
        ];

        $cases = [];

        foreach (TaskStatus::cases() as $from) {
            foreach (TaskStatus::cases() as $to) {
                $key = "{$from->value}>{$to->value}";
                $cases[$key] = [$from, $to, in_array($key, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_only_the_documented_transitions_are_allowed(TaskStatus $from, TaskStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    public function test_rollback_revision_and_reopen_are_backward_moves(): void
    {
        $this->assertTrue(TaskStatus::InProgress->isBackwardTo(TaskStatus::ToDo));
        $this->assertTrue(TaskStatus::InReview->isBackwardTo(TaskStatus::InProgress));
        $this->assertTrue(TaskStatus::Done->isBackwardTo(TaskStatus::InProgress));
        $this->assertFalse(TaskStatus::InReview->isBackwardTo(TaskStatus::Done));
    }

    public function test_only_done_to_in_progress_counts_as_a_reopen(): void
    {
        $this->assertTrue(TaskStatus::Done->isReopen(TaskStatus::InProgress));
        $this->assertFalse(TaskStatus::InReview->isReopen(TaskStatus::InProgress));
    }
}
