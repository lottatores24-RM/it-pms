{{--
    Role-aware sidebar. Each entry is shown only when its route exists and the
    user holds the matching permission, so modules appear as they are built.
--}}
@php
$icons = [
    'dashboard' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    'projects' => 'M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
    'notifications' => 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
    'users' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    'roles' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
    'activity' => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z',
];

$mainLinks = [
    ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'permission' => null],
    ['route' => 'projects.index', 'pattern' => 'projects.*', 'label' => 'Proyek', 'icon' => 'projects', 'permission' => 'project.viewAny'],
    ['route' => 'notifications.index', 'pattern' => 'notifications.*', 'label' => 'Notifikasi', 'icon' => 'notifications', 'permission' => null],
];

$adminLinks = [
    ['route' => 'admin.users.index', 'pattern' => 'admin.users.*', 'label' => 'Manajemen User', 'icon' => 'users', 'permission' => 'user.viewAny'],
    ['route' => 'admin.roles.index', 'pattern' => 'admin.roles.*', 'label' => 'Role & Permission', 'icon' => 'roles', 'permission' => 'role.viewAny'],
    ['route' => 'admin.activity-logs.index', 'pattern' => 'admin.activity-logs.*', 'label' => 'Activity Log', 'icon' => 'activity', 'permission' => 'activity_log.viewAny'],
];

$visible = fn (array $link) => Route::has($link['route'])
    && ($link['permission'] === null || Auth::user()->can($link['permission']));

$mainLinks = array_filter($mainLinks, $visible);
$adminLinks = array_filter($adminLinks, $visible);
@endphp

<!-- Mobile backdrop -->
<div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-gray-900/60 lg:hidden" style="display: none;"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-gray-900 transition-transform duration-200 lg:translate-x-0 print:hidden">
    <div class="flex h-16 shrink-0 items-center justify-between gap-2 px-5">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-white">
            <x-application-logo class="h-8 w-8" />
            <span class="text-lg font-semibold tracking-tight">IT-PMS</span>
        </a>
        <button type="button" @click="sidebarOpen = false" class="text-gray-400 hover:text-white lg:hidden" aria-label="Tutup menu">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex flex-1 flex-col gap-6 overflow-y-auto px-3 py-4">
        <div class="flex flex-col gap-1">
            @foreach ($mainLinks as $link)
                <x-sidebar-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])" :icon="$icons[$link['icon']]">
                    {{ $link['label'] }}
                </x-sidebar-link>
            @endforeach
        </div>

        @if (count($adminLinks) > 0)
            <div class="flex flex-col gap-1">
                <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-gray-500">Administrasi</p>
                @foreach ($adminLinks as $link)
                    <x-sidebar-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])" :icon="$icons[$link['icon']]">
                        {{ $link['label'] }}
                    </x-sidebar-link>
                @endforeach
            </div>
        @endif
    </nav>

    <div class="border-t border-gray-800 px-5 py-4 text-xs text-gray-500">
        PT Nusantara Digital Solutions
    </div>
</aside>
