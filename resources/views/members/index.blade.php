<x-app-layout>
    <x-slot name="title">Anggota · {{ $project->name }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Semua proyek</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        @if ($canAdd)
            <form method="POST" action="{{ route('projects.members.store', $project) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <h3 class="font-semibold text-gray-900">Tambah Anggota</h3>

                @if ($candidates->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">Semua user aktif sudah menjadi anggota proyek ini.</p>
                @else
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-[1fr_14rem_auto] sm:items-end">
                        <div>
                            <x-input-label for="user_id">User <span class="text-red-600">*</span></x-input-label>
                            <x-select-input id="user_id" name="user_id" class="mt-1 block w-full" required>
                                <option value="" disabled @selected(! old('user_id'))>Pilih user aktif…</option>
                                @foreach ($candidates as $candidate)
                                    <option value="{{ $candidate->id }}" @selected((int) old('user_id') === $candidate->id)>{{ $candidate->name }} ({{ $candidate->email }})</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label for="role_in_project">Role di proyek <span class="text-red-600">*</span></x-input-label>
                            <x-select-input id="role_in_project" name="role_in_project" class="mt-1 block w-full" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(old('role_in_project', App\Enums\ProjectRole::TeamMember->value) === $role->value)>{{ $role->value }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <x-primary-button class="justify-center">Tambah</x-primary-button>
                    </div>
                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                    <x-input-error :messages="$errors->get('role_in_project')" class="mt-2" />
                @endif
            </form>
        @endif

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Anggota</th>
                            <th scope="col" class="px-4 py-3">Role di proyek</th>
                            <th scope="col" class="hidden px-4 py-3 md:table-cell">Bergabung</th>
                            <th scope="col" class="px-4 py-3 text-center">Task aktif</th>
                            <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($members as $member)
                            @php
                                $isLastManager = $member->role_in_project === App\Enums\ProjectRole::ProjectManager && $managerCount <= 1;
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">
                                        {{ $member->user->name }}
                                        @if ($member->user_id === Auth::id())
                                            <span class="text-xs font-normal text-gray-500">(Anda)</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $member->user->email }}</p>
                                    @unless ($member->user->is_active)
                                        <x-badge color="red" class="mt-1">Non-aktif</x-badge>
                                    @endunless
                                </td>
                                <td class="px-4 py-3">
                                    @can('update', $member)
                                        <form method="POST" action="{{ route('projects.members.update', [$project, $member]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-select-input name="role_in_project" class="py-1 text-sm" onchange="this.form.submit()" aria-label="Role {{ $member->user->name }}">
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->value }}" @selected($member->role_in_project === $role)
                                                            @disabled($isLastManager && $role !== App\Enums\ProjectRole::ProjectManager)>
                                                        {{ $role->value }}
                                                    </option>
                                                @endforeach
                                            </x-select-input>
                                        </form>
                                    @else
                                        <x-badge :color="$member->role_in_project === App\Enums\ProjectRole::ProjectManager ? 'indigo' : 'gray'">{{ $member->role_in_project->value }}</x-badge>
                                    @endcan
                                </td>
                                <td class="hidden px-4 py-3 text-gray-600 md:table-cell">{{ $member->joined_at->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3 text-center font-medium text-gray-900">{{ $activeTaskCounts[$member->user_id] ?? 0 }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('delete', $member)
                                        @if ($isLastManager)
                                            <span class="text-xs text-gray-400" title="Tunjuk Project Manager lain terlebih dahulu">PM terakhir</span>
                                        @else
                                            <form method="POST" action="{{ route('projects.members.destroy', [$project, $member]) }}"
                                                  data-confirm="{{ $member->user->name }} akan dikeluarkan dari proyek. {{ $activeTaskCounts[$member->user_id] ?? 0 }} task aktif miliknya akan menjadi unassigned."
                                                  data-confirm-title="Keluarkan anggota?"
                                                  data-confirm-button="Keluarkan">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800">Keluarkan</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($errors->updateMember->any())
            <div class="rounded-md bg-red-50 p-4 ring-1 ring-inset ring-red-600/20">
                <x-input-error :messages="$errors->updateMember->all()" />
            </div>
        @endif
    </div>
</x-app-layout>
