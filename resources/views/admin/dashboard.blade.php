@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<!-- Stats Grid -->
<div class="grid grid-cols-2 gap-3 mb-5">
    <div class="stat-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-lg">👥</span>
            <span class="badge bg-blue-50 text-blue-500">Users</span>
        </div>
        <div class="text-xl font-extrabold text-[#1B2438]">{{ number_format($stats['total_users']) }}</div>
        <div class="text-[10px] text-[#94A3B8] mt-0.5 font-medium">{{ $stats['active_users'] }} active</div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-lg">🎙️</span>
            <span class="badge bg-green-50 text-green-500">Gens</span>
        </div>
        <div class="text-xl font-extrabold text-[#1B2438]">{{ number_format($stats['total_generations']) }}</div>
        <div class="text-[10px] text-[#94A3B8] mt-0.5 font-medium">{{ $stats['failed_generations'] }} failed</div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-lg">💳</span>
            <span class="badge bg-amber-50 text-amber-500">Revenue</span>
        </div>
        <div class="text-lg font-extrabold text-[#1B2438]">Rs. {{ number_format(\App\Models\ApiSetting::creditsToNpr($stats['total_credits_used']), 0) }}</div>
        <div class="text-[10px] text-[#94A3B8] mt-0.5 font-medium">{{ number_format($stats['total_credits_used'], 0) }} cr used</div>
    </div>

    <div class="stat-card">
        <div class="flex items-center justify-between mb-2">
            <span class="text-lg">💰</span>
            <span class="badge bg-red-50 text-red-500">API Cost</span>
        </div>
        <div class="text-lg font-extrabold text-[#1B2438]">${{ number_format($stats['total_api_cost'], 4) }}</div>
        <div class="text-[10px] text-[#94A3B8] mt-0.5 font-medium">${{ number_format($stats['total_expenses'], 4) }} total</div>
    </div>
</div>

<!-- Charts -->
<div class="space-y-4 mb-5">
    <div class="glass-panel p-5">
        <h3 class="text-xs font-bold text-[#1B2438] mb-3">📈 Daily Generations (30d)</h3>
        <canvas id="generationsChart" height="160"></canvas>
    </div>
    <div class="glass-panel p-5">
        <h3 class="text-xs font-bold text-[#1B2438] mb-3">💸 Daily API Costs (30d)</h3>
        <canvas id="costsChart" height="160"></canvas>
    </div>
</div>

<!-- Recent Activity -->
<div class="space-y-4">
    <div class="glass-panel p-5">
        <h3 class="text-xs font-bold text-[#1B2438] mb-3">🎙️ Recent Generations</h3>
        <div class="space-y-2.5">
            @forelse($recentGenerations as $gen)
                <div class="flex items-center justify-between p-3 rounded-xl bg-[#F5F7FA]">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full {{ $gen->status === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500' }} flex items-center justify-center text-xs font-bold shrink-0">
                            {{ $gen->status === 'success' ? '✓' : '✗' }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-[#1B2438] truncate">{{ $gen->user?->name ?? 'Unknown' }}</div>
                            <div class="text-[10px] text-[#94A3B8] truncate">{{ Str::limit($gen->input_text, 30) }}</div>
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-2">
                        <div class="text-[10px] text-[#64748B] font-medium">{{ $gen->voice_model }}</div>
                        <div class="text-[10px] text-[#C0C9D6]">{{ $gen->created_at->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <p class="text-[#94A3B8] text-xs text-center py-4">No generations yet</p>
            @endforelse
        </div>
    </div>

    <div class="glass-panel p-5">
        <h3 class="text-xs font-bold text-[#1B2438] mb-3">💳 Recent Transactions</h3>
        <div class="space-y-2.5">
            @forelse($recentTransactions as $tx)
                <div class="flex items-center justify-between p-3 rounded-xl bg-[#F5F7FA]">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full {{ $tx->amount > 0 ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600' }} flex items-center justify-center text-xs font-bold shrink-0">
                            {{ $tx->amount > 0 ? '+' : '-' }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-[#1B2438] truncate">{{ $tx->user?->name ?? 'Unknown' }}</div>
                            <div class="text-[10px] text-[#94A3B8] truncate">{{ Str::limit($tx->description, 25) }}</div>
                        </div>
                    </div>
                    <div class="text-right shrink-0 ml-2">
                        <div class="text-xs font-bold {{ $tx->amount > 0 ? 'text-green-600' : 'text-amber-600' }}">
                            {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount, 0) }} cr
                        </div>
                        <div class="text-[10px] text-[#C0C9D6]">{{ $tx->created_at->diffForHumans() }}</div>
                    </div>
                </div>
            @empty
                <p class="text-[#94A3B8] text-xs text-center py-4">No transactions yet</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    const genCtx = document.getElementById('generationsChart').getContext('2d');
    new Chart(genCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($dailyGenerations->pluck('date')) !!},
            datasets: [{
                label: 'Generations',
                data: {!! json_encode($dailyGenerations->pluck('count')) !!},
                backgroundColor: 'rgba(27, 36, 56, 0.7)',
                borderColor: '#1B2438',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#94A3B8', font: { size: 8 } }, grid: { display: false } },
                y: { ticks: { color: '#94A3B8', font: { size: 9 } }, grid: { color: '#F0F4F8' }, beginAtZero: true }
            }
        }
    });

    const costCtx = document.getElementById('costsChart').getContext('2d');
    new Chart(costCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($dailyCosts->pluck('date')) !!},
            datasets: [{
                label: 'API Cost ($)',
                data: {!! json_encode($dailyCosts->pluck('total')) !!},
                borderColor: '#F97316',
                backgroundColor: 'rgba(249, 115, 22, 0.08)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
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
