@extends('layouts.admin')
@section('title', 'Settings')

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf

    <!-- API Key -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">🔑 OpenRouter API Key</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">Used for all TTS generations. <a href="https://openrouter.ai/keys" target="_blank" class="text-[#F97316] font-semibold">Get key →</a></p>
        <input type="password" name="openrouter_api_key" value="{{ $settings['openrouter_api_key'] }}"
               class="input-field w-full" placeholder="sk-or-v1-..." id="apiKeyInput">
        <label class="flex items-center gap-2 mt-2 cursor-pointer">
            <input type="checkbox" onchange="document.getElementById('apiKeyInput').type = this.checked ? 'text' : 'password'" class="rounded border-[#D1D9E2] text-[#1B2438]">
            <span class="text-[10px] text-[#94A3B8] font-medium">Show key</span>
        </label>
    </div>

    <!-- Admin Cost -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">💸 API Cost (USD)</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">Your actual cost per generation on OpenRouter.</p>
        <div>
            <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Cost Per Generation (USD)</label>
            <input type="number" name="admin_cost_per_generation_usd" step="0.0001" min="0.0001"
                   value="{{ $settings['admin_cost_per_generation_usd'] }}" class="input-field w-full" required>
        </div>
    </div>

    <!-- User Pricing -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">💰 User Pricing</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">1 Credit = ₨1</p>

        <div>
            <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">🎙️ Voiceover — Credits Per Generation</label>
            <input type="number" name="credit_cost_per_generation" step="0.1" min="0.1"
                   value="{{ $settings['credit_cost_per_generation'] }}" class="input-field w-full" required
                   id="creditCostInput" oninput="updateNprDisplay()">
        </div>

        <div class="mt-3 p-3 rounded-xl bg-blue-50 border border-blue-100">
            <div class="flex justify-between items-center">
                <span class="text-xs text-[#64748B] font-medium">User pays per voiceover:</span>
                <div class="text-right">
                    <span id="nprDisplay" class="text-base font-extrabold text-[#1B2438]">₨{{ number_format($settings['credit_cost_per_generation'], 2) }}</span>
                    <span class="text-[10px] text-[#94A3B8] block">{{ $settings['credit_cost_per_generation'] }} credits</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Ad Video Pricing -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">🎬 Video Render Cost</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">Flat fee for video rendering (any duration). Voiceover cost is charged separately.</p>
        
        <div>
            <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Render Cost (credits)</label>
            <input type="number" name="video_render_cost" step="1" min="1"
                   value="{{ $settings['video_render_cost'] }}" class="input-field w-full" required>
        </div>
        <div class="mt-3 p-3 rounded-xl bg-blue-50 border border-blue-100">
            <p class="text-xs text-[#64748B] font-medium">Total ad video cost = <strong>Voiceover (₨{{ $settings['credit_cost_per_generation'] }}) + Render (₨{{ $settings['video_render_cost'] }})</strong></p>
        </div>
    </div>

    <!-- App Logo -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">🎨 App Logo</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">Displayed on login/signup pages. Recommended: PNG with transparency, 200×200px+</p>
        
        @if($currentLogoUrl)
            <div class="mb-3 text-center">
                <p class="text-[10px] text-[#94A3B8] font-bold mb-1 uppercase">Current Logo</p>
                <img src="{{ $currentLogoUrl }}" alt="App Logo" class="h-16 w-auto object-contain mx-auto rounded-xl border border-[#E8EDF2] p-2">
            </div>
        @else
            <div class="mb-3 p-3 rounded-xl bg-blue-50 border border-blue-100 text-center">
                <p class="text-xs text-blue-600 font-medium">No logo uploaded. Default icon will be shown.</p>
            </div>
        @endif

        <input type="file" name="app_logo" accept="image/*" class="input-field w-full text-xs">
    </div>

    <!-- Payment QR Code -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">📱 Payment QR Code</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">Upload QR code image for users to make payments.</p>
        
        @if($currentQrUrl)
            <div class="mb-3">
                <p class="text-[10px] text-[#94A3B8] font-bold mb-1 uppercase">Current QR</p>
                <img src="{{ $currentQrUrl }}" alt="Payment QR" class="w-40 h-40 object-contain rounded-xl border border-[#E8EDF2] mx-auto">
            </div>
        @else
            <div class="mb-3 p-4 rounded-xl bg-yellow-50 border border-yellow-100 text-center">
                <p class="text-xs text-yellow-600 font-medium">⚠️ No QR uploaded yet. Users can't buy credits without it.</p>
            </div>
        @endif

        <input type="file" name="payment_qr" accept="image/*" class="input-field w-full text-xs">
    </div>

    <!-- Signup Bonus -->
    <div class="glass-panel p-5">
        <h3 class="text-sm font-bold text-[#1B2438] mb-1">🎁 Signup Bonus</h3>
        <p class="text-[10px] text-[#94A3B8] mb-4">Free credits for new users.</p>
        <div>
            <label class="text-[10px] text-[#94A3B8] font-medium block mb-1">Bonus Credits</label>
            <input type="number" name="signup_bonus_credits" step="1" min="0" max="10000"
                   value="{{ $settings['signup_bonus_credits'] }}" class="input-field w-full" required
                   id="bonusInput" oninput="updateBonusDisplay()">
        </div>
        <div class="mt-2 p-2.5 rounded-xl bg-green-50 border border-green-100">
            <span class="text-[11px] text-green-600 font-semibold">= ₨<span id="bonusNprDisplay">{{ number_format($settings['signup_bonus_credits'], 0) }}</span> worth</span>
        </div>
    </div>

    <button type="submit" class="w-full py-3.5 bg-[#1B2438] hover:bg-[#2A3548] text-white font-bold text-sm rounded-full transition-all active:scale-[0.98] shadow-lg shadow-[#1B2438]/20">
        💾 Save Settings
    </button>
</form>

<script>
function updateNprDisplay() {
    const credits = parseFloat(document.getElementById('creditCostInput').value) || 0;
    document.getElementById('nprDisplay').textContent = '₨' + credits.toFixed(2);
}
function updateBonusDisplay() {
    const credits = parseFloat(document.getElementById('bonusInput').value) || 0;
    document.getElementById('bonusNprDisplay').textContent = credits.toFixed(0);
}
</script>
@endsection
