{{--
    One button per allowed workflow transition (FR-011). Moving to Done asks
    for confirmation first. Expects: $project, $task, $transitions (list<TaskStatus>).
--}}
@if (count($transitions) > 0)
    <div class="flex flex-wrap gap-2">
        @foreach ($transitions as $target)
            @php $toDone = $target === App\Enums\TaskStatus::Done; @endphp
            <form method="POST" action="{{ route('projects.tasks.updateStatus', [$project, $task]) }}"
                  @if ($toDone)
                      data-confirm="Task &quot;{{ $task->title }}&quot; akan ditandai Done. Setelah Done, hanya Project Manager yang dapat membukanya kembali."
                      data-confirm-title="Tandai task selesai?"
                      data-confirm-button="Ya, tandai Done"
                      data-confirm-variant="primary"
                  @endif>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $target->value }}">
                <button type="submit" @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium shadow-sm',
                    'bg-green-600 text-white hover:bg-green-500' => $toDone,
                    'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $task->status->isBackwardTo($target),
                    'bg-indigo-600 text-white hover:bg-indigo-500' => ! $toDone && ! $task->status->isBackwardTo($target),
                ])>
                    {{ $task->status->transitionLabel($target) }}
                </button>
            </form>
        @endforeach
    </div>
@endif
