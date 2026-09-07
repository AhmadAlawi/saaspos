/**
 * Admin shell entry — bundled separately from the global app.js so the
 * cashier checkout can opt out of the heavier admin module.
 *
 * Wires Alpine + admin-specific stores + Alpine.data factories +
 * global keyboard shortcuts. No DOM markup — the Blade components in
 * resources/views/components/admin/* render everything.
 */
import Alpine from 'alpinejs';

import { registerUiStore }     from './stores/ui.js';
import { registerAdminStores } from './admin/stores.js';
import { registerToastStore } from './admin/toasts.js';
import { registerConfirmStore } from './admin/confirm.js';
import { dropdown }            from './admin/dropdowns.js';
import { commandPalette }      from './admin/command-palette.js';
import { navTooltip }          from './admin/nav-tooltip.js';
import { navGroup }            from './admin/nav-group.js';
import { sortableList }        from './admin/sortable-list.js';
import { dataTable }           from './admin/data-table.js';
import { enhancedSelect, remoteSelect } from './admin/select.js';
import { timePicker }          from './admin/time-picker.js';
import { manualJournalEntry }  from './admin/manual-journal-entry.js';
import { categoriesPage }      from './admin/categories-page.js';
import { brandsPage }          from './admin/brands-page.js';
import { unitsPage }           from './admin/units-page.js';
import { unitCategoriesPage }  from './admin/unit-categories-page.js';
import { productsPage }        from './admin/products-page.js';
import { bulkProductImages }   from './admin/bulk-product-images.js';
import { shiftsIndexPage }     from './admin/shifts-index.js';
import { rowActionsMenu }      from './admin/row-actions.js';
import { pwaInstall }          from './admin/pwa-install.js';
import { storesPage }          from './admin/stores-page.js';
import { terminalsPage }       from './admin/terminals-page.js';
import { terminalForm }        from './admin/terminal-form.js';
import { kioskOrdersPage }     from './admin/kiosk-orders-page.js';
import { kioskAllOrdersPage }  from './admin/kiosk-all-orders-page.js';
import { printButton }         from './admin/print-button.js';
import { drawerOpener }         from './admin/drawer-opener.js';
import { labelWizard }          from './admin/label-wizard.js';
import { printQueuePanel }      from './admin/print-queue-panel.js';
import { hardwareDiagnostics }  from './admin/hardware-diagnostics.js';
import { fullscreenToggle }     from './admin/fullscreen-toggle.js';
import { roleBuilder }          from './admin/role-builder.js';
import { userStoreRoles }       from './admin/user-store-roles.js';
import { productEditor }       from './admin/product-editor.js';
import { drugSchedulesPage }   from './admin/drug-schedules-page.js';
import { reasonsPage }         from './admin/reasons-page.js';
import { taxComponentsPage }   from './admin/tax-components-page.js';
import { taxGroupsPage }           from './admin/tax-groups-page.js';
import { taxClassificationsPage } from './admin/tax-classifications-page.js';
import { chartOfAccountsPage }    from './admin/chart-of-accounts-page.js';
import { openingBalances }         from './admin/opening-balances.js';
import { cashierPage }         from './cashier/cashier-page.js';
import { shiftGate }           from './cashier/shift-gate.js';
import { denominationHelper }  from './admin/denomination-helper.js';
import { calculator }          from './cashier/calculator.js';
import { stockAdjustmentEditor } from './admin/stock-adjustment-editor.js';
import { scaleSettingsPage }     from './admin/scale-settings-page.js';
import { stockTransferEditor }   from './admin/stock-transfer-editor.js';
import { stockTakeEditor }       from './admin/stock-take-editor.js';
import { customerForm }         from './admin/customer-form.js';
import { purchaseForm }         from './admin/purchase-form.js';
import { supplierPaymentForm }  from './admin/supplier-payment-form.js';
import { customerPaymentForm }  from './admin/customer-payment-form.js';
import { saleRefundPage }       from './admin/sale-refund-page.js';
import { dashboardPage }        from './admin/dashboard-page.js';
import { setupChecklist }       from './admin/setup-checklist.js';
import { returnReasonsPage }    from './admin/return-reasons-page.js';
import { customerGroupsPage }   from './admin/customer-groups-page.js';
import { customersIndexPage }   from './admin/customers-index.js';
import { salesIndexPage }       from './admin/sales-index.js';
import { serverTablePage }      from './admin/server-table.js';
import { currencySettings }    from './admin/currency-settings.js';
import { regionalSettings }    from './admin/regional-settings.js';
import { brandingSettings }    from './admin/branding-settings.js';
import { receiptSettings }     from './admin/receipt-settings.js';
import { richTextEditor }      from './admin/rich-text-editor.js';
import { restoreWizard }       from './admin/restore-wizard.js';
import { updatesPage }         from './admin/updates-page.js';
import { notificationsPanel }  from './admin/notifications-panel.js';
import { daySummaryPanel }     from './admin/day-summary-panel.js';
import { imageUpload }         from './admin/image-upload.js';
import { fileUpload }          from './admin/file-upload.js';
import { registerDatepickers } from './admin/datepicker.js';
import { registerNumericInputGuard } from './admin/numeric-input-guard.js';
import { registerShortcuts }   from './admin/shortcuts.js';
import { registerSubmitLoader } from './lib/submit-loader.js';
import { registerFormValidators } from './lib/form-validators.js';
import { registerPhoneInputs } from './lib/phone-input.js';
import { registerSearchEnterSubmit } from './lib/search-enter-submit.js';
import { registerInvSearchLive } from './lib/inv-search-live.js';
import { registerInvFilterAjax } from './lib/inv-filter-ajax.js';
import { registerFormatMoney }   from './lib/format-money.js';
import { registerFormatPhone }   from './lib/phone-format.js';
import { registerHttpClient }    from './lib/http.js';
import { registerAjaxForms }     from './lib/ajax-form.js';
import { submitDeleteForm, submitDeleteRow } from './lib/delete-form.js';
import { submitForm }           from './lib/submit-form.js';

