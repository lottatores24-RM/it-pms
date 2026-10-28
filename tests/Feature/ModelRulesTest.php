<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\RiskLevel;
use App\Enums\RiskRating;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{RiskRating, RiskRating, int, RiskLevel}>
     */
    public static function riskMatrix(): array
    {
        return [
            'low x low' => [RiskRating::Low, RiskRating::Low, 1, RiskLevel::Low],
            'low x medium' => [RiskRating::Low, RiskRating::Medium, 2, RiskLevel::Low],
            'low x high' => [RiskRating::Low, RiskRating::High, 3, RiskLevel::Medium],
            'medium x medium' => [RiskRating::Medium, RiskRating::Medium, 4, RiskLevel::High],
            'medium x high' => [RiskRating::Medium, RiskRating::High, 6, RiskLevel::High],
            'high x high' => [RiskRating::High, RiskRating::High, 9, RiskLevel::Critical],
        ];
    }

    #[DataProvider('riskMatrix')]
    public function test_risk_score_and_level_are_derived_from_probability_and_impact(
        RiskRating $probability,
        RiskRating $impact,
        int $score,
        RiskLevel $level,
    ): void {
        $risk = Risk::factory()->create(['probability' => $probability, 'impact' => $impact]);

        $this->assertSame($score, $risk->fresh()->risk_score);
        $this->assertSame($level, $risk->fresh()->risk_level);
    }

    public function test_risk_score_is_recalculated_when_ratings_change(): void
    {
        $risk = Risk::factory()->create(['probability' => RiskRating::Low, 'impact' => RiskRating::Low]);

        $risk->update(['probability' => RiskRating::High, 'impact' => RiskRating::High]);

        $this->assertSame(9, $risk->fresh()->risk_score);
        $this->assertSame(RiskLevel::Critical, $risk->fresh()->risk_level);
    }

    public function test_task_completed_at_follows_the_done_status(): void
    {
        $task = Task::factory()->status(TaskStatus::InReview)->create();
        $this->assertNull($task->completed_at);

        $task->update(['status' => TaskStatus::Done]);
        $this->assertNotNull($task->fresh()->completed_at);

        $task->update(['status' => TaskStatus::InProgress]);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_activity_log_never_stores_passwords(): void
    {
        $user = User::factory()->create();

        $log = ActivityLog::record(
            ActivityAction::Update,
            $user,
            oldValues: ['name' => 'Lama', 'password' => 'hash-lama'],
            newValues: ['name' => 'Baru', 'password' => 'hash-baru', 'remember_token' => 'token'],
            user: $user,
        );

        $this->assertSame(['name' => 'Lama'], $log->fresh()->old_values);
        $this->assertSame(['name' => 'Baru'], $log->fresh()->new_values);
    }

    public function test_activity_log_entries_cannot_be_changed_or_deleted(): void
    {
        $user = User::factory()->create();
        $log = ActivityLog::record(ActivityAction::Login, $user, user: $user);

        try {
            $log->update(['description' => 'diubah']);
            $this->fail('Updating an activity log must be rejected.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
