<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop's saved free-positioned design for one `config('labels.layouts')`
 * key (e.g. `roll-60x40`) — the web counterpart to the mobile app's
 * per-label-spec Label Layout Designer. Existence is opt-in per layout key:
 * {@see LabelController::sheet()} only takes the positioned-element render
 * path when a row is present; otherwise it falls back to the original
 * fixed-stack markup untouched.
 */
class LabelLayout extends Model
{
    use Auditable;

    protected $fillable = ['layout_key', 'label_w_mm', 'label_h_mm'];

    protected function casts(): array
    {
        return [
            'label_w_mm' => 'decimal:2',
            'label_h_mm' => 'decimal:2',
        ];
    }

    /** @return HasMany<LabelLayoutElement, $this> */
    public function elements(): HasMany
    {
        return $this->hasMany(LabelLayoutElement::class);
    }

    /**
     * The label's real width/height in mm — this shop's saved override
     * when set, else `config('labels.layouts')[$layoutKey]`'s stock size
     * for the layout. Merged onto a copy of the config array so callers
     * that already destructure `$layout['label_w_mm']` etc. (the print
     * sheet, the designer, the preview) need no shape change, just this
     * merged array instead of the raw config one.
     *
     * @param array<string, mixed> $configLayout
     * @return array<string, mixed>
     */
    public function effectiveLayout(array $configLayout): array
    {
        return array_merge($configLayout, array_filter([
            'label_w_mm' => $this->label_w_mm !== null ? (float) $this->label_w_mm : null,
            'label_h_mm' => $this->label_h_mm !== null ? (float) $this->label_h_mm : null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Get-or-seed entry point for both the designer and the print path.
     * A newly created layout gets exactly 5 elements, positioned to
     * visually approximate the original centered vertical stack (name,
     * price, sku, barcode) plus a logo in the mobile app's own default
     * top-right spot, off by default.
     */
    public static function firstOrCreateForKey(string $layoutKey): self
    {
        $layout = static::query()->where('layout_key', $layoutKey)->first();
        if ($layout) {
            return $layout;
        }

        $layout = static::create(['layout_key' => $layoutKey]);

        $defaults = [
            ['type' => 'name', 'x_pct' => 5, 'y_pct' => 8, 'width_pct' => 90, 'font_size' => 9, 'font_family' => 'sans', 'align' => 'center', 'is_visible' => true],
            ['type' => 'price', 'x_pct' => 5, 'y_pct' => 34, 'width_pct' => 90, 'font_size' => 13, 'font_family' => 'sans', 'align' => 'center', 'is_visible' => true],
            ['type' => 'sku', 'x_pct' => 5, 'y_pct' => 54, 'width_pct' => 90, 'font_size' => 8, 'font_family' => 'mono', 'align' => 'center', 'is_visible' => true],
            ['type' => 'barcode', 'x_pct' => 5, 'y_pct' => 68, 'width_pct' => 90, 'scale' => 1.00, 'align' => 'center', 'is_visible' => true],
            ['type' => 'image', 'x_pct' => 62, 'y_pct' => 4, 'scale' => 0.20, 'align' => 'left', 'is_visible' => false],
        ];

        foreach ($defaults as $el) {
            $layout->elements()->create($el);
        }

        return $layout->load('elements');
    }
}
