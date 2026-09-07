/**
 * timePicker — a themed hour / minute / AM-PM dropdown.
 *
 * Native `<input type="time">` and `<select>` both render their dropdown in
 * unstyleable browser chrome; this gives time fields the same look as the rest
 * of the design system (dark-mode + RTL aware) while keeping the familiar
 * column picker UX.
 *
 * The bound value is always 24-hour `H:i` (e.g. "08:30") so it round-trips with
 * the server's `date_format:H:i` validation. Wire it with x-modelable:
 *
 *   <div x-data="timePicker()" x-modelable="value" x-model="form.time_of_day"> … </div>
 */
export function timePicker() {
    return {
        value: '08:00',        // 24-hour H:i — synced to the parent via x-modelable
        open: false,

        hours:   Array.from({ length: 12 }, (_, i) => i + 1),   // 1..12
        minutes: Array.from({ length: 12 }, (_, i) => i * 5),   // 0,5,…,55

        /** Break the 24-hour value into { hr:1-12, m, ampm }. */
        _parts() {
            const [hh, mm] = String(this.value || '08:00').split(':');
            let h = parseInt(hh, 10); if (Number.isNaN(h)) h = 8;
            let m = parseInt(mm, 10); if (Number.isNaN(m)) m = 0;
            const ampm = h >= 12 ? 'PM' : 'AM';
            let hr = h % 12; if (hr === 0) hr = 12;
            return { hr, m, ampm };
        },

        get display() {
            const { hr, m, ampm } = this._parts();
            return `${hr}:${String(m).padStart(2, '0')} ${ampm}`;
        },

        isHour(h)   { return this._parts().hr === h; },
        isMinute(m) { return this._parts().m === m; },
        isAmpm(a)   { return this._parts().ampm === a; },

        setHour(h)   { const p = this._parts(); this._compose(h, p.m, p.ampm); },
        setMinute(m) { const p = this._parts(); this._compose(p.hr, m, p.ampm); },
        setAmpm(a)   { const p = this._parts(); this._compose(p.hr, p.m, a); },

        /** Recombine parts back into a 24-hour H:i string. */
        _compose(hr, m, ampm) {
            let h = hr % 12;
            if (ampm === 'PM') h += 12;
            this.value = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                // Scroll the current selection into view in each column.
                this.$nextTick(() => {
                    this.$refs.pop?.querySelectorAll('.tp-opt.is-active')
                        .forEach((el) => el.scrollIntoView({ block: 'center' }));
                });
            }
        },
    };
}
