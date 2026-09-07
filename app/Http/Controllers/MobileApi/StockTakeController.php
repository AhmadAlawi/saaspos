<?php

namespace App\Http\Controllers\MobileApi;

use App\Actions\Inventory\CreateStockTake;
use App\Actions\Inventory\PostStockTake;
use App\Actions\Inventory\UpdateStockTake;
use App\Models\StockTake;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Stock-take (جرد) endpoints for the mobile app. Thin HTTP wrapper —
 * all business logic stays in the existing app/Actions/Inventory
 * classes and StockTakePolicy, exactly as the admin web UI uses them.
 */
class StockTakeController
{
    private function storeId(Request $request): int
    {
        return (int) $request->attributes->get('mobile_api_store_id');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user->can('viewAny', StockTake::class)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = StockTake::where('store_id', $this->storeId($request))
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->get(['id', 'number', 'name', 'take_date', 'status', 'created_at']));
    }

    public function show(Request $request, StockTake $stockTake)
    {
        if ($stockTake->store_id !== $this->storeId($request)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! $request->user()->can('view', $stockTake)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $stockTake->load('items.product', 'items.variant');

        return response()->json([
            'id' => $stockTake->id,
            'number' => $stockTake->number,
            'name' => $stockTake->name,
            'status' => $stockTake->status,
            'take_date' => $stockTake->take_date,
            'items' => $stockTake->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'variant_id' => $i->variant_id,
                'name' => $i->variant?->label ?? $i->product?->name,
                'barcode' => $i->variant?->barcode ?? $i->product?->barcode,
                'expected_quantity' => $i->expected_quantity,
                'counted_quantity' => $i->counted_quantity,
                'notes' => $i->notes,
            ]),
        ]);
    }

    public function store(Request $request, CreateStockTake $action)
    {
        $user = $request->user();
        if (! $user->can('create', StockTake::class)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'take_date' => ['required', 'date'],
            'name'      => ['nullable', 'string', 'max:255'],
            'notes'     => ['nullable', 'string'],
        ]);
        $data['store_id'] = $this->storeId($request);

        try {
            $stockTake = $action($data, $user);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $stockTake->id, 'number' => $stockTake->number], 201);
    }

    public function update(Request $request, StockTake $stockTake, UpdateStockTake $action)
    {
        if ($stockTake->store_id !== $this->storeId($request)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $user = $request->user();
        if (! $user->can('update', $stockTake)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'name'  => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required_with:items', 'integer'],
            'items.*.counted_quantity' => ['nullable'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        try {
            $stockTake = $action($stockTake, $data, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $stockTake->id, 'status' => $stockTake->status]);
    }

    public function post(Request $request, StockTake $stockTake, PostStockTake $action)
    {
        if ($stockTake->store_id !== $this->storeId($request)) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $user = $request->user();
        if (! $user->can('post', $stockTake)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $stockTake = $action($stockTake, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['id' => $stockTake->id, 'status' => $stockTake->status, 'posted_at' => $stockTake->posted_at]);
    }
}
