{{-- Self-contained mini-mockup of a thermal sale receipt. Bound to the
     receiptSettings() Alpine state on its ancestor: width follows the
     selected paper size; each section shows/hides per its toggle. --}}
<div class="rcpt-frame" :style="previewStyle">
    <div class="rcpt">
        <template x-if="showLogo">
            <div class="rcpt-logo">{{ config('app.name') }}</div>
        </template>

        <div class="rcpt-header" x-show="header.trim().length" x-text="header"></div>

        <div class="rcpt-meta">
            <div>#R-1042</div>
            <div>{{ format_datetime(now()) }}</div>
        </div>

        <template x-if="showCustomer">
            <div class="rcpt-line">Customer: Anil Patel · 98xxxxxx20</div>
        </template>
        <template x-if="showCashier">
            <div class="rcpt-line">Cashier: Ramesh</div>
        </template>

        <div class="rcpt-rule"></div>

        <div class="rcpt-items">
            <div class="rcpt-item">
                <span class="rcpt-item-name">Basmati Rice 1kg</span>
                <template x-if="showSku"><span class="rcpt-item-code">SKU: RICE-1KG</span></template>
                <template x-if="showHsn"><span class="rcpt-item-code">HSN: 1006</span></template>
                <span class="rcpt-item-qty">2 × 120.00</span>
                <span class="rcpt-item-total">{{ format_money(240) }}</span>
            </div>
            <div class="rcpt-item">
                <span class="rcpt-item-name">Sunflower Oil 1L</span>
                <template x-if="showSku"><span class="rcpt-item-code">SKU: OIL-1L</span></template>
                <template x-if="showHsn"><span class="rcpt-item-code">HSN: 1512</span></template>
                <span class="rcpt-item-qty">1 × 175.00</span>
                <span class="rcpt-item-total">{{ format_money(175) }}</span>
            </div>
            <div class="rcpt-item">
                <span class="rcpt-item-name">Toothpaste 100g</span>
                <template x-if="showSku"><span class="rcpt-item-code">SKU: TP-100</span></template>
                <template x-if="showHsn"><span class="rcpt-item-code">HSN: 3306</span></template>
                <span class="rcpt-item-qty">3 × 65.00</span>
                <span class="rcpt-item-total">{{ format_money(195) }}</span>
            </div>
        </div>

        <div class="rcpt-rule"></div>

        <div class="rcpt-totals">
            <div><span>Subtotal</span><span>{{ format_money(610) }}</span></div>
            <template x-if="showTaxBreakdown">
                <div class="rcpt-tax">
                    <div><span>CGST 2.5%</span><span>{{ format_money(15.25) }}</span></div>
                    <div><span>SGST 2.5%</span><span>{{ format_money(15.25) }}</span></div>
                </div>
            </template>
            <div class="rcpt-total"><span>Total</span><span>{{ format_money(640.50) }}</span></div>
            <div><span>Cash</span><span>{{ format_money(700) }}</span></div>
            <div><span>Change</span><span>{{ format_money(59.50) }}</span></div>
        </div>

        <template x-if="showHsnSummary">
            <div>
                <div class="rcpt-rule"></div>
                <div class="rcpt-hsn">
                    <div class="rcpt-hsn-title">HSN summary</div>
                    <div class="rcpt-hsn-head"><span>HSN</span><span>Taxable</span><span>Tax</span></div>
                    <div class="rcpt-hsn-row"><span>1006</span><span>{{ format_money(240) }}</span><span>{{ format_money(12) }}</span></div>
                    <div class="rcpt-hsn-comp"><span>CGST 2.5%</span><span>{{ format_money(6) }}</span></div>
                    <div class="rcpt-hsn-comp"><span>SGST 2.5%</span><span>{{ format_money(6) }}</span></div>
                    <div class="rcpt-hsn-row"><span>1512</span><span>{{ format_money(175) }}</span><span>{{ format_money(8.75) }}</span></div>
                    <div class="rcpt-hsn-comp"><span>CGST 2.5%</span><span>{{ format_money(4.38) }}</span></div>
                    <div class="rcpt-hsn-comp"><span>SGST 2.5%</span><span>{{ format_money(4.38) }}</span></div>
                </div>
            </div>
        </template>

        <div class="rcpt-rule"></div>

        <div class="rcpt-footer" x-show="footer.trim().length" x-text="footer"></div>

        <template x-if="showBarcode">
            <div class="rcpt-barcode">||||| || ||| ||| | ||||| | || |||</div>
        </template>
        <template x-if="showQr">
            <div class="rcpt-qr">[ QR ]</div>
        </template>

        <div class="rcpt-return" x-show="returnPolicy.trim().length" x-text="returnPolicy"></div>
    </div>
</div>
