<?php

namespace App\Models\Concerns;

use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Earned Value Management on task effort points (FR-022).
 *
 * - BAC: effort points of every task that has an estimate.
 * - PV:  effort points of tasks due on or before the status date.
 * - EV:  effort points of Done tasks (0/100 rule).
 * - AC:  actual effort recorded on tasks.
 */
trait CalculatesEarnedValue
{
    /**
     * EVM figures as of the given date (default today).
     *
     * @return array{
     *     as_of: string,
     *     task_count: int,
     *     bac: float, pv: float, ev: float, ac: float,
     *     sv: float, cv: float,
     *     spi: ?float, cpi: ?float, eac: ?float,
     *     percent_complete: int,
     *     gaps: array{without_effort: int, without_due_date: int, done_without_actual: int},
     * }
     */
    public function earnedValue(?CarbonInterface $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $tasks = $this->earnedValueTasks();
        $estimated = $tasks->whereNotNull('effort_points');

        $bac = $this->sumPoints($estimated, 'effort_points');
        $pv = $this->sumPoints($estimated->filter(fn (Task $task) => $task->due_date?->lte($asOf)), 'effort_points');
        $ev = $this->sumPoints($estimated->where('status', TaskStatus::Done), 'effort_points');
        $ac = $this->sumPoints($tasks, 'actual_effort_points');

        $spi = $pv > 0 ? round($ev / $pv, 2) : null;
        $cpi = $ac > 0 ? round($ev / $ac, 2) : null;

        return [
            'as_of' => $asOf->toDateString(),
            'task_count' => $tasks->count(),
            'bac' => $bac,
            'pv' => $pv,
            'ev' => $ev,
            'ac' => $ac,
            'sv' => round($ev - $pv, 2),
            'cv' => round($ev - $ac, 2),
            'spi' => $spi,
            'cpi' => $cpi,
            'eac' => $cpi ? round($bac / $cpi, 2) : null,
            'percent_complete' => $bac > 0 ? (int) round($ev / $bac * 100) : 0,
            'gaps' => [
                'without_effort' => $tasks->whereNull('effort_points')->count(),
                'without_due_date' => $estimated->whereNull('due_date')->count(),
                'done_without_actual' => $tasks->where('status', TaskStatus::Done)->whereNull('actual_effort_points')->count(),
            ],
        ];
    }

    /**
     * Cumulative PV, EV and AC over the project timeline (S-curve). EV and AC
     * accrue on the day a task is completed and stop at the status date;
     * actual effort on unfinished tasks is added at the status date.
     *
     * @return array{labels: list<string>, pv: list<float>, ev: list<?float>, ac: list<?float>}
     */
    public function earnedValueCurve(?CarbonInterface $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $tasks = $this->earnedValueTasks();
        $estimated = $tasks->whereNotNull('effort_points');
        $done = $tasks->where('status', TaskStatus::Done);
        $unfinishedActual = $this->sumPoints($tasks->where('status', '!=', TaskStatus::Done), 'actual_effort_points');

        $start = $this->start_date->copy()->startOfDay();
        $end = $this->end_date->copy()->max($asOf);
        $step = max(1, (int) ceil($start->diffInDays($end) / 24));

        $dates = collect();
        for ($date = $start->copy(); $date->lt($end); $date->addDays($step)) {
            $dates->push($date->copy());
        }
        $dates = $dates->push($end->copy(), $asOf->copy())
            ->filter(fn (CarbonInterface $date) => $date->gte($start))
            ->unique(fn (CarbonInterface $date) => $date->toDateString())
            ->sort()
            ->values();

        $curve = ['labels' => [], 'pv' => [], 'ev' => [], 'ac' => []];

        foreach ($dates as $date) {
            $completedBy = fn (Task $task) => $task->completed_at?->lte($date->copy()->endOfDay());

            $curve['labels'][] = $date->translatedFormat('d M Y');
            $curve['pv'][] = $this->sumPoints($estimated->filter(fn (Task $task) => $task->due_date?->lte($date)), 'effort_points');

            if ($date->gt($asOf)) {
                $curve['ev'][] = null;
                $curve['ac'][] = null;

                continue;
            }

            $curve['ev'][] = $this->sumPoints($estimated->where('status', TaskStatus::Done)->filter($completedBy), 'effort_points');
            $curve['ac'][] = round($this->sumPoints($done->filter($completedBy), 'actual_effort_points')
                + ($date->equalTo($asOf) ? $unfinishedActual : 0), 2);
        }

        return $curve;
    }

    /**
     * @return Collection<int, Task>
     */
    private function earnedValueTasks(): Collection
    {
        return $this->tasks()->get(['id', 'status', 'due_date', 'effort_points', 'actual_effort_points', 'completed_at']);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function sumPoints(Collection $tasks, string $column): float
    {
        return round((float) $tasks->sum(fn (Task $task) => (float) $task->{$column}), 2);
    }
}
