{{--
    Shared create/edit form fields.
    Expects: $roles, optional $user (edit mode), $canAssignRole (bool).
--}}
@php
$editing = isset($user);
$currentRole = $editing ? $user->roles->first()?->name : null;
@endphp

<div class="space-y-5">
    <div>
        <x-input-label for="name">Nama <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name ?? '')" required maxlength="150" autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email">Email <span class="text-red-600">*</span></x-input-label>
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email ?? '')" required autocomplete="off" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="role">Role global <span class="text-red-600">*</span></x-input-label>
        <x-select-input id="role" name="role" class="mt-1 block w-full" :disabled="! $canAssignRole" required>
            <option value="" disabled @selected(! old('role', $currentRole))>Pilih role…</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>{{ $role }}</option>
            @endforeach
        </x-select-input>
        @unless ($canAssignRole)
            <p class="mt-1 text-xs text-gray-500">Anda tidak dapat mengubah role akun Anda sendiri.</p>
        @endunless
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <x-input-label for="password">
                Password @unless ($editing)<span class="text-red-600">*</span>@endunless
            </x-input-label>
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :required="! $editing" autocomplete="new-password" />
            <p class="mt-1 text-xs text-gray-500">
                Minimal 8 karakter, mengandung huruf dan angka.@if ($editing) Kosongkan jika tidak diubah.@endif
            </p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation">
                Konfirmasi password @unless ($editing)<span class="text-red-600">*</span>@endunless
            </x-input-label>
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" :required="! $editing" autocomplete="new-password" />
        </div>
    </div>
</div>
