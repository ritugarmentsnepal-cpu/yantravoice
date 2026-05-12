<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminExpense;
use App\Models\ApiSetting;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', '30'); // days

        // Income: credits granted to users (admin_grant transactions = credits sold)
        // Income in NPR = credits granted / 10
        $totalCreditsGranted = CreditTransaction::where('type', 'admin_grant')
            ->when($period !== 'all', fn($q) => $q->where('created_at', '>=', now()->subDays($period)))
            ->sum('amount');
        $totalIncomeNpr = ApiSetting::creditsToNpr($totalCreditsGranted);

        // Revenue from generations: credits used
        $totalCreditsUsed = CreditTransaction::where('type', 'generation_debit')
            ->when($period !== 'all', fn($q) => $q->where('created_at', '>=', now()->subDays($period)))
            ->sum(DB::raw('ABS(amount)'));
        $totalRevenueNpr = ApiSetting::creditsToNpr($totalCreditsUsed);

        // Expenses (API costs in USD)
        $apiExpensesUsd = AdminExpense::where('category', 'api_cost')
            ->when($period !== 'all', fn($q) => $q->where('expense_date', '>=', now()->subDays($period)))
            ->sum('amount');

        // Other expenses (stored in NPR)
        $otherExpensesNpr = AdminExpense::where('category', '!=', 'api_cost')
            ->when($period !== 'all', fn($q) => $q->where('expense_date', '>=', now()->subDays($period)))
            ->sum('amount');

        // Manual expenses list
        $manualExpenses = AdminExpense::where('is_auto', false)
            ->latest('expense_date')
            ->paginate(20);

        // Daily breakdown for chart
        $dailyData = AdminExpense::where('is_auto', true)
            ->when($period !== 'all', fn($q) => $q->where('expense_date', '>=', now()->subDays($period)))
            ->select('expense_date as date', DB::raw('SUM(amount) as expense'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.finance.index', compact(
            'totalIncomeNpr', 'totalRevenueNpr', 'totalCreditsGranted', 'totalCreditsUsed',
            'apiExpensesUsd', 'otherExpensesNpr',
            'manualExpenses', 'dailyData', 'period'
        ));
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'category'     => 'required|in:server,domain,other',
            'amount'       => 'required|numeric|min:0.01',
            'description'  => 'required|string|max:255',
            'expense_date' => 'required|date',
        ]);

        AdminExpense::create([
            ...$validated,
            'admin_id' => auth()->id(),
            'currency' => 'NPR',
            'is_auto'  => false,
        ]);

        return back()->with('success', 'Expense recorded successfully');
    }

    public function destroyExpense(AdminExpense $expense)
    {
        if ($expense->is_auto) {
            return back()->with('error', 'Cannot delete auto-generated API costs');
        }

        $expense->delete();
        return back()->with('success', 'Expense deleted');
    }
}
