<?php

namespace App\View\Components\Admin;

use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Top-bar store switcher. Shows the active store and lists the stores
 * the signed-in user can reach, POSTing to the switch route to change
 * the active store held in session. Renders nothing when the user can
 * reach no stores (fresh installs, users with no membership).
 */
class StoreSwitcher extends Component
{
    /** @var Collection<int, Store> */
    public Collection $stores;

    public ?Store $active;

    public bool $canManage;

    public function __construct()
    {
        $user = auth()->user();

        $this->stores    = $user ? $user->accessibleStores() : collect();
        $this->active    = current_store();
        $this->canManage = (bool) ($user?->can('viewAny', Store::class));
    }

    public function shouldRender(): bool
    {
        return $this->stores->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.admin.store-switcher');
    }
}
