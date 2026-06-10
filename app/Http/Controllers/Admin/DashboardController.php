<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VoiceoverLog;
use App\Models\CreditTransaction;
use App\Models\AdminExpense;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Quick stats
        $stats = [
            'total_users'       => User::where('role', 'user')->count(),
            'active_users'      => User::where('role', 'user')->where('is_active', true)->count(),
            'total_generations' => VoiceoverLog::where('status', 'success')->count(),
            'failed_generations' => VoiceoverLog::where('status', 'failed')->count(),
            'total_credits_used' => CreditTransaction::where('type', 'generation_debit')->sum(DB::raw('ABS(amount)')),
            'total_credits_granted' => CreditTransaction::where('type', 'admin_grant')->sum('amount'),
            'total_api_cost'    => AdminExpense::where('category', 'api_cost')->where('is_auto', true)->sum('amount'),
            'total_expenses'    => AdminExpense::sum('amount'),
            'total_income'      => CreditTransaction::where('type', 'admin_grant')->sum('amount'), // credits sold = income
        ];

        // Recent activity (last 10)
        $recentGenerations = VoiceoverLog::with('user')
            ->latest()
            ->take(10)
            ->get();

        $recentTransactions = CreditTransaction::with('user')
            ->latest()
            ->take(10)
            ->get();

        // Chart data: Daily generations (last 30 days)
        $dailyGenerations = VoiceoverLog::where('status', 'success')
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Chart data: Daily API costs (last 30 days)
        $dailyCosts = AdminExpense::where('is_auto', true)
            ->where('expense_date', '>=', now()->subDays(30))
            ->select('expense_date as date', DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'recentGenerations', 'recentTransactions',
            'dailyGenerations', 'dailyCosts'
        ));
    }
}
