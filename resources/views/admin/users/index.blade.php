@extends('layouts.admin')
@section('title', 'Users')

@section('content')
<!-- Search -->
<div class="glass-panel p-4 mb-5">
    <form method="GET" class="flex gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..." class="input-field flex-1">
        <select name="role" class="input-field w-24">
            <option value="">All</option>
            <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>User</option>
            <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
        </select>
        <button type="submit" class="btn-sm btn-navy">Go</button>
    </form>
</div>

<!-- User Cards -->
<div class="space-y-3">
    @forelse($users as $u)
        <div class="glass-panel p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full bg-[#1B2438] flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ strtoupper(substr($u->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-[#1B2438] truncate flex items-center gap-1.5">
                            {{ $u->name }}
                            <span class="inline-block w-2 h-2 rounded-full {{ $u->is_active ? 'bg-green-400' : 'bg-red-400' }}"></span>
                        </div>
                        <div class="text-[11px] text-[#94A3B8] truncate">{{ $u->email }}</div>
                    </div>
                </div>
                <span class="badge {{ $u->role === 'admin' ? 'bg-purple-50 text-purple-500' : 'bg-[#F0F4F8] text-[#64748B]' }}">{{ ucfirst($u->role) }}</span>
            </div>

            <div class="flex items-center justify-between mt-3 pt-3 border-t border-[#F0F4F8]">
                <div class="flex gap-4">
                    <div>
                        <div class="text-[10px] text-[#94A3B8] font-medium">Balance</div>
                        <div class="text-xs font-bold text-green-600">₨{{ number_format(\App\Models\ApiSetting::creditsToNpr($u->credits), 0) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-[#94A3B8] font-medium">Gens</div>
                        <div class="text-xs font-bold text-[#1B2438]">{{ $u->voiceover_logs_count }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-[#94A3B8] font-medium">Joined</div>
                        <div class="text-xs font-bold text-[#64748B]">{{ $u->created_at->format('M d') }}</div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.users.show', $u) }}" class="btn-sm btn-muted">View</a>
                    <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="inline">
                        @csrf
                        <button class="btn-sm {{ $u->is_active ? 'btn-red' : 'btn-green' }}">
                            {{ $u->is_active ? 'Disable' : 'Enable' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="glass-panel p-8 text-center">
            <p class="text-[#94A3B8] text-sm">No users found</p>
        </div>
    @endforelse
</div>

<!-- Pagination -->
<div class="mt-4">{{ $users->withQueryString()->links() }}</div>
@endsection
