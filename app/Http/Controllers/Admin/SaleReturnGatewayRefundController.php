<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sales\ReverseGatewayCharge;
use App\Http\Controllers\Controller;
use App\Models\SaleReturn;
use Illuminate\Http\JsonResponse;

class SaleReturnGatewayRefundController extends Controller
{
    /**
     * Manually re-attempt a failed gateway reversal for a refund. Gated by
     * the same `refund` ability as processing the refund itself.
     */
    public function retry(SaleReturn $saleReturn, ReverseGatewayCharge $reverse): JsonResponse
    {
        $this->authorize('refund', $saleReturn->sale);

        $reverse($saleReturn->fresh());

        $saleReturn->refresh();

        return response()->json([
            'ok'     => $saleReturn->gateway_refund_status === SaleReturn::GATEWAY_REFUND_SUCCEEDED,
            'status' => $saleReturn->gateway_refund_status,
        ]);
    }
}
