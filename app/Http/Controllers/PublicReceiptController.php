<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ReceiptPublicLink;
use App\Models\Sale;
use Illuminate\Contracts\View\View;

/**
 * The no-login public receipt viewer: `GET /r/{token}`.
 *
 * A customer scans the CFD thank-you QR (or taps a WhatsApp / email link)
 * and lands here on their own phone — no account, no admin chrome. The
 * opaque token IS the authorisation; an unknown or expired token 404s so a
 * link can't be enumerated or outlive its window. Renders the SAME receipt
 * blade the till prints, with contact details masked (docs §11.2).
 */
class PublicReceiptController extends Controller
{
    public function show(string $token): View
    {
        $link = ReceiptPublicLink::query()->where('token', $token)->first();

        // Unknown token, expired link, or a reference we can't render as a
        // public receipt → the same 404 (never leak which case it was).
        abort_if($link === null || $link->isExpired(), 404);

        $sale = $this->resolveSale($link);
        abort_if($sale === null, 404);

        $link->markViewed();

        $sale->load([
            'store:id,name,code,address_line1,address_line2,city,state,postal_code,phone',
            'customer:id,name,phone,email',
            'cashier:id,name',
            'items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'items.product:id,sku,name,hsn_code,unit_id,type',
            'items.product.unit:id,code',
            'items.product.kitItems.component:id,sku,name,unit_id',
            'items.product.kitItems.component.unit:id,code',
            'items.product.kitItems.variant:id,sku,attributes',
            'items.variant:id,sku,attributes',
            'items.batch:id,batch_number,expiry_date',
            'payments.paymentMethod:id,name,type',
        ]);

        $company = Company::current() ?? new Company();

        return view('sales.receipt', [
            'sale'    => $sale,
            'company' => $company,
            'paper'   => $company->receipt_paper_size ?: '80mm',
            'auto'    => false,
            // Masks customer contact details + hides the admin "Back" tool.
            'public'  => true,
        ]);
    }

    /**
     * Resolve the link's reference to a renderable sale. Only completed
     * sales are shown publicly — a draft/held/voided document shouldn't be
     * reachable by link. Returns null for any other reference type.
     */
    private function resolveSale(ReceiptPublicLink $link): ?Sale
    {
        if ($link->reference_type !== (new Sale())->getMorphClass()) {
            return null;
        }

        $sale = $link->reference()->first();

        return ($sale instanceof Sale && $sale->isCompleted()) ? $sale : null;
    }
}
