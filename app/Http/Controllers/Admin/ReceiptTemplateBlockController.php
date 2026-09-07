<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ReceiptTemplate;
use App\Models\ReceiptTemplateBlock;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The actual block editor for a {@see ReceiptTemplate} — reorder, add/
 * remove free-form blocks, edit per-block content. Metadata (name/paper
 * size) lives on {@see ReceiptTemplateController}; this is deliberately
 * a separate screen.
 */
class ReceiptTemplateBlockController extends Controller
{
    public function edit(ReceiptTemplate $receiptTemplate): View
    {
        $this->authorize('settings.manage_receipt_templates');

        return view('admin.receipt-templates.blocks', [
            'template' => $receiptTemplate,
            'blocks'   => $receiptTemplate->blocks,
        ]);
    }

    /** Appends a new free-form block (text/image/divider/spacer) at the end of the list. */
    public function store(Request $request, ReceiptTemplate $receiptTemplate): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        $data = $request->validate([
            'type' => ['required', Rule::in(ReceiptTemplateBlock::FREEFORM_TYPES)],
        ]);

        $nextOrder = (int) $receiptTemplate->blocks()->max('sort_order') + 1;
        ReceiptTemplateBlock::create([
            'receipt_template_id' => $receiptTemplate->id,
            'type'                => $data['type'],
            'sort_order'          => $nextOrder,
            'is_visible'          => true,
        ]);

        return redirect()->route('admin.receipt-templates.blocks.edit', $receiptTemplate)
            ->with('success', __('receipt_templates.flash.block_added'));
    }

    /**
     * Visibility toggle (any block type) + content (text/image only, no-op
     * for structural types since they carry no `config`).
     */
    public function update(Request $request, ReceiptTemplate $receiptTemplate, ReceiptTemplateBlock $block): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');
        $this->ensureBelongsToTemplate($receiptTemplate, $block);

        $data = $request->validate([
            'is_visible'   => ['sometimes', 'boolean'],
            'text'         => ['nullable', 'string', 'max:2000'],
            'image'        => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'image_remove' => ['sometimes', 'boolean'],
        ]);

        $update = ['is_visible' => $request->boolean('is_visible', $block->is_visible)];

        if ($block->type === 'text') {
            $update['config'] = ['text' => trim((string) ($data['text'] ?? ''))];
        } elseif ($block->type === 'image') {
            $config = $block->config ?? [];
            if ($request->hasFile('image')) {
                $this->deleteStoredImage($block);
                $config['image_path'] = $request->file('image')->store('receipt-templates', 'public');
            } elseif ($request->boolean('image_remove')) {
                $this->deleteStoredImage($block);
                $config['image_path'] = null;
            }
            $update['config'] = $config;
        }

        $block->update($update);

        return redirect()->route('admin.receipt-templates.blocks.edit', $receiptTemplate)
            ->with('success', __('receipt_templates.flash.block_updated'));
    }

    /** AJAX — persists the dragged order from {@see resources/js/admin/sortable-list.js}. */
    public function reorder(Request $request, ReceiptTemplate $receiptTemplate): JsonResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $blocks = $receiptTemplate->blocks()->whereIn('id', $data['ids'])->get()->keyBy('id');
        foreach ($data['ids'] as $i => $id) {
            $blocks->get($id)?->update(['sort_order' => $i + 1]);
        }

        return response()->json(['ok' => true]);
    }

    /** Free-form blocks only — structural blocks (items/totals/…) can't be removed. */
    public function destroy(ReceiptTemplate $receiptTemplate, ReceiptTemplateBlock $block): RedirectResponse
    {
        $this->authorize('settings.manage_receipt_templates');
        $this->ensureBelongsToTemplate($receiptTemplate, $block);
        abort_if($block->isStructural(), 422);

        if ($block->type === 'image') {
            $this->deleteStoredImage($block);
        }
        $block->delete();

        return redirect()->route('admin.receipt-templates.blocks.edit', $receiptTemplate)
            ->with('success', __('receipt_templates.flash.block_removed'));
    }

    /**
     * Renders the actual `sales.receipt` view with this template forced in
     * — the exact same code path {@see \App\Actions\Hardware\PreparePrintPayload}
     * uses, so what the admin sees here is what really prints. Meant to
     * be embedded in an <iframe>, not linked to directly.
     */
    public function preview(ReceiptTemplate $receiptTemplate): Response
    {
        $this->authorize('settings.manage_receipt_templates');

        $sale = Sale::with([
            'store', 'customer', 'cashier',
            'items.product', 'items.variant', 'items.batch',
            'payments.paymentMethod',
        ])->latest()->first();

        if (! $sale) {
            return response(
                '<p style="font-family:sans-serif;padding:24px;color:#6b7280;">'
                .__('receipt_templates.blocks.no_sales_yet').'</p>',
            );
        }

        $company = Company::current() ?? new Company();

        $html = ViewFacade::make('sales.receipt', [
            'sale'       => $sale,
            'company'    => $company,
            'paper'      => $receiptTemplate->paper_size,
            'auto'       => false,
            'template'   => $receiptTemplate->loadMissing('blocks'),
            'receiptUrl' => null,
        ])->render();

        return response($html);
    }

    private function ensureBelongsToTemplate(ReceiptTemplate $receiptTemplate, ReceiptTemplateBlock $block): void
    {
        abort_unless($block->receipt_template_id === $receiptTemplate->id, 404);
    }

    private function deleteStoredImage(ReceiptTemplateBlock $block): void
    {
        $path = $block->config['image_path'] ?? null;
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
