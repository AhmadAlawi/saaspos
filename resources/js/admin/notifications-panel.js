/**
 * Topbar bell dropdown — the signed-in user's database notifications.
 *
 * The unread count is server-rendered on first paint (for the bell dot); the
 * full list is fetched lazily the first time the panel is opened. Marking
 * read updates the count in place. Clicking an item marks it read and follows
 * its link.
 */
export function notificationsPanel(initial = {}) {
    return {
        open: false,
        unread: initial.unread || 0,
        emptySub: initial.emptySub || '',
        unreadTpl: initial.unreadTpl || ':count',
        urls: initial.urls || {},

        items: [],
        loaded: false,
        busy: false,

        get subText() {
            return this.unread > 0
                ? this.unreadTpl.replace(':count', this.unread)
                : this.emptySub;
        },

        toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) this.load();
        },

        close() {
            this.open = false;
        },

        async load() {
            this.busy = true;
            try {
                const { data } = await this.$http.get(this.urls.index);
                this.items = data.items || [];
                this.unread = data.unread || 0;
                this.loaded = true;
            } catch (e) {
                // Silent — the bell simply keeps its server-rendered count.
            } finally {
                this.busy = false;
            }
        },

        async markAll() {
            try {
                await this.$http.post(this.urls.readAll);
                this.items = this.items.map((i) => ({ ...i, read: true }));
                this.unread = 0;
            } catch (e) {
                // no-op
            }
        },

        async openItem(item) {
            if (!item.read) {
                try {
                    await this.$http.post(this.urls.read.replace('__id__', item.id));
                    item.read = true;
                    this.unread = Math.max(0, this.unread - 1);
                } catch (e) {
                    // no-op
                }
            }
            if (item.url) {
                window.location.href = item.url;
            }
        },
    };
}
