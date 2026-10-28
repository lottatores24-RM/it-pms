<x-guest-layout>
    <x-slot name="heading">Konfirmasi password</x-slot>
    <x-slot name="subheading">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</x-slot>

    <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-5">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="mt-1.5 block w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center py-2.5">
            {{ __('Confirm') }}
        </x-primary-button>
    </form>
</x-guest-layout>
