<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDashboardController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RiskController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::get('close', [ProjectController::class, 'closeForm'])->name('close-form');
        Route::post('close', [ProjectController::class, 'close'])->name('close');
        Route::patch('reopen', [ProjectController::class, 'reopen'])->name('reopen');
        Route::get('closing-report', [ProjectController::class, 'closingReport'])->name('closing-report');

        Route::resource('members', ProjectMemberController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['members' => 'projectMember'])
            ->scoped();

        Route::get('activity', [ActivityLogController::class, 'project'])->name('activity');
        Route::get('activity/export', [ActivityLogController::class, 'projectExport'])->name('activity.export');

        Route::post('comments', [CommentController::class, 'store'])->name('comments.store');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
        Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('dashboard', [ProjectDashboardController::class, 'index'])->name('dashboard');
        Route::get('kanban', [KanbanController::class, 'index'])->name('kanban');
        Route::patch('kanban/tasks/{task}', [KanbanController::class, 'move'])->name('kanban.move')->scopeBindings();

        Route::resource('change-requests', ChangeRequestController::class)
            ->parameters(['change-requests' => 'changeRequest'])
            ->scoped();
        Route::patch('change-requests/{changeRequest}/status', [ChangeRequestController::class, 'updateStatus'])->name('change-requests.updateStatus')->scopeBindings();
        Route::resource('milestones', MilestoneController::class)->scoped();
        Route::resource('issues', IssueController::class)->scoped();
        Route::patch('issues/{issue}/status', [IssueController::class, 'updateStatus'])->name('issues.updateStatus')->scopeBindings();
        Route::resource('risks', RiskController::class)->scoped();
        Route::patch('risks/{risk}/status', [RiskController::class, 'updateStatus'])->name('risks.updateStatus')->scopeBindings();
        Route::resource('tasks', TaskController::class)->scoped();
        Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.updateStatus')->scopeBindings();
        Route::patch('tasks/{task}/actual-effort', [TaskController::class, 'recordEffort'])->name('tasks.recordEffort')->scopeBindings();
    });

    Route::middleware('role:Administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('destroy');
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::resource('roles', RoleController::class)->only(['index', 'edit', 'update']);
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
    });
});

require __DIR__.'/auth.php';
