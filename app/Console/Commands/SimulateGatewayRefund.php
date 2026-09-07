<?php

namespace App\Console\Commands;

use App\Actions\Sales\RecordSaleReturn;
use App\Models\PaymentMethod;
use App\Models\ReturnReason;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\Stripe\StripeGateway;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Testing aid — proves the gateway refund reversal end-to-end WITHOUT a real
 * gateway account. It stamps a sale as if it were paid by Stripe, binds a fake
 * Stripe gateway (so no real API call happens), and runs a genuine 1-unit
 * refund to the original method. Open the printed sale URL to see the
 * "Reversed at gateway" (or, with --fail, "Gateway reversal failed" + Retry)
 * badge in the Refund history.
 *
 * ⚠ Creates a REAL refund (restocks 1 unit, marks the sale refunded). Run it
 * on a test/dev database.
 *
 *   php artisan pos:simulate-gateway-refund            # newest completed sale
 *   php artisan pos:simulate-gateway-refund 42         # a specific sale
 *   php artisan pos:simulate-gateway-refund --fail     # show the failed/retry path
 */
class SimulateGatewayRefund extends Command
{
    protected $signature = 'pos:simulate-gateway-refund {sale? : Sale id (defaults to the newest refundable sale)} {--fail : Simulate the gateway refund failing}';

    protected $description = 'Simulate a gateway refund reversal so the sale page badge is visible without real gateway keys';

    public function handle(RecordSaleReturn $recordReturn): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run on production — this creates a real refund.');

            return self::FAILURE;
        }

        $sale = $this->resolveSale();
        if (! $sale) {
            $this->error('No refundable sale found. Ring one up on the cashier first.');

            return self::FAILURE;
        }

        $item = $sale->items->first(fn (SaleItem $i) => bccomp((string) $i->quantity, (string) $i->quantity_returned, 4) > 0);
        if (! $item) {
            $this->error("Sale #{$sale->id} has nothing left to refund.");

            return self::FAILURE;
        }

        // 1. Make the sale look gateway-paid (Stripe).
        $payment = SalePayment::query()->where('sale_id', $sale->id)->first();
        if (! $payment) {
            $this->error("Sale #{$sale->id} has no payment rows.");

            return self::FAILURE;
        }
        $payment->forceFill([
            'gateway_provider'   => 'stripe',
            'gateway_payment_id' => 'pi_sim_'.$sale->id,
        ])->save();

        // 2. Bind a fake Stripe gateway so no real API call is made.
        $shouldFail = (bool) $this->option('fail');
        $this->laravel->bind(StripeGateway::class, fn () => $this->fakeGateway($shouldFail));

        // 3. Run a genuine 1-unit refund to the original (non-cash) method.
        $method  = $this->originalMethod($payment);
        $reason  = ReturnReason::query()->where('is_active', true)->first()
            ?? ReturnReason::create(['code' => 'sim', 'name' => 'Simulated', 'default_restock' => true, 'is_active' => true, 'sort_order' => 99]);
        $cashier = User::query()->where('is_super_admin', true)->first() ?? User::query()->firstOrFail();

        $return = $recordReturn([
            'sale_id'          => $sale->id,
            'reason_code_id'   => $reason->id,
            'refund_method_id' => $method->id,
            'restock'          => true,
            'items'            => [['sale_item_id' => $item->id, 'quantity' => '1']],
        ], $cashier);

        $return->refresh();

        $this->newLine();
        $this->info("Refund {$return->number} created on sale #{$sale->id}.");
        $this->line('  Gateway reversal status: <comment>'.($return->gateway_refund_status ?? 'n/a').'</comment>');
        $this->line('  Open the sale to see the badge:');
        $this->line('  <info>'.route('admin.sales.show', $sale).'</info>');

        return self::SUCCESS;
    }

    private function resolveSale(): ?Sale
    {
        $query = Sale::query()
            ->whereIn('status', [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_REFUNDED])
            ->with('items')
            ->has('items');

        if ($id = $this->argument('sale')) {
            return $query->find((int) $id);
        }

        return $query->latest('id')->first();
    }

    /** A non-cash, non-store-credit method so the refund buckets to "original method". */
    private function originalMethod(SalePayment $payment): PaymentMethod
    {
        $original = $payment->paymentMethod;
        if ($original && $original->type !== 'cash' && $original->code !== 'store_credit') {
            return $original;
        }

        return PaymentMethod::query()->where('type', '!=', 'cash')->where('code', '!=', 'store_credit')->where('is_active', true)->first()
            ?? PaymentMethod::create([
                'code' => 'card', 'name' => 'Card', 'type' => 'card', 'provider' => 'none',
                'requires_reference' => false, 'opens_cash_drawer' => false, 'is_active' => true,
            ]);
    }

    private function fakeGateway(bool $shouldFail): PaymentGateway
    {
        return new class($shouldFail) implements PaymentGateway {
            public function __construct(private bool $shouldFail) {}

            public function refund(SalePayment $payment, string $amount): bool
            {
                if ($this->shouldFail) {
                    throw new RuntimeException('Simulated gateway failure (--fail).');
                }

                return true;
            }

            public function code(): string { return 'stripe'; }
            public function label(): string { return 'Stripe (simulated)'; }
            public function createOrder(string $amount, Sale $sale): array { return []; }
            public function verify(SalePayment $payment): bool { return true; }
            public function startPayment(string $amountMinor, string $currency, array $context): array { throw new RuntimeException('not used'); }
            public function pollStatus(string $sessionId, PaymentMethod $method): array { throw new RuntimeException('not used'); }
            public function handleWebhook(Request $request, PaymentMethod $method): array { throw new RuntimeException('not used'); }
        };
    }
}
