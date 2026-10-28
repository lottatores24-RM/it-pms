<x-guest-layout>
    <x-slot name="heading">Lupa password</x-slot>
    <x-slot name="subheading">Masukkan email akun Anda, kami akan mengirimkan link untuk membuat password baru.</x-slot>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center py-2.5">
            {{ __('Email Password Reset Link') }}
        </x-primary-button>

        <p class="text-center text-sm text-gray-600">
            <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">&larr; Kembali ke halaman masuk</a>
        </p>
    </form>
</x-guest-layout>
