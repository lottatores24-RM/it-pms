import Sortable from 'sortablejs';

/**
 * Kanban board (FR-017).
 *
 * Markup contract (resources/views/kanban/index.blade.php):
 *  - [data-kanban]                 board root, data-move-url contains "__TASK__"
 *  - [data-kanban-column]          column list, data-status = TaskStatus value
 *  - [data-kanban-count]           counter inside a column, data-status = value
 *  - [data-kanban-card]            card, data-task-id, data-title,
 *                                  data-transitions = JSON array of allowed targets
 *  - select[data-kanban-select]    per-card status picker for small screens
 *
 * The server re-validates every move; the client-side check only gives
 * immediate feedback. A rejected move puts the card back where it was.
 */
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const toast = (type, message) => window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }));

const confirmMove = (title) => new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('confirm-dialog', {
        detail: {
            title: 'Tandai task selesai?',
            message: `Task "${title}" akan ditandai Done. Setelah Done, hanya Project Manager yang dapat membukanya kembali.`,
            button: 'Ya, tandai Done',
            danger: false,
            onConfirm: () => resolve(true),
            onCancel: () => resolve(false),
        },
    }));
});

function transitionsOf(card) {
    try {
        return JSON.parse(card.dataset.transitions || '[]');
    } catch {
        return [];
    }
}

function refreshCounts(board) {
    board.querySelectorAll('[data-kanban-column]').forEach((column) => {
        const counter = board.querySelector(`[data-kanban-count][data-status="${column.dataset.status}"]`);
        if (counter) {
            counter.textContent = column.querySelectorAll('[data-kanban-card]').length;
        }
    });
}

function refreshSelect(card, status, transitions) {
    const select = card.querySelector('[data-kanban-select]');
    if (!select) {
        return;
    }

    select.innerHTML = '';
    select.add(new Option(status, '', true, true));
    transitions.forEach((target) => select.add(new Option(`→ ${target}`, target)));
    select.disabled = transitions.length === 0;
}

function putBack(card, origin, index) {
    const reference = origin.children[index] ?? null;
    origin.insertBefore(card, reference === card ? card.nextSibling : reference);
}

async function move(board, card, target, origin, originIndex) {
    const allowed = transitionsOf(card);

    if (!allowed.includes(target)) {
        putBack(card, origin, originIndex);
        refreshCounts(board);
        toast('error', `Task tidak dapat dipindahkan ke ${target}.`);
        return;
    }

    if (target === 'Done' && !(await confirmMove(card.dataset.title))) {
        putBack(card, origin, originIndex);
        refreshCounts(board);
        return;
    }

    try {
        const response = await fetch(board.dataset.moveUrl.replace('__TASK__', card.dataset.taskId), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ status: target }),
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = data.errors?.status?.[0] ?? data.message ?? 'Perpindahan ditolak.';
            throw new Error(response.status === 403 ? 'Anda tidak berhak memindahkan task ini.' : message);
        }

        card.dataset.status = data.status;
        card.dataset.transitions = JSON.stringify(data.transitions ?? []);
        refreshSelect(card, data.status, data.transitions ?? []);
        toast('success', data.message);
    } catch (error) {
        putBack(card, origin, originIndex);
        toast('error', error.message);
    }

    refreshCounts(board);
}

export function initKanban() {
    const board = document.querySelector('[data-kanban]');
    if (!board) {
        return;
    }

    board.querySelectorAll('[data-kanban-column]').forEach((column) => {
        Sortable.create(column, {
            group: 'kanban',
            animation: 150,
            draggable: '[data-kanban-card][data-draggable="true"]',
            ghostClass: 'opacity-40',
            sort: false,
            onEnd: (event) => {
                if (event.from === event.to) {
                    return;
                }
                move(board, event.item, event.to.dataset.status, event.from, event.oldIndex);
            },
        });
    });

    // Small screens: move through the per-card select instead of dragging (NFR-006).
    board.addEventListener('change', (event) => {
        const select = event.target.closest('[data-kanban-select]');
        if (!select || !select.value) {
            return;
        }

        const card = select.closest('[data-kanban-card]');
        const origin = card.parentElement;
        const originIndex = Array.prototype.indexOf.call(origin.children, card);
        const target = board.querySelector(`[data-kanban-column][data-status="${select.value}"]`);

        target.prepend(card);
        refreshCounts(board);
        move(board, card, select.value, origin, originIndex);
    });
}
