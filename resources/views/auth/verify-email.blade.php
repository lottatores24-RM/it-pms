<x-guest-layout>
    <x-slot name="heading">Verifikasi email</x-slot>
    <x-slot name="subheading">{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}</x-slot>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-5 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="flex flex-col gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button class="w-full justify-center py-2.5">
                {{ __('Resend Verification Email') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf

            <button type="submit" class="rounded-md text-sm font-medium text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
