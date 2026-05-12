<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In — Yantra Voice Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #E8EDF2; }
        .mobile-wrap { max-width: 420px; margin: 0 auto; min-height: 100vh; background: #FFFFFF; box-shadow: 0 0 60px rgba(0,0,0,0.08); }
        input:focus { outline: none; border-color: #1B2438; box-shadow: 0 0 0 2px rgba(27,36,56,0.1); }
    </style>
</head>
<body class="antialiased">

<div class="mobile-wrap flex flex-col justify-between px-8 py-12 min-h-screen">
    {{-- Top Section --}}
    <div>
        {{-- Logo --}}
        <div class="flex justify-center mb-10">
            @php $logoUrl = \App\Models\ApiSetting::getLogoUrl(); @endphp
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="Logo" class="h-20 w-auto object-contain">
            @else
                <div class="w-16 h-16 rounded-2xl bg-[#1B2438] flex items-center justify-center shadow-lg">
                    <svg class="w-9 h-9 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                </div>
            @endif
        </div>

        {{-- Error --}}
        @if($errors->any())
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-100">
                <p class="text-red-500 text-sm font-medium">{{ $errors->first() }}</p>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ url('/login') }}" class="space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#94A3B8] uppercase tracking-wider mb-2">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full px-5 py-4 rounded-2xl bg-[#F5F7FA] border border-[#E8EDF2] text-[#1B2438] text-sm font-medium placeholder-[#C0C9D6] transition-all"
                       placeholder="you@example.com">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#94A3B8] uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" required
                       class="w-full px-5 py-4 rounded-2xl bg-[#F5F7FA] border border-[#E8EDF2] text-[#1B2438] text-sm font-medium placeholder-[#C0C9D6] transition-all"
                       placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-[#D1D9E2] text-[#1B2438] focus:ring-[#1B2438]">
                    <span class="text-sm text-[#94A3B8] font-medium">Remember me</span>
                </label>
            </div>

            <button type="submit" class="w-full py-4 bg-[#1B2438] hover:bg-[#2A3548] text-white font-bold text-sm rounded-full transition-all active:scale-[0.98] shadow-lg shadow-[#1B2438]/20 mt-2">
                Sign In
            </button>
        </form>
    </div>

    {{-- Bottom --}}
    <p class="text-center text-sm text-[#94A3B8] mt-8">
        Don't have an account?
        <a href="{{ url('/register') }}" class="text-[#1B2438] font-bold">Create one</a>
    </p>
</div>

</body>
</html>
