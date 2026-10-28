<?php

namespace App\Console\Commands;

use App\Enums\ActivityAction;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Milestone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mark open milestones whose target date has passed as Missed (FR-012).
 * Scheduled daily in routes/console.php.
 */
#[Signature('milestones:mark-missed')]
#[Description('Tandai milestone yang melewati tanggal target sebagai Missed')]
class MarkMissedMilestones extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $marked = 0;

        Milestone::query()
            ->overdue()
            ->whereHas('project', fn ($query) => $query->where('status', '!=', ProjectStatus::Closed))
            ->chunkById(100, function ($milestones) use (&$marked): void {
                foreach ($milestones as $milestone) {
                    DB::transaction(function () use ($milestone): void {
                        $oldStatus = $milestone->status;
                        $milestone->update(['status' => MilestoneStatus::Missed]);

                        ActivityLog::record(
                            ActivityAction::StatusChange,
                            $milestone,
                            "Otomatis ditandai Missed: tanggal target {$milestone->target_date->format('Y-m-d')} terlewati",
                            ['status' => $oldStatus->value],
                            ['status' => MilestoneStatus::Missed->value],
                        );
                    });

                    $marked++;
                }
            });

        $this->info("{$marked} milestone ditandai Missed.");

        return self::SUCCESS;
    }
}