/* Hydrate the command-palette index from the server-rendered meta tag.
   AdminLayout writes it on render so JS stays free of nav-schema duplication. */
try {
    const raw = document.querySelector('meta[name="pos-cmd-index"]')?.content;
    if (raw) window.POS_CMD_INDEX = JSON.parse(raw);
} catch (e) {
    window.POS_CMD_INDEX = [];
}

/* Hydrate the current user's permission set for UI gating via $user.can().
   This is presentation only — the server re-checks every action. */
try {
    const raw = document.querySelector('meta[name="pos-user"]')?.content;
    window.__user = raw ? JSON.parse(raw) : { id: null, is_super_admin: false, permissions: [] };
} catch (e) {
    window.__user = { id: null, is_super_admin: false, permissions: [] };
}

document.addEventListener('alpine:init', () => {
    // Money formatter — exposes window.posFormatMoney() AND $formatMoney
    // magic inside Alpine. See lib/format-money.js.
    registerFormatMoney(Alpine);

    // Phone formatter — `window.posFormatPhone()` + `$formatPhone` magic;
    // mirrors PHP's PhoneFormatter::pretty (space after the calling code).
    registerFormatPhone(Alpine);

    // HTTP client (axios) — the ONLY way admin JS talks to the server.
    // Window globals + Alpine `$http` magic. See lib/http.js.
    registerHttpClient(Alpine);

    // Generic `data-ajax-form` handler — converts any form to AJAX
    // with one attribute, no per-form factory work. See lib/ajax-form.js.
    registerAjaxForms();

    Alpine.magic('user', () => ({
        id: window.__user?.id ?? null,
        isSuperAdmin: !!window.__user?.is_super_admin,
        can: (perm) => !!window.__user?.is_super_admin || (window.__user?.permissions || []).includes(perm),
    }));
    // $deleteForm(url) — POSTs a DELETE and keeps the confirm-dialog spinner
    // up until navigation. The system-wide way to wire a confirmed delete:
    //   onConfirm: () => $deleteForm('{{ route('admin.x.destroy', $m) }}')
    Alpine.magic('deleteForm', () => submitDeleteForm);
    // $deleteRow(url, $event.target) — same as $deleteForm but removes the
    // closest [data-dt-row] from the DOM on success instead of reloading
    // the page. Used by list tables where a full reload is excessive.
    Alpine.magic('deleteRow', () => submitDeleteRow);
    // $submitForm(url, extraFields?) — general-purpose confirm-driven POST
    // (Post adjustment, Approve, Send, etc.). Same never-resolving Promise
    // contract as $deleteForm so the dialog spinner stays up until navigation.
    Alpine.magic('submitForm', () => submitForm);
    registerUiStore(Alpine);
    registerAdminStores(Alpine);
    registerToastStore(Alpine);
    registerConfirmStore(Alpine);
    Alpine.data('dropdown',       dropdown);
    Alpine.data('commandPalette', commandPalette);
    Alpine.data('navTooltip',     navTooltip);
    Alpine.data('navGroup',       navGroup);
    Alpine.data('sortableList',   sortableList);
    Alpine.data('dataTable',       dataTable);
    Alpine.data('enhancedSelect',  enhancedSelect);
    Alpine.data('timePicker',      timePicker);
    Alpine.data('manualJournalEntry', manualJournalEntry);
    Alpine.data('remoteSelect',    remoteSelect);
    Alpine.data('categoriesPage',  categoriesPage);
    Alpine.data('brandsPage',      brandsPage);
    Alpine.data('unitsPage',       unitsPage);
    Alpine.data('productsPage',    productsPage);
    Alpine.data('bulkProductImages', bulkProductImages);
    Alpine.data('shiftsIndexPage', shiftsIndexPage);
    Alpine.data('rowActionsMenu',  rowActionsMenu);
    Alpine.data('pwaInstall',      pwaInstall);
    Alpine.data('storesPage',      storesPage);
    Alpine.data('terminalsPage',   terminalsPage);
    Alpine.data('terminalForm',    terminalForm);
    Alpine.data('kioskOrdersPage', kioskOrdersPage);
    Alpine.data('kioskAllOrdersPage', kioskAllOrdersPage);
    Alpine.data('printButton',     printButton);
    Alpine.data('drawerOpener',    drawerOpener);
    Alpine.data('labelWizard',     labelWizard);
    Alpine.data('printQueuePanel', printQueuePanel);
    Alpine.data('hardwareDiagnostics', hardwareDiagnostics);
    Alpine.data('fullscreenToggle', fullscreenToggle);
    Alpine.data('roleBuilder',     roleBuilder);
    Alpine.data('userStoreRoles',  userStoreRoles);
    Alpine.data('productEditor',   productEditor);
    Alpine.data('drugSchedulesPage', drugSchedulesPage);
    Alpine.data('reasonsPage',      reasonsPage);
    Alpine.data('taxComponentsPage',  taxComponentsPage);
    Alpine.data('taxGroupsPage',          taxGroupsPage);
    Alpine.data('taxClassificationsPage', taxClassificationsPage);
    Alpine.data('chartOfAccountsPage',    chartOfAccountsPage);
    Alpine.data('openingBalances',        openingBalances);
    Alpine.data('unitCategoriesPage', unitCategoriesPage);
    Alpine.data('cashierPage',       cashierPage);
    Alpine.data('shiftGate',         shiftGate);
    Alpine.data('denominationHelper', denominationHelper);
    Alpine.data('calculator',        calculator);
    Alpine.data('dashboardPage',     dashboardPage);
    Alpine.data('setupChecklist',    setupChecklist);
    Alpine.data('stockAdjustmentEditor', stockAdjustmentEditor);
    Alpine.data('scaleSettingsPage',     scaleSettingsPage);
    Alpine.data('stockTransferEditor',   stockTransferEditor);
    Alpine.data('stockTakeEditor',       stockTakeEditor);
    Alpine.data('customerForm',     customerForm);
    Alpine.data('purchaseForm',     purchaseForm);
    Alpine.data('supplierPaymentForm', supplierPaymentForm);
    Alpine.data('customerPaymentForm', customerPaymentForm);
    Alpine.data('saleRefundPage',  saleRefundPage);
    Alpine.data('returnReasonsPage', returnReasonsPage);
    Alpine.data('customerGroupsPage', customerGroupsPage);
    Alpine.data('customersIndexPage', customersIndexPage);
    Alpine.data('salesIndexPage', salesIndexPage);
    // Purchases + Sync log reuse the generic self-managed-filter-form +
    // server-pagination factory as-is — only the endpoint + columns differ.
    Alpine.data('purchasesIndexPage', salesIndexPage);
    Alpine.data('syncLogPage', salesIndexPage);
    Alpine.data('stockMovementsPage', salesIndexPage);
    Alpine.data('supplierPaymentsPage', salesIndexPage);
    Alpine.data('customerPaymentsPage', salesIndexPage);
    Alpine.data('purchaseReturnsPage', salesIndexPage);
    Alpine.data('expensesPage', salesIndexPage);
    Alpine.data('stockLevelsPage', salesIndexPage);
    Alpine.data('batchesPage', salesIndexPage);
    Alpine.data('stockAdjustmentsPage', salesIndexPage);
    Alpine.data('stockTransfersPage', salesIndexPage);
    // Plain server-paginated tables (component search + sortable headers, no
    // filter form): Users, Roles.
    Alpine.data('usersIndexPage', serverTablePage);
    Alpine.data('rolesIndexPage', serverTablePage);
    // The customers index factory is generic (rowState + AJAX toggle + dataTable);
    // suppliers reuse it as-is. Registering an alias keeps the Blade markup readable.
    Alpine.data('suppliersIndexPage',  customersIndexPage);
    Alpine.data('currencySettings', currencySettings);
    Alpine.data('regionalSettings', regionalSettings);
    Alpine.data('brandingSettings', brandingSettings);
    Alpine.data('receiptSettings',  receiptSettings);
    Alpine.data('richTextEditor',   richTextEditor);
    Alpine.data('restoreWizard',    restoreWizard);
    Alpine.data('updatesPage',      updatesPage);
    Alpine.data('notificationsPanel', notificationsPanel);
    Alpine.data('daySummaryPanel',    daySummaryPanel);
    Alpine.data('imageUpload',     imageUpload);
    Alpine.data('fileUpload',      fileUpload);
});

