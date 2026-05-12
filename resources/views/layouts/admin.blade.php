<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Yantra Voice Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #E8EDF2; }
        .mobile-wrap { max-width: 420px; margin: 0 auto; height: 100dvh; background: #F0F4F8; position: relative; box-shadow: 0 0 60px rgba(0,0,0,0.08); display: flex; flex-direction: column; overflow: hidden; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .stat-card { background: #FFFFFF; border: 1px solid #E8EDF2; border-radius: 16px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: all 0.2s; }
        .stat-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .glass-panel { background: #FFFFFF; border: 1px solid #E8EDF2; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); }
        .btn-sm { padding: 0.4rem 0.85rem; font-size: 0.75rem; border-radius: 9999px; transition: all 0.2s; font-weight: 600; }
        .btn-navy { background: #1B2438; color: white; }
        .btn-navy:hover { background: #2A3548; }
        .btn-green { background: #10B981; color: white; }
        .btn-green:hover { background: #059669; }
        .btn-red { background: #EF4444; color: white; }
        .btn-red:hover { background: #DC2626; }
        .btn-muted { background: #F0F4F8; color: #64748B; border: 1px solid #E8EDF2; }
        .btn-muted:hover { background: #E8EDF2; }
        .input-field { background: #F5F7FA; border: 1px solid #E8EDF2; color: #1B2438; padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.875rem; transition: all 0.2s; }
        .input-field:focus { border-color: #1B2438; outline: none; box-shadow: 0 0 0 2px rgba(27,36,56,0.1); }
        .badge { padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
        .data-table { border-collapse: separate; border-spacing: 0; width: 100%; }
        .data-table th { background: #F5F7FA; font-weight: 700; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94A3B8; }
        .data-table td { border-bottom: 1px solid #F0F4F8; font-size: 0.8rem; }
        .data-table tr:hover td { background: #FAFBFC; }
    </style>
</head>
<body class="antialiased">
    <div class="mobile-wrap pb-[80px]">
        {{-- Top App Bar --}}
        <header class="bg-white px-6 pt-14 pb-5 flex items-center justify-between z-10 sticky top-0 shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#1B2438] flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                </div>
                <div>
                    <h1 class="text-lg font-extrabold text-[#1B2438]">@yield('title', 'Dashboard')</h1>
                    <p class="text-[10px] text-[#94A3B8] font-medium">Admin Panel</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @yield('header-actions')
                <a href="{{ route('studio') }}" class="w-9 h-9 rounded-full bg-[#F0F4F8] flex items-center justify-center text-[#64748B] hover:bg-[#E8EDF2] transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                </a>
            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-y-auto no-scrollbar px-5 py-5">
            @if(session('success'))
                <div class="mb-5 p-4 rounded-2xl bg-green-50 border border-green-100 text-green-600 text-sm flex items-center gap-2 font-medium">
                    <span>✅</span> {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-5 p-4 rounded-2xl bg-red-50 border border-red-100 text-red-500 text-sm flex items-center gap-2 font-medium">
                    <span>❌</span> {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        {{-- Bottom Navigation Bar --}}
        @php $current = request()->route()->getName(); @endphp
        <nav class="absolute bottom-0 w-full bg-[#1B2438] rounded-t-[24px] shadow-[0_-4px_20px_rgba(0,0,0,0.08)] px-3 py-3 z-50 flex justify-around items-center">
            <a href="{{ route('admin.dashboard') }}" class="flex flex-col items-center gap-0.5 {{ $current === 'admin.dashboard' ? 'text-[#F97316]' : 'text-[#64748B]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span class="text-[9px] font-bold">Home</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="flex flex-col items-center gap-0.5 {{ str_starts_with($current, 'admin.users') ? 'text-[#F97316]' : 'text-[#64748B]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span class="text-[9px] font-bold">Users</span>
            </a>
            <a href="{{ route('admin.purchases.index') }}" class="flex flex-col items-center gap-0.5 relative {{ str_starts_with($current, 'admin.purchases') ? 'text-[#F97316]' : 'text-[#64748B]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                @php $pendingPurchaseCount = \App\Models\CreditPurchase::where('status', 'pending')->count(); @endphp
                @if($pendingPurchaseCount > 0)
                    <span class="absolute -top-1 -right-1 bg-[#F97316] text-white text-[8px] font-bold px-1 py-0.5 rounded-full min-w-[14px] text-center">{{ $pendingPurchaseCount }}</span>
                @endif
                <span class="text-[9px] font-bold">Buy</span>
            </a>
            <a href="{{ route('admin.transactions.index') }}" class="flex flex-col items-center gap-0.5 {{ $current === 'admin.transactions.index' ? 'text-[#F97316]' : 'text-[#64748B]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                <span class="text-[9px] font-bold">Txns</span>
            </a>
            <a href="{{ route('admin.finance.index') }}" class="flex flex-col items-center gap-0.5 {{ str_starts_with($current, 'admin.finance') ? 'text-[#F97316]' : 'text-[#64748B]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[9px] font-bold">Finance</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="flex flex-col items-center gap-0.5 {{ $current === 'admin.settings.index' ? 'text-[#F97316]' : 'text-[#64748B]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="text-[9px] font-bold">Settings</span>
            </a>
        </nav>
    </div>
</body>
</html>
