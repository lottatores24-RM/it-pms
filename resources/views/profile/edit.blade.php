<x-app-layout>
    <x-slot name="title">Profil</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Profil</h2>
    </x-slot>

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 lg:grid-cols-3">
        <aside class="lg:col-span-1">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5">
                <div class="flex items-center gap-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-xl font-semibold text-white" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-base font-semibold text-gray-900">{{ $user->name }}</p>
                        <p class="truncate text-sm text-gray-500">{{ $user->email }}</p>
                    </div>
                </div>

                <dl class="mt-6 space-y-3 border-t border-gray-100 pt-5 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Role</dt>
                        <dd><x-badge color="indigo">{{ $user->getRoleNames()->first() ?? '—' }}</x-badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Status</dt>
                        <dd><x-badge :color="$user->is_active ? 'green' : 'red'">{{ $user->is_active ? 'Aktif' : 'Non-aktif' }}</x-badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-gray-500">Terdaftar sejak</dt>
                        <dd class="font-medium text-gray-900">{{ $user->created_at?->translatedFormat('d F Y') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </aside>

        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-900/5 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
