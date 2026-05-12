<?php

namespace App\Http\Controllers;

use App\Models\CreditPurchase;
use App\Models\ApiSetting;
use Illuminate\Http\Request;

class CreditPurchaseController extends Controller
{
    /**
     * Submit a credit purchase request.
     */
    public function store(Request $request)
    {
        $request->validate([
            'package_amount' => 'required|integer|in:100,500,1000,5000,10000',
            'screenshot'     => 'required|image|max:5120', // 5MB max
        ]);

        $user = auth()->user();
        $packages = collect(CreditPurchase::packages());
        $pkg = $packages->firstWhere('amount', $request->package_amount);

        if (!$pkg) {
            return response()->json(['error' => 'Invalid package.'], 422);
        }

        // Check for existing pending request
        $pending = CreditPurchase::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();
        if ($pending >= 3) {
            return response()->json(['error' => 'You have too many pending requests. Please wait for approval.'], 422);
        }

        // Store screenshot
        $path = $request->file('screenshot')->store('purchase-screenshots', 'public');

        $purchase = CreditPurchase::create([
            'user_id'            => $user->id,
            'package_amount'     => $pkg['amount'],
            'bonus_amount'       => $pkg['bonus'],
            'total_credits'      => $pkg['total'],
            'payment_screenshot' => $path,
            'status'             => 'pending',
        ]);

        return response()->json([
            'message' => 'Purchase request submitted! You will receive credits once admin approves.',
            'purchase_id' => $purchase->id,
        ]);
    }

    /**
     * Get user's purchase history.
     */
    public function history()
    {
        $purchases = CreditPurchase::where('user_id', auth()->id())
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($p) {
                return [
                    'id'             => $p->id,
                    'package_amount' => $p->package_amount,
                    'bonus_amount'   => $p->bonus_amount,
                    'total_credits'  => $p->total_credits,
                    'status'         => $p->status,
                    'admin_note'     => $p->admin_note,
                    'created_at'     => $p->created_at->diffForHumans(),
                ];
            });

        return response()->json(['purchases' => $purchases]);
    }

    /**
     * Get the payment QR code URL.
     */
    public function qrCode()
    {
        $path = ApiSetting::getPaymentQrPath();
        if (!$path) {
            return response()->json(['error' => 'Payment QR not configured. Contact admin.'], 404);
        }
        return response()->json(['qr_url' => asset('storage/' . $path)]);
    }
}
