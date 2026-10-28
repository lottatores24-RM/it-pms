<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_complete_and_consistent(): void
    {
        Storage::fake(Attachment::DISK);

        $this->seed(DemoSeeder::class);

        $this->assertSame(
            ['SIAKAD-2026' => 'In Progress', 'KASIR-BATIK' => 'In Progress', 'WEB-NDS' => 'Closed', 'REKRUT-2027' => 'Planning'],
            Project::orderBy('id')->get()->mapWithKeys(fn (Project $project) => [$project->code => $project->status->value])->all(),
        );

        $this->assertGreaterThan(0, Task::get()->filter->isOverdue()->count());
        $this->assertSame(0, ActivityLog::where('created_at', '>', now())->count());
        $this->assertSame(0, Task::where('status', 'Done')->whereNull('completed_at')->count());
        Storage::disk(Attachment::DISK)->assertExists(Attachment::sole()->path);
        $this->assertValidPdf(Storage::disk(Attachment::DISK)->get(Attachment::sole()->path));
        $this->assertSame(0, ChangeRequest::where('status', '!=', 'Submitted')->whereNull('justification')->count());
        $this->assertSame(4, ChangeRequest::distinct()->count('status'));
        $this->assertSame(100, Project::where('code', 'WEB-NDS')->sole()->closing_summary['tasks']['completion_rate']);

        $evm = Project::where('code', 'SIAKAD-2026')->sole()->earnedValue();
        $this->assertSame(0, array_sum($evm['gaps']));
        $this->assertLessThan(1, $evm['spi']);
        $this->assertLessThan(1, $evm['cpi']);
    }

    public function test_demo_pages_render_for_each_role(): void
    {
        Storage::fake(Attachment::DISK);
        $this->seed(DemoSeeder::class);

        $siakad = Project::where('code', 'SIAKAD-2026')->firstOrFail();

        foreach (['admin', 'riki', 'mirco', 'hendra'] as $key) {
            $user = User::where('email', "{$key}@itpms.test")->firstOrFail();

            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->actingAs($user)->get(route('projects.dashboard', $siakad))->assertOk();
            $this->actingAs($user)->get(route('projects.kanban', $siakad))->assertOk();
            $this->actingAs($user)->get(route('projects.change-requests.index', $siakad))->assertOk();
        }
    }

    /**
     * A PDF reader locates every object through the cross-reference table,
     * so each offset must point exactly at its "N 0 obj" header.
     */
    private function assertValidPdf(string $content): void
    {
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringEndsWith("%%EOF\n", $content);
        $this->assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF\n$/', $content, $match));
        $this->assertSame('xref', substr($content, (int) $match[1], 4));

        preg_match_all('/^(\d{10}) 00000 n $/m', $content, $offsets);
        $this->assertNotEmpty($offsets[1]);
        foreach ($offsets[1] as $index => $offset) {
            $this->assertStringStartsWith(($index + 1).' 0 obj', substr($content, (int) $offset));
        }

        $this->assertSame(1, preg_match('/\/Length (\d+) >>\nstream\n(.*)\nendstream/s', $content, $stream));
        $this->assertSame((int) $stream[1], strlen($stream[2]));
    }

    public function test_running_it_twice_does_not_duplicate_data(): void
    {
        Storage::fake(Attachment::DISK);

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(4, Project::count());
        $this->assertSame(1, Project::where('status', ProjectStatus::Closed)->count());
    }
}
