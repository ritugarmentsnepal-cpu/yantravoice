@extends('layouts.admin')
@section('title', $user->name)

@section('content')
<div class="grid grid-cols-1 gap-6">
    <!-- User Info Card -->
    <div class="glass-panel rounded-xl p-6">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto rounded-full bg-indigo-600 flex items-center justify-center text-white text-2xl font-bold mb-3">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <h2 class="text-lg font-bold text-white">{{ $user->name }}</h2>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
            <span class="badge mt-2 inline-block {{ $user->role === 'admin' ? 'bg-purple-500/20 text-purple-400' : 'bg-slate-500/20 text-slate-400' }}">
                {{ ucfirst($user->role) }}
            </span>
        </div>

        <div class="space-y-3 border-t border-slate-700 pt-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Balance (Rs. )</span>
                <span class="text-emerald-400 font-mono font-bold">Rs. {{ number_format(\App\Models\ApiSetting::creditsToNpr($user->credits), 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Credits</span>
                <span class="text-white font-mono">{{ number_format($user->credits, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Status</span>
                <span class="{{ $user->is_active ? 'text-emerald-400' : 'text-red-400' }}">{{ $user->is_active ? 'Active' : 'Disabled' }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Generations</span>
                <span class="text-white">{{ $user->voiceoverLogs->count() }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400">Joined</span>
                <span class="text-slate-400">{{ $user->created_at->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Grant Credits Form (input in NPR) -->
        <div class="mt-6 pt-4 border-t border-slate-700">
            <h3 class="text-sm font-semibold text-white mb-3">💰 Grant Credits (in Rs. )</h3>
            <form method="POST" action="{{ route('admin.users.grant', $user) }}" class="space-y-3">
                @csrf
                <div>
                    <input type="number" name="npr_amount" step="1" min="1" placeholder="Amount in Rs.  (e.g., 100)" required class="input-dark w-full"
                           oninput="document.getElementById('creditPreview').textContent = this.value + ' credits'">
                    <p id="creditPreview" class="text-xs text-indigo-400 mt-1"></p>
                </div>
                <input type="text" name="description" placeholder="Reason (optional)" class="input-dark w-full">
                <button type="submit" class="btn-sm btn-emerald w-full">Grant Credits</button>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="mt-4 space-y-2">
            <form method="POST" action="{{ route('admin.users.toggle', $user) }}">
                @csrf
                <button class="btn-sm w-full {{ $user->is_active ? 'btn-red' : 'btn-emerald' }}">
                    {{ $user->is_active ? '🚫 Disable Account' : '✅ Enable Account' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.users.role', $user) }}">
                @csrf
                <input type="hidden" name="role" value="{{ $user->role === 'admin' ? 'user' : 'admin' }}">
                <button class="btn-sm btn-slate w-full">
                    {{ $user->role === 'admin' ? '👤 Demote to User' : '🛡️ Promote to Admin' }}
                </button>
            </form>
        </div>
    </div>

    <!-- Transactions -->
    <div class="glass-panel rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">💳 Credit Transactions</h3>
        <div class="overflow-x-auto">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-slate-400">Date</th>
                        <th class="px-3 py-2 text-left text-slate-400">Type</th>
                        <th class="px-3 py-2 text-left text-slate-400">Description</th>
                        <th class="px-3 py-2 text-right text-slate-400">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $tx->created_at->format('M d, H:i') }}</td>
                            <td class="px-3 py-2">
                                <span class="badge {{ match($tx->type) {
                                    'admin_grant' => 'bg-emerald-500/20 text-emerald-400',
                                    'generation_debit' => 'bg-amber-500/20 text-amber-400',
                                    'signup_bonus' => 'bg-indigo-500/20 text-indigo-400',
                                    'refund' => 'bg-blue-500/20 text-blue-400',
                                    default => 'bg-slate-500/20 text-slate-400'
                                } }}">{{ str_replace('_', ' ', ucfirst($tx->type)) }}</span>
                            </td>
                            <td class="px-3 py-2 text-slate-400 text-xs">{{ $tx->description }}</td>
                            <td class="px-3 py-2 text-right font-mono {{ $tx->amount > 0 ? 'text-emerald-400' : 'text-amber-400' }}">
                                {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount, 4) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No transactions</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
