@extends('layouts.admin')
@section('title', 'Transactions')

@section('content')
<!-- Filters -->
<div class="glass-panel p-4 mb-5">
    <form method="GET" class="space-y-3">
        <div class="flex gap-2">
            <select name="type" class="input-field flex-1">
                <option value="">All Types</option>
                <option value="admin_grant" {{ request('type') === 'admin_grant' ? 'selected' : '' }}>Admin Grant</option>
                <option value="generation_debit" {{ request('type') === 'generation_debit' ? 'selected' : '' }}>Gen Debit</option>
                <option value="signup_bonus" {{ request('type') === 'signup_bonus' ? 'selected' : '' }}>Signup Bonus</option>
                <option value="refund" {{ request('type') === 'refund' ? 'selected' : '' }}>Refund</option>
            </select>
            <button type="submit" class="btn-sm btn-navy">Filter</button>
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="input-field w-full">
            </div>
            <div class="flex-1">
                <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="input-field w-full">
            </div>
        </div>
    </form>
</div>

<!-- Transaction Cards -->
<div class="space-y-2.5">
    @forelse($transactions as $tx)
        <div class="glass-panel p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full {{ $tx->amount > 0 ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600' }} flex items-center justify-center text-sm font-bold shrink-0">
                        {{ $tx->amount > 0 ? '+' : '−' }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-[#1B2438] truncate">{{ $tx->user?->name ?? 'Deleted' }}</div>
                        <div class="text-[10px] text-[#94A3B8] truncate">{{ Str::limit($tx->description, 30) }}</div>
                    </div>
                </div>
                <div class="text-right shrink-0 ml-2">
                    <div class="text-sm font-extrabold {{ $tx->amount > 0 ? 'text-green-600' : 'text-amber-600' }}">
                        {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount, 0) }} cr
                    </div>
                    <span class="badge {{ match($tx->type) {
                        'admin_grant' => 'bg-green-50 text-green-500',
                        'generation_debit' => 'bg-amber-50 text-amber-500',
                        'signup_bonus' => 'bg-blue-50 text-blue-500',
                        'refund' => 'bg-purple-50 text-purple-500',
                        default => 'bg-[#F0F4F8] text-[#64748B]'
                    } }}">{{ str_replace('_', ' ', ucfirst($tx->type)) }}</span>
                </div>
            </div>
            <div class="flex items-center justify-between mt-2 pt-2 border-t border-[#F0F4F8]">
                <span class="text-[10px] text-[#C0C9D6]">{{ $tx->created_at->format('M d, Y H:i') }}</span>
                @if($tx->admin)
                    <span class="text-[10px] text-[#94A3B8]">by {{ $tx->admin->name }}</span>
                @endif
            </div>
        </div>
    @empty
        <div class="glass-panel p-8 text-center">
            <p class="text-[#94A3B8] text-sm">No transactions found</p>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $transactions->withQueryString()->links() }}</div>
@endsection
