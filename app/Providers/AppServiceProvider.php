<?php

namespace App\Providers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Policies\RolePolicy;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Gate::policy(Role::class, RolePolicy::class);

        View::composer('layouts.app', function (ViewContract $view): void {
            $view->with('unreadNotificationsCount', Auth::user()?->unreadNotifications()->count() ?? 0);
        });

        // Quick links to the user's unclosed projects in the sidebar.
        View::composer('layouts.navigation', function (ViewContract $view): void {
            $user = Auth::user();

            $view->with('sidebarProjects', $user === null ? collect() : Project::query()
                ->visibleTo($user)
                ->where('status', '!=', ProjectStatus::Closed)
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'name', 'code', 'status']));
        });
    }
}
