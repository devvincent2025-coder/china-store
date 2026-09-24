<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, int $orderItem): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $orderItem = OrderItem::with('vendorOrder.order')->findOrFail($orderItem);

        // Reviews are only allowed on the buyer's own delivered order items — enforced here rather
        // than a policy since a review is created from an order item, not directly on an existing model.
        // This is also what makes every review here inherently a "verified purchase" one — there is
        // no path to leaving a review without actually buying and receiving the product (see
        // reviews.order_item_id's NOT NULL + UNIQUE constraint, which the check below mirrors with
        // a friendly message instead of a raw duplicate-key exception).
        abort_unless($orderItem->vendorOrder->order->user_id === $request->user()->id, 403);
        abort_unless($orderItem->vendorOrder->fulfillment_status === 'delivered', 422, 'You can only review delivered orders.');
        abort_if($orderItem->review()->exists(), 422, 'You have already reviewed this item.');

        $orderItem->review()->create([
            'user_id' => $request->user()->id,
            'product_id' => $orderItem->product_id,
            'vendor_id' => $orderItem->vendorOrder->vendor_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return back()->with('success', 'Thanks for your review!');
    }
}
