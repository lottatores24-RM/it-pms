<form method="POST" action="{{ route('admin.users.toggle-active', $user) }}"
      @if ($user->is_active)
          data-confirm="User {{ $user->name }} tidak akan bisa login sampai diaktifkan kembali."
          data-confirm-title="Nonaktifkan user?"
          data-confirm-button="Nonaktifkan"
      @else
          data-confirm="User {{ $user->name }} akan bisa login kembali."
          data-confirm-title="Aktifkan user?"
          data-confirm-button="Aktifkan"
          data-confirm-variant="primary"
      @endif>
    @csrf
    @method('PATCH')
    <button type="submit" class="{{ $user->is_active ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
        {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
    </button>
</form>
