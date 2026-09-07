import Sortable from 'sortablejs';
import { posPost } from '../lib/http.js';

/**
 * Alpine factory wrapping SortableJS for "list of rows you can drag to
 * reorder". The list element posts the new order to a server endpoint
 * after each drag completes.
 *
 *   <ul x-data="sortableList({
 *           handle: '.cat-handle',
 *           url:    '{{ route('admin.categories.reorder') }}',
 *       })">
 *     <li data-id="42">…</li>
 *     <li data-id="17">…</li>
 *   </ul>
 *
 * Required:
 *   url     — POST endpoint; receives JSON { ids: [...] }, top-to-bottom.
 *   handle  — CSS selector inside each row that initiates a drag (so the
 *             rest of the row stays clickable for navigation).
 */
export function sortableList({ url, handle = null } = {}) {
    return {
        instance: null,

        init() {
            this.instance = Sortable.create(this.$el, {
                handle,
                animation: 150,
                ghostClass: 'is-dragging-ghost',
                chosenClass: 'is-dragging',
                dragClass: 'is-dragged',
                onEnd: () => this.persistOrder(),
            });
        },

        destroy() {
            this.instance?.destroy();
            this.instance = null;
        },

        persistOrder() {
            const ids = Array.from(this.$el.children)
                .map((el) => parseInt(el.dataset.id, 10))
                .filter((id) => Number.isFinite(id));

            if (ids.length === 0) return;

            posPost(url, { ids }).catch(() => {
                /* No-op for now. A future toast system can surface the error. */
            });
        },
    };
}
