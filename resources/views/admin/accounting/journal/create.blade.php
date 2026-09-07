<x-admin-layout
    active="journal"
    :title="__('accounting.journal.new_entry')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.journal.title'), 'href' => route('admin.accounting.journal.index')],
        ['label' => __('accounting.journal.new_entry')],
    ]">

    <div class="page-wide" x-data="manualJournalEntry()">
        @if ($errors->any())
            <div class="alert alert-danger mb-4">
                <span class="alert-icon"><x-icon name="alert" class="w-5 h-5" /></span>
                <div class="alert-body">
                    <ul class="alert-list">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('accounting.journal.new_entry') }}</h1>
                <p class="page-sub">{{ __('accounting.journal.create_sub') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.accounting.journal.store') }}">
            @csrf

            <div class="card mb-4">
                <div class="card-body">
                    <div class="form-stack">
                        <div class="grid grid-cols-2 gap-3">
                            <label class="field">
                                <span class="field-label">{{ __('accounting.journal.fields.date') }}</span>
                                <input type="text" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" class="pos-input js-datepicker">
                            </label>
                            @if (count($stores) > 1)
                                <label class="field">
                                    <span class="field-label">{{ __('reports.filter.store') }}</span>
                                    <select name="store_id" class="pos-input" x-data="enhancedSelect()">
                                        @foreach ($stores as $store)
                                            <option value="{{ $store->id }}" @selected(current_store_id() === $store->id)>{{ $store->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        </div>
                        <label class="field">
                            <span class="field-label">{{ __('accounting.journal.fields.description') }}</span>
                            <input type="text" name="description" value="{{ old('description') }}" class="pos-input" maxlength="255"
                                   placeholder="{{ __('accounting.journal.fields.description_placeholder') }}">
                        </label>
                    </div>
                </div>
            </div>

            <div class="card card-pad-0 mb-4">
                <div class="dt-scroll">
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th style="width:36%">{{ __('accounting.journal.columns.account') }}</th>
                            <th>{{ __('accounting.journal.columns.line_description') }}</th>
                            <th class="num" style="width:130px">{{ __('accounting.journal.columns.debit') }}</th>
                            <th class="num" style="width:130px">{{ __('accounting.journal.columns.credit') }}</th>
                            <th style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, i) in lines" :key="line.uid">
                            <tr>
                                <td>
                                    <select :name="`lines[${i}][account_id]`" x-model="line.account_id"
                                            class="pos-input" x-data="enhancedSelect({ dropdownParent: 'body' })">
                                        <option value="">{{ __('accounting.journal.fields.select_account') }}</option>
                                        @foreach ($accounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" :name="`lines[${i}][description]`" x-model="line.description" class="pos-input" maxlength="255"></td>
                                <td><input type="number" min="0" step="0.01" :name="`lines[${i}][debit]`" x-model="line.debit" @input="onDebit(line)" class="pos-input num"></td>
                                <td><input type="number" min="0" step="0.01" :name="`lines[${i}][credit]`" x-model="line.credit" @input="onCredit(line)" class="pos-input num"></td>
                                <td class="text-center">
                                    <button type="button" class="icon-btn" @click="removeLine(i)" x-show="lines.length > 2" aria-label="{{ __('accounting.journal.remove_line') }}">
                                        <x-icon name="x" class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="dt-total-row font-semibold">
                            <td colspan="2">
                                <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="addLine()">
                                    <x-icon name="plus" class="w-4 h-4" /> {{ __('accounting.journal.add_line') }}
                                </button>
                            </td>
                            <td class="num tnum" x-text="$formatMoney(totalDebit)"></td>
                            <td class="num tnum" x-text="$formatMoney(totalCredit)"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="text-sm">
                    <span x-show="balanced" class="prod-badge prod-badge-positive">{{ __('reports.trial_balance.balanced') }}</span>
                    <span x-show="!balanced && totalDebit > 0" class="prod-badge prod-badge-negative"
                          x-text="'{{ __('accounting.journal.off_by') }} ' + $formatMoney(Math.abs(difference))"></span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.accounting.journal.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('accounting.journal.cancel') }}</a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary" :disabled="!canSubmit">{{ __('accounting.journal.post') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
