<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_pages_show_a_friendly_404(): void
    {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Ke Dashboard');
    }

    public function test_forbidden_pages_show_a_friendly_403(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teamMember = User::where('email', 'mirco@itpms.test')->firstOrFail();

        $this->actingAs($teamMember)
            ->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertSee('Akses ditolak');
    }

    public function test_server_errors_hide_the_stack_trace_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/boom', fn () => throw new RuntimeException('Rahasia internal: SQLSTATE password=xyz'));

        $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertSee('Terjadi kesalahan')
            ->assertDontSee('Rahasia internal')
            ->assertDontSee('RuntimeException');
    }
}
