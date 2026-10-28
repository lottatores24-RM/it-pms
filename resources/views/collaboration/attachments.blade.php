{{--
    Attachments of a project, task or issue (FR-018).
    Expects: $project, $target (model using HasCollaboration, attachments.user loaded).
--}}
@php $canDownload = Auth::user()->hasProjectPermission($project, 'attachment.download'); @endphp

<div id="lampiran" class="scroll-mt-20 bg-white shadow-sm sm:rounded-lg">
    <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Lampiran ({{ $target->attachments->count() }})</h3>

    <ul class="divide-y divide-gray-100">
        @forelse ($target->attachments as $attachment)
            <li class="flex items-center gap-3 px-6 py-3 text-sm">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-gray-100 font-mono text-[10px] font-semibold uppercase text-gray-600">
                    {{ Str::afterLast($attachment->original_name, '.') }}
                </span>
                <div class="min-w-0 flex-1">
                    @if ($canDownload)
                        <a href="{{ route('projects.attachments.download', [$project, $attachment]) }}" class="block truncate font-medium text-indigo-600 hover:text-indigo-800">{{ $attachment->original_name }}</a>
                    @else
                        <span class="block truncate font-medium text-gray-900">{{ $attachment->original_name }}</span>
                    @endif
                    <p class="text-xs text-gray-500">{{ $attachment->humanSize() }} · {{ $attachment->user->name }} · {{ $attachment->created_at->translatedFormat('d M Y H:i') }}</p>
                </div>
                @can('delete', $attachment)
                    <form method="POST" action="{{ route('projects.attachments.destroy', [$project, $attachment]) }}"
                          data-confirm="Lampiran &quot;{{ $attachment->original_name }}&quot; akan dihapus."
                          data-confirm-title="Hapus lampiran?"
                          data-confirm-button="Hapus">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-red-600 hover:text-red-800">Hapus</button>
                    </form>
                @endcan
            </li>
        @empty
            <li class="px-6 py-6 text-sm text-gray-500">Belum ada lampiran.</li>
        @endforelse
    </ul>

    @can('create', [App\Models\Attachment::class, $target])
        <form method="POST" action="{{ route('projects.attachments.store', $project) }}" enctype="multipart/form-data" class="border-t border-gray-100 p-6">
            @csrf
            <input type="hidden" name="attachable_type" value="{{ $target->collaborationType() }}">
            <input type="hidden" name="attachable_id" value="{{ $target->id }}">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-input-label for="attachment-file" value="File" class="sr-only" />
                <input id="attachment-file" name="file" type="file" required
                       accept=".{{ implode(',.', App\Models\Attachment::ALLOWED_EXTENSIONS) }}"
                       class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                <x-primary-button class="shrink-0 justify-center">Unggah</x-primary-button>
            </div>
            <p class="mt-2 text-xs text-gray-500">PDF, DOC/DOCX, XLS/XLSX, PNG, JPG, atau ZIP. Maksimal 10 MB.</p>
            <x-input-error :messages="$errors->attachment->get('file')" class="mt-2" />
        </form>
    @endcan
</div>
