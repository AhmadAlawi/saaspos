/**
 * Alpine factory for the Regional settings form.
 *
 * Holds the selected time zone + date/time formats two-way bound to the
 * selects, and drives a live preview. The example strings come from the
 * server (PHP `date()` output for each format token), so the preview
 * matches what `format_date()` / `format_datetime()` render app-wide —
 * we just look up the example for the currently selected format rather
 * than re-implementing date formatting in JS.
 */
export function regionalSettings(opts = {}) {
    return {
        timezone:   opts.timezone   ?? 'UTC',
        dateFormat: opts.dateFormat ?? 'd M Y',
        timeFormat: opts.timeFormat ?? 'h:i A',

        /** { format → server-rendered example } */
        dateMap: opts.dateMap ?? {},
        timeMap: opts.timeMap ?? {},

        get datePreview() {
            return this.dateMap[this.dateFormat] ?? '';
        },

        get timePreview() {
            return this.timeMap[this.timeFormat] ?? '';
        },

        get preview() {
            return `${this.datePreview} · ${this.timePreview}`;
        },
    };
}
