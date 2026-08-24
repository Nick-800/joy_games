<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function addItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'game_session_id' => ['nullable', 'exists:game_sessions,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $shift = Shift::active();

        $subtotal = $product->price_millimes * $validated['quantity'];

        OrderItem::create([
            'game_session_id' => $validated['game_session_id'] ?? null,
            'shift_id' => $shift?->id,
            'product_id' => $product->id,
            'item_name' => $product->name,
            'unit_price_millimes' => $product->price_millimes,
            'quantity' => $validated['quantity'],
            'subtotal_millimes' => $subtotal,
        ]);

        // Decrement stock if positive
        if ($product->stock_quantity >= $validated['quantity']) {
            $product->decrement('stock_quantity', $validated['quantity']);
        }

        return back()->with('success', "Added {$validated['quantity']}x {$product->name}");
    }

    public function removeItem(OrderItem $orderItem): RedirectResponse
    {
        // Restore stock
        $orderItem->product?->increment('stock_quantity', $orderItem->quantity);
        $orderItem->delete();

        return back()->with('success', "Removed {$orderItem->item_name}");
    }
}
