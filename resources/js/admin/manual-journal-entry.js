/**
 * manualJournalEntry — the line editor for a manual journal entry. Manages the
 * rows (add/remove), keeps each line one-sided (a debit clears its credit and
 * vice versa), and computes the live debit/credit totals so the Post button
 * only enables on a balanced, complete entry.
 *
 * The form submits normally (server posts via PostJournalEntry); this component
 * only drives the editor UI. Row inputs carry `lines[i][...]` names.
 */
export function manualJournalEntry() {
    let uid = 0;
    const blank = () => ({ uid: ++uid, account_id: '', description: '', debit: '', credit: '' });

    return {
        lines: [blank(), blank()],

        addLine() {
            this.lines.push(blank());
        },

        removeLine(i) {
            if (this.lines.length > 2) {
                this.lines.splice(i, 1);
            }
        },

        // A line is exactly one of debit / credit — entering one clears the other.
        onDebit(line) {
            if (Number(line.debit) > 0) line.credit = '';
        },
        onCredit(line) {
            if (Number(line.credit) > 0) line.debit = '';
        },

        get totalDebit() {
            return this.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0);
        },
        get totalCredit() {
            return this.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);
        },
        get difference() {
            return this.totalDebit - this.totalCredit;
        },
        get balanced() {
            return this.totalDebit > 0 && Math.abs(this.difference) < 0.0001;
        },
        get canSubmit() {
            return this.balanced && this.lines.every((l) =>
                l.account_id && ((Number(l.debit) || 0) > 0) !== ((Number(l.credit) || 0) > 0));
        },
    };
}
