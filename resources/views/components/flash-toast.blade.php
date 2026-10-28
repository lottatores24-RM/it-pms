{{--
    Toast messages (NFR-005): session flashes on page load, plus any
    window "toast" event, e.g. dispatched by JavaScript with
    detail { type: 'success' | 'error', message: '...' }.
--}}
@php
$initial = collect(['success' => session('success'), 'error' => session('error')])
    ->filter()
    ->map(fn ($message, $type) => ['type' => $type, 'message' => $message])
    ->values();
@endphp

<div x-data="{
        toasts: [],
        nextId: 0,
        push(toast) {
            const id = this.nextId++;
            this.toasts.push({ id, ...toast });
            setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
     }"
     x-init="@js($initial).forEach((toast) => push(toast))"
     x-on:toast.window="push($event.detail)"
     class="pointer-events-none fixed right-4 top-4 z-50 flex w-full max-w-sm flex-col gap-2"
     aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition.opacity.duration.300ms
             role="status"
             :class="toast.type === 'error' ? 'bg-red-600' : 'bg-green-600'"
             class="pointer-events-auto flex items-start gap-3 rounded-lg px-4 py-3 text-sm text-white shadow-lg">
            <span class="flex-1" x-text="toast.message"></span>
            <button type="button" @click="remove(toast.id)" class="opacity-80 hover:opacity-100" aria-label="Tutup">&times;</button>
        </div>
    </template>
</div>
