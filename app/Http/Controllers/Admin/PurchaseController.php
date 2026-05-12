<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditPurchase;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        
        $purchases = CreditPurchase::with('user', 'processor')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        $pendingCount = CreditPurchase::where('status', 'pending')->count();

        return view('admin.purchases.index', compact('purchases', 'status', 'pendingCount'));
    }

    public function approve($id)
    {
        $purchase = CreditPurchase::findOrFail($id);
        
        if ($purchase->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $user = $purchase->user;

        // Grant credits
        $user->addCredits($purchase->total_credits);

        // Log the transaction
        CreditTransaction::create([
            'user_id'     => $user->id,
            'amount'      => $purchase->total_credits,
            'type'        => 'admin_grant',
            'description' => "Credit purchase: ₨{$purchase->package_amount}" . 
                           ($purchase->bonus_amount > 0 ? " + {$purchase->bonus_amount} bonus" : ''),
            'admin_id'    => auth()->id(),
        ]);

        // Update purchase status
        $purchase->update([
            'status'       => 'approved',
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', "✅ Approved! {$purchase->total_credits} credits added to {$user->name}.");
    }

    public function reject(Request $request, $id)
    {
        $purchase = CreditPurchase::findOrFail($id);
        
        if ($purchase->status !== 'pending') {
            return back()->with('error', 'This request has already been processed.');
        }

        $purchase->update([
            'status'       => 'rejected',
            'admin_note'   => $request->input('note', 'Payment could not be verified.'),
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', "Request rejected for {$purchase->user->name}.");
    }
}