window.Alpine = Alpine;
Alpine.start();

registerShortcuts(Alpine);
registerNumericInputGuard();
registerDatepickers();

// System-wide client-side validation for email + phone fields. Runs in
// the capture phase so a failure can preventDefault() *before* the
// submit-loader attaches a spinner (see lib/form-validators.js).
registerFormValidators();

// Upgrade every phone-flavoured input with intl-tel-input (flag
// dropdown + libphonenumber validation). Rewrites the input's value
// to E.164 on submit. See resources/js/lib/phone-input.js.
registerPhoneInputs();

// Make every <input type="search"> submit its form on Enter — works
// around the implicit-submission rule being disabled when
// enhancedSelect() adds its own text input to the same form. See
// resources/js/lib/search-enter-submit.js.
registerSearchEnterSubmit();

// Live-as-you-type search for `.inv-search` filter inputs — debounced
// auto-submit so the filter feels instant. See lib/inv-search-live.js.
registerInvSearchLive();

// AJAX the system-wide `.inv-filter` server forms — search / filter /
// reset swap just the table rows instead of reloading the page. The
// live-search + datepicker submits above feed into this via the submit
// event. See lib/inv-filter-ajax.js.
registerInvFilterAjax();

// Every submit button shows a loader + locks against double-submit.
registerSubmitLoader();
