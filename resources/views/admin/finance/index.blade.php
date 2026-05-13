@extends('layouts.admin')
@section('title', 'Finance')

@section('content')
<!-- Period Filter -->
<div class="flex overflow-x-auto no-scrollbar gap-2 mb-5 -mx-5 px-5">
    @foreach(['7' => '7 days', '30' => '30 days', '90' => '90 days', 'all' => 'All time'] as $val => $label)
        <a href="?period={{ $val }}" class="shrink-0 px-5 py-2 text-[13px] font-semibold rounded-full transition-all {{ $period == $val ? 'bg-[#1B2438] text-white' : 'bg-white text-[#64748B] border border-[#E8EDF2]' }}">{{ $label }}</a>
    @endforeach
</div>

<!-- Financial Summary -->
<div class="grid grid-cols-2 gap-3 mb-5">
    <div class="stat-card">
        <div class="text-[10px] text-[#94A3B8] font-medium mb-1">💰 Revenue</div>
        <div class="text-lg font-extrabold text-green-600">Rs. {{ number_format($totalRevenueNpr, 0) }}</div>
        <div class="text-[10px] text-[#C0C9D6]">{{ number_format($totalCreditsUsed, 0) }} cr used</div>
    </div>
    <div class="stat-card">
        <div class="text-[10px] text-[#94A3B8] font-medium mb-1">💸 API Costs</div>
        <div class="text-lg font-extrabold text-amber-600">${{ number_format($apiExpensesUsd, 4) }}</div>
        <div class="text-[10px] text-[#C0C9D6]">OpenRouter</div>
    </div>
    <div class="stat-card">
        <div class="text-[10px] text-[#94A3B8] font-medium mb-1">🏠 Other Expenses</div>
        <div class="text-lg font-extrabold text-red-500">Rs. {{ number_format($otherExpensesNpr, 0) }}</div>
        <div class="text-[10px] text-[#C0C9D6]">Server, domain, etc.</div>
    </div>
    <div class="stat-card">
        <div class="text-[10px] text-[#94A3B8] font-medium mb-1">📊 Credits Granted</div>
        <div class="text-lg font-extrabold text-blue-600">Rs. {{ number_format($totalIncomeNpr, 0) }}</div>
        <div class="text-[10px] text-[#C0C9D6]">{{ number_format($totalCreditsGranted, 0) }} cr issued</div>
    </div>
</div>

<!-- Chart -->
<div class="glass-panel p-5 mb-5">
    <h3 class="text-xs font-bold text-[#1B2438] mb-3">📈 Daily API Costs (USD)</h3>
    <canvas id="costChart" height="160"></canvas>
</div>

<!-- Record Manual Expense -->
<div class="glass-panel p-5 mb-5">
    <h3 class="text-xs font-bold text-[#1B2438] mb-4">✨ Record Expense</h3>
    <form method="POST" action="{{ route('admin.finance.expense.store') }}" class="space-y-3">
        @csrf
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Category</label>
                <select name="category" class="input-field w-full">
                    <option value="server">Server</option>
                    <option value="domain">Domain</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Amount (Rs. )</label>
                <input type="number" name="amount" step="0.01" min="0.01" class="input-field w-full" required placeholder="0.00">
            </div>
        </div>
        <div>
            <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Description</label>
            <input type="text" name="description" class="input-field w-full" required placeholder="What was this for?">
        </div>
        <div>
            <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Date</label>
            <input type="date" name="expense_date" value="{{ now()->toDateString() }}" class="input-field w-full" required>
        </div>
        <button type="submit" class="btn-sm btn-navy w-full py-2.5">Add Expense</button>
    </form>
</div>

<!-- Manual Expenses List -->
@if($manualExpenses->count())
<div class="glass-panel p-5">
    <h3 class="text-xs font-bold text-[#1B2438] mb-3">📋 Manual Expenses</h3>
    <div class="space-y-2.5">
        @foreach($manualExpenses as $exp)
            <div class="flex items-center justify-between p-3 rounded-xl bg-[#F5F7FA]">
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-[#1B2438] truncate">{{ $exp->description }}</div>
                    <div class="text-[10px] text-[#94A3B8]">{{ ucfirst($exp->category) }} · {{ $exp->expense_date }}</div>
                </div>
                <div class="flex items-center gap-3 shrink-0 ml-2">
                    <span class="text-xs font-bold text-red-500">{{ $exp->currency }} {{ number_format($exp->amount, 2) }}</span>
                    <form method="POST" action="{{ route('admin.finance.expense.destroy', $exp) }}" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="text-red-400 hover:text-red-600 text-[10px] font-bold">✕</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-4">{{ $manualExpenses->withQueryString()->links() }}</div>
</div>
@endif

<script>
const costCtx = document.getElementById('costChart').getContext('2d');
new Chart(costCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($dailyData->pluck('date')) !!},
        datasets: [{
            label: 'API Cost (USD)',
            data: {!! json_encode($dailyData->pluck('expense')) !!},
            borderColor: '#F97316',
            backgroundColor: 'rgba(249, 115, 22, 0.08)',
            fill: true, tension: 0.4, pointRadius: 3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#94A3B8', font: { size: 8 } }, grid: { display: false } },
            y: { ticks: { color: '#94A3B8', font: { size: 9 }, callback: v => '$' + v.toFixed(4) }, grid: { color: '#F0F4F8' }, beginAtZero: true }
        }
    }
});
</script>
@endsection
