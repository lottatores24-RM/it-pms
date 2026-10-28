<x-app-layout>
    <x-slot name="title">Profil</x-slot>

    <x-slot name="header">
        <h2 class="truncate text-xl font-semibold leading-tight text-gray-800">Profil</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
