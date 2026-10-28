{{--
    Comment thread for a project, task or issue (FR-018).
    Expects: $project, $target (model using HasCollaboration, comments.user loaded).
--}}
@can('viewAny', [App\Models\Comment::class, $target])
    <div id="komentar" class="scroll-mt-20 bg-white shadow-sm sm:rounded-lg">
        <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Komentar ({{ $target->comments->count() }})</h3>

        <ul class="divide-y divide-gray-100">
            @forelse ($target->comments as $comment)
                <li class="flex gap-3 px-6 py-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                        {{ mb_strtoupper(mb_substr($comment->user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-sm">
                                <span class="font-medium text-gray-900">{{ $comment->user->name }}</span>
                                <span class="text-xs text-gray-500" title="{{ $comment->created_at->translatedFormat('d F Y H:i') }}">· {{ $comment->created_at->diffForHumans() }}</span>
                            </p>
                            @can('delete', $comment)
                                <form method="POST" action="{{ route('projects.comments.destroy', [$project, $comment]) }}"
                                      data-confirm="Komentar ini akan dihapus."
                                      data-confirm-title="Hapus komentar?"
                                      data-confirm-button="Hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            @endcan
                        </div>
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-gray-700">{{ $comment->body }}</p>
                    </div>
                </li>
            @empty
                <li class="px-6 py-6 text-sm text-gray-500">Belum ada komentar.</li>
            @endforelse
        </ul>

        @can('create', [App\Models\Comment::class, $target])
            <form method="POST" action="{{ route('projects.comments.store', $project) }}" class="border-t border-gray-100 p-6"
                  x-data="{ length: {{ mb_strlen(old('body', '')) }} }">
                @csrf
                <input type="hidden" name="commentable_type" value="{{ $target->collaborationType() }}">
                <input type="hidden" name="commentable_id" value="{{ $target->id }}">

                <x-input-label for="comment-body" value="Tulis komentar" class="sr-only" />
                <textarea id="comment-body" name="body" rows="3" required maxlength="2000"
                          x-on:input="length = $event.target.value.length"
                          placeholder="Tulis komentar…"
                          class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                <x-input-error :messages="$errors->comment->get('body')" class="mt-2" />

                <div class="mt-3 flex items-center justify-between">
                    <span class="text-xs text-gray-500"><span x-text="length">0</span>/2000</span>
                    <x-primary-button>Kirim</x-primary-button>
                </div>
            </form>
        @endcan
    </div>
@endcan
