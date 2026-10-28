<x-app-layout>
    <x-slot name="title">{{ $issue->title }}</x-slot>

    <x-slot name="header">
        <a href="{{ route('projects.issues.index', $project) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Issue {{ $project->name }}</a>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        @include('projects.partials.header')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :color="$issue->status->color()">{{ $issue->status->value }}</x-badge>
                                <x-badge :color="$issue->severity->color()">{{ $issue->severity->value }}</x-badge>
                            </div>
                            <h2 class="mt-2 text-xl font-semibold text-gray-900">{{ $issue->title }}</h2>
                        </div>

                        <div class="flex shrink-0 items-center gap-3 text-sm">
                            @can('update', $issue)
                                <a href="{{ route('projects.issues.edit', [$project, $issue]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50">Edit</a>
                            @endcan
                            @can('delete', $issue)
                                <form method="POST" action="{{ route('projects.issues.destroy', [$project, $issue]) }}"
                                      data-confirm="Issue &quot;{{ $issue->title }}&quot; akan dihapus."
                                      data-confirm-title="Hapus issue?"
                                      data-confirm-button="Hapus">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Hapus</button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    <p class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ $issue->description ?: 'Tidak ada deskripsi.' }}</p>

                    @if ($issue->status === App\Enums\IssueStatus::Closed)
                        <p class="mt-4 text-sm text-gray-500">Issue sudah ditutup dan bersifat read-only.</p>
                    @elseif ($issue->status === App\Enums\IssueStatus::Resolved && count($transitions) === 0)
                        <p class="mt-4 text-sm text-gray-500">Issue sudah resolved dan menunggu ditutup oleh Project Manager.</p>
                    @endif

                    @if (count($transitions) > 0)
                        <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-5">
                            @foreach ($transitions as $target)
                                <form method="POST" action="{{ route('projects.issues.updateStatus', [$project, $issue]) }}"
                                      @if ($target === App\Enums\IssueStatus::Closed)
                                          data-confirm="Issue yang ditutup menjadi read-only."
                                          data-confirm-title="Tutup issue?"
                                          data-confirm-button="Tutup issue"
                                          data-confirm-variant="primary"
                                      @endif>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $target->value }}">
                                    <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
                                        {{ $issue->status->transitionLabel($target) }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    @endif
                </div>

                @include('collaboration.attachments', ['target' => $issue])
                @include('collaboration.comments', ['target' => $issue])

                @include('activity-logs.partials.history')
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg lg:self-start">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Penanggung jawab</dt>
                        <dd class="font-medium text-gray-900">{{ $issue->assignee?->name ?? 'Belum di-assign' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Pelapor</dt>
                        <dd class="font-medium text-gray-900">{{ $issue->reporter->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Task terkait</dt>
                        <dd class="font-medium">
                            @if ($issue->relatedTask)
                                <a href="{{ route('projects.tasks.show', [$project, $issue->relatedTask]) }}" class="text-indigo-600 hover:text-indigo-800">{{ $issue->relatedTask->title }}</a>
                            @else
                                <span class="text-gray-900">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Risiko terkait</dt>
                        <dd class="font-medium">
                            @if ($issue->relatedRisk)
                                <a href="{{ route('projects.risks.show', [$project, $issue->relatedRisk]) }}" class="text-indigo-600 hover:text-indigo-800">{{ $issue->relatedRisk->title }}</a>
                            @else
                                <span class="text-gray-900">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Dilaporkan</dt>
                        <dd class="font-medium text-gray-900">{{ $issue->created_at->translatedFormat('d F Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
