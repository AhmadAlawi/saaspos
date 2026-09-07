<?php

namespace App\Hooks;

/**
 * WordPress-style action / filter dispatcher.
 *
 * The two concepts are kept distinct on purpose:
 *
 *   ACTIONS fire side-effects. Their return values are ignored.
 *     do_action('sale.after_create', $sale);
 *
 *   FILTERS transform a value. Each listener receives the previous
 *     listener's return value as the first argument.
 *     $html = apply_filters('receipt.html', $html, $sale);
 *
 * Listeners can be registered with a priority (lower runs first; ties
 * preserve registration order). Both APIs accept any PHP callable, so
 * plugin authors can use closures, [$obj, 'method'], or 'GlobalFn'.
 *
 * This class is bound as a singleton by {@see App\Providers\HookServiceProvider};
 * the global helpers (`do_action`, `apply_filters`, `add_action`,
 * `add_filter`) defined in `app/helpers.php` resolve it from the
 * container so plugin code reads exactly like WordPress.
 *
 * Naming convention for hook names:  `<domain>.<verb>_<phase>`
 *   - `category.before_create`  · runs before a new row is persisted
 *   - `category.after_create`   · runs after persistence
 *   - `category.fields`         · filter on the persisted attributes
 *   - `category.before_update`  · runs before the row is saved
 *   - `category.after_update`   · runs after save
 *   - `category.before_delete`  · before soft-delete
 *   - `category.after_delete`   · after soft-delete
 *   - `categories.reordered`    · after a batch reorder/parent-move
 */
class HookManager
{
    /** @var array<string, array<int, array<int, callable>>> */
    private array $actions = [];

    /** @var array<string, array<int, array<int, callable>>> */
    private array $filters = [];

    /* ── Registration ─────────────────────────────────────────── */

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    public function removeAction(string $hook, callable $callback): bool
    {
        return $this->removeCallback($this->actions, $hook, $callback);
    }

    public function removeFilter(string $hook, callable $callback): bool
    {
        return $this->removeCallback($this->filters, $hook, $callback);
    }

    public function hasAction(string $hook): bool { return !empty($this->actions[$hook]); }
    public function hasFilter(string $hook): bool { return !empty($this->filters[$hook]); }

    /* ── Dispatch ─────────────────────────────────────────────── */

    /**
     * Fire every listener registered for `$hook`, lowest priority first.
     * Return values are discarded — actions are for side-effects.
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        if (empty($this->actions[$hook])) {
            return;
        }
        foreach ($this->prioritised($this->actions[$hook]) as $callback) {
            $callback(...$args);
        }
    }

    /**
     * Pass `$value` through every listener registered for `$hook`,
     * lowest priority first. Each listener receives the running value
     * as the first argument plus any extra positional `$args`. The
     * final return value is what `apply_filters` returns.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty($this->filters[$hook])) {
            return $value;
        }
        foreach ($this->prioritised($this->filters[$hook]) as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }

    /* ── Internals ────────────────────────────────────────────── */

    /**
     * Walk a [priority => [callable, ...]] bucket in ascending priority,
     * preserving insertion order within the same priority.
     *
     * @param  array<int, array<int, callable>> $bucket
     * @return iterable<callable>
     */
    private function prioritised(array $bucket): iterable
    {
        ksort($bucket);
        foreach ($bucket as $callables) {
            foreach ($callables as $cb) {
                yield $cb;
            }
        }
    }

    /**
     * Locate and remove a single callable inside one of the registries.
     * Matches by identity for closures; uses callable equality for everything else.
     *
     * @param array<string, array<int, array<int, callable>>> $registry
     */
    private function removeCallback(array &$registry, string $hook, callable $callback): bool
    {
        if (empty($registry[$hook])) {
            return false;
        }
        foreach ($registry[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $idx => $existing) {
                if ($existing === $callback) {
                    unset($registry[$hook][$priority][$idx]);
                    if (empty($registry[$hook][$priority])) {
                        unset($registry[$hook][$priority]);
                    }
                    if (empty($registry[$hook])) {
                        unset($registry[$hook]);
                    }
                    return true;
                }
            }
        }
        return false;
    }
}
