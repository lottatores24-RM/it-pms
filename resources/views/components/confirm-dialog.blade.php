{{--
    Global confirmation dialog (NFR-005). Any form with a data-confirm="message"
    attribute is intercepted by resources/js/app.js and only submitted after
    confirmation. Optional: data-confirm-title, data-confirm-button,
    data-confirm-variant="primary".

    Scripts can also dispatch a "confirm-dialog" window event with
    detail { title, message, button, danger, onConfirm, onCancel }.
--}}
<div x-data="{
        open: false,
        form: null,
        onConfirm: null,
        onCancel: null,
        title: '',
        message: '',
        button: '',
        danger: true,
        ask(detail) {
            this.form = detail.form ?? null;
            this.onConfirm = detail.onConfirm ?? null;
            this.onCancel = detail.onCancel ?? null;
            this.title = detail.title ?? 'Konfirmasi';
            this.message = detail.message ?? '';
            this.button = detail.button ?? 'Ya, lanjutkan';
            this.danger = detail.danger ?? true;
            this.open = true;
            this.$nextTick(() => this.$refs.cancel.focus());
        },
        confirm() {
            this.open = false;
            if (this.form) {
                this.form.dataset.confirmed = '1';
                this.form.requestSubmit();
            } else if (this.onConfirm) {
                this.onConfirm();
            }
        },
        cancel() {
            if (! this.open) {
                return;
            }
            this.open = false;
            if (this.onCancel) {
                this.onCancel();
            }
        },
     }"
     x-on:confirm-dialog.window="ask($event.detail)"
     x-on:keydown.escape.window="cancel()"
     x-show="open"
     class="fixed inset-0 z-50 flex items-center justify-center px-4"
     role="dialog"
     aria-modal="true"
     style="display: none;">
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-900/60" @click="cancel()"></div>

    <div x-show="open" x-transition class="relative w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold text-gray-900" x-text="title"></h3>
        <p class="mt-2 text-sm text-gray-600" x-text="message"></p>

        <div class="mt-6 flex justify-end gap-3">
            <x-secondary-button x-ref="cancel" @click="cancel()">Batal</x-secondary-button>
            <button type="button"
                    @click="confirm()"
                    :class="danger ? 'bg-red-600 hover:bg-red-500 focus:ring-red-500' : 'bg-indigo-600 hover:bg-indigo-500 focus:ring-indigo-500'"
                    class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2"
                    x-text="button"></button>
        </div>
    </div>
</div>
