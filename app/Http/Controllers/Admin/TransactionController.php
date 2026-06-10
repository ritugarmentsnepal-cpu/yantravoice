<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = CreditTransaction::with(['user', 'admin']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $transactions = $query->latest()->paginate(30);

        return view('admin.transactions.index', compact('transactions'));
    }
}
