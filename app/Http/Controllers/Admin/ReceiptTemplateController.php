<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hardware\SetDefaultReceiptTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceiptTemplateRequest;
use App\Models\ReceiptTemplate;
use App\Models\ReceiptTemplateBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD for {@see ReceiptTemplate} — the metadata (name/paper size/active)
 * only. Block editing (reorder, add/remove free-form blocks, per-block
 * content) is a separate screen, {@see edit()} for now delegates to it.
 */
class ReceiptTemplateController extends Controller
{
    /** Default order for a freshly created template — mirrors the legacy hardcoded sequence. */
    private const DEFAULT_STRUCTURAL_ORDER = [
        'logo', 'store_info', 'items_table', 'totals', 'payments', 'hsn_summary', 'barcode', 'qr',
    ];

    public function index(Request $request): View
    {
        $this->authorize('settings.manage_receipt_templates');

        $rows = ReceiptTemplate::query()
            ->withCount(['blocks', 'elements'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.receipt-templates.index', ['rows' => $rows]);
    }

    public function create(): View
    {
        $this->authorize('settings.manage_receipt_templates');

        return view('admin.receipt-templates.form', [
            'template' => new ReceiptTemplate(['paper_size' => '80mm', 'is_active' => true]),
        ]);
    }

    /**
     * Seeds the structural blocks (logo, store info, items, totals, …) in
     * their legacy-matching default order — the admin reorders/hides from
     * there, never starts from a blank, section-less receipt.
     */
    public function store(ReceiptTemplateRequest $request): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        // layout_mode is a one-time choice, deliberately not part of
        // ReceiptTemplateRequest's shared rules/persistedAttributes() — it
        // must never be settable through update() once content exists.
        $layoutMode = $request->input('layout_mode') === ReceiptTemplate::LAYOUT_CANVAS
            ? ReceiptTemplate::LAYOUT_CANVAS
            : ReceiptTemplate::LAYOUT_BLOCKS;

        $template = ReceiptTemplate::create(array_merge(
            $request->persistedAttributes(),
            ['template' => '', 'is_default' => false, 'layout_mode' => $layoutMode],
        ));

        // Canvas templates start with an empty surface — blocks are the
        // ones seeded with a legacy-matching structural order.
        if ($layoutMode === ReceiptTemplate::LAYOUT_BLOCKS) {
            foreach (self::DEFAULT_STRUCTURAL_ORDER as $i => $type) {
                ReceiptTemplateBlock::create([
                    'receipt_template_id' => $template->id,
                    'type'                => $type,
                    'sort_order'          => $i + 1,
                    'is_visible'          => true,
                ]);
            }
        }

        return redirect()->route('admin.receipt-templates.index')
            ->with('success', __('receipt_templates.flash.created', ['name' => $template->name]));
    }

    public function edit(ReceiptTemplate $receiptTemplate): View
    {
        $this->authorize('settings.manage_receipt_templates');

        return view('admin.receipt-templates.form', ['template' => $receiptTemplate]);
    }

    public function update(ReceiptTemplateRequest $request, ReceiptTemplate $receiptTemplate): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        $receiptTemplate->update($request->persistedAttributes());

        return redirect()->route('admin.receipt-templates.index')
            ->with('success', __('receipt_templates.flash.updated', ['name' => $receiptTemplate->name]));
    }

    public function destroy(ReceiptTemplate $receiptTemplate): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');
        $name = $receiptTemplate->name;
        $receiptTemplate->blocks()->delete();
        $receiptTemplate->delete();

        return redirect()->route('admin.receipt-templates.index')
            ->with('success', __('receipt_templates.flash.deleted', ['name' => $name]));
    }

    public function setDefault(ReceiptTemplate $receiptTemplate, SetDefaultReceiptTemplate $setDefault): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        ($setDefault)($receiptTemplate);

        return redirect()->route('admin.receipt-templates.index')
            ->with('success', __('receipt_templates.flash.default_set', ['name' => $receiptTemplate->name]));
    }
}
