<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Yantra Voice Studio — AI-powered Text-to-Speech">
    <title>Yantra Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { colors: {
            navy: '#1B2438', coral: '#F97316', lightbg: '#F0F4F8', cardbg: '#FFFFFF',
        }, boxShadow: {
            'soft': '0 4px 20px rgba(0,0,0,0.06)', 'nav': '0 -4px 20px rgba(0,0,0,0.08)',
        }}}}
    </script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #E8EDF2; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .mobile-wrapper { max-width: 420px; margin: 0 auto; height: 100dvh; background: #F0F4F8; position: relative; box-shadow: 0 0 60px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-direction: column; }
        textarea:focus, input:focus, select:focus { outline: none; box-shadow: 0 0 0 2px rgba(27,36,56,0.12); border-color: #1B2438; }
        .toast { position: absolute; top: 1rem; left: 1rem; right: 1rem; padding: 1rem 1.5rem; border-radius: 1rem; color: #fff; font-weight: 600; z-index: 9999; transform: translateY(-120%); opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); box-shadow: 0 10px 30px rgba(0,0,0,0.2); font-size: 13px; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.error { background: #EF4444; }
        .toast.success { background: #10B981; }
        .spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block; vertical-align: middle; margin-right: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-progress { position: relative; overflow: hidden; }
        .btn-progress .progress-fill { position: absolute; left: 0; top: 0; bottom: 0; background: rgba(255,255,255,0.18); border-radius: inherit; transition: width 0.3s ease; width: 0%; pointer-events: none; }
        .btn-progress .progress-text { position: relative; z-index: 1; }
        .user-menu-dropdown { position: absolute; top: 56px; right: 0; background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.12); padding: 0.5rem; display: none; z-index: 100; min-width: 200px; border: 1px solid #E8EDF2; }
        .user-menu.active .user-menu-dropdown { display: block; }
    </style>
</head>
<body class="antialiased">

<div class="mobile-wrapper pb-[80px]">
    <div id="toast" class="toast text-sm"></div>

    {{-- Top App Bar --}}
    <header class="bg-white px-6 pt-14 pb-5 flex items-center justify-between z-10 sticky top-0 shadow-soft">
        <div>
            <h1 class="text-[22px] font-extrabold text-navy tracking-tight">Yantra Studio</h1>
            <p class="text-[11px] text-[#94A3B8] font-medium mt-0.5">Create amazing content</p>
        </div>
        
        <div class="user-menu relative">
            <button id="profileBtn" class="w-10 h-10 rounded-full bg-navy text-white font-bold text-sm flex items-center justify-center cursor-pointer hover:bg-[#2A3548] transition-colors">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </button>
            <div class="user-menu-dropdown">
                <div class="px-4 py-3 border-b border-[#F0F4F8] mb-1">
                    <p class="text-sm font-bold text-navy truncate">{{ $user->name }}</p>
                    <p class="text-xs text-[#94A3B8] font-medium truncate flex items-center gap-1 mt-0.5">
                        <span class="text-coral">💰</span> ₨{{ number_format($userNpr, 2) }}
                    </p>
                </div>
                @if($user->isAdmin())
                    <a href="{{ url('/admin') }}" class="block px-4 py-2.5 text-sm text-[#64748B] hover:bg-[#F0F4F8] rounded-xl transition-colors font-medium">🛡️ Admin Panel</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 rounded-xl transition-colors font-medium">🚪 Logout</button>
                </form>
            </div>
        </div>
    </header>

    {{-- Scrollable Content Area --}}
    <main class="flex-1 overflow-y-auto no-scrollbar px-6 py-2">
        @if(!$hasApiKey)
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-100 text-red-600 text-xs font-medium flex items-center gap-2">
                ⚠️ TTS service is not configured yet. Contact admin.
            </div>
        @endif

        {{-- ─── TAB: DASHBOARD ─── --}}
        <div id="pane-dashboard" class="tab-pane block space-y-4 pb-6">
            {{-- Welcome Card --}}
            <div class="bg-gradient-to-br from-navy to-[#2A3548] rounded-2xl p-5 text-white shadow-lg mt-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-white/60 font-medium">नमस्कार 👋</p>
                        <h2 class="text-lg font-extrabold mt-0.5">{{ $user->name }}</h2>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-white/50 font-medium uppercase tracking-wider">Balance</p>
                        <p class="text-xl font-extrabold text-coral">₨{{ number_format($userNpr, 2) }}</p>
                        <p class="text-[10px] text-white/40">{{ number_format($user->credits, 0) }} credits</p>
                    </div>
                </div>
                <div class="mt-3 bg-white/10 rounded-full h-2 overflow-hidden">
                    @php $barWidth = min(100, max(3, ($user->credits / max(1, $user->credits + 1)) * 100)); @endphp
                    <div class="h-full rounded-full bg-gradient-to-r from-coral to-orange-400" style="width: {{ $barWidth }}%"></div>
                </div>
                <button onclick="openBuyCreditsPopup()" class="mt-3 w-full py-2.5 bg-coral hover:bg-orange-500 text-white font-bold text-xs rounded-full transition-all active:scale-[0.97] shadow-lg shadow-coral/30 flex items-center justify-center gap-1.5">
                    💳 Buy Credits
                </button>
            </div>

            {{-- Stats Grid 2x2 --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-2xl p-4 shadow-soft border border-[#E8EDF2] text-center">
                    <div class="text-2xl mb-1">🎙️</div>
                    <p class="text-xl font-extrabold text-navy">{{ $totalVoiceovers }}</p>
                    <p class="text-[10px] text-[#94A3B8] font-bold uppercase tracking-wider">Voiceovers</p>
                </div>
                <div class="bg-white rounded-2xl p-4 shadow-soft border border-[#E8EDF2] text-center">
                    <div class="text-2xl mb-1">🎬</div>
                    <p class="text-xl font-extrabold text-navy">{{ $totalAdVideos }}</p>
                    <p class="text-[10px] text-[#94A3B8] font-bold uppercase tracking-wider">Ad Videos</p>
                </div>
                <div class="bg-white rounded-2xl p-4 shadow-soft border border-[#E8EDF2] text-center">
                    <div class="text-2xl mb-1">💸</div>
                    <p class="text-xl font-extrabold text-navy">₨{{ number_format($nprSpentMonth, 0) }}</p>
                    <p class="text-[10px] text-[#94A3B8] font-bold uppercase tracking-wider">This Month</p>
                </div>
                <div class="bg-white rounded-2xl p-4 shadow-soft border border-[#E8EDF2] text-center">
                    <div class="text-2xl mb-1">📅</div>
                    <p class="text-xl font-extrabold text-navy">{{ $accountAge }}</p>
                    <p class="text-[10px] text-[#94A3B8] font-bold uppercase tracking-wider">Days Active</p>
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="bg-white rounded-2xl shadow-soft border border-[#E8EDF2] overflow-hidden">
                <div class="px-4 pt-4 pb-2 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-navy">Recent Activity</h3>
                    <span class="text-[10px] text-[#94A3B8] font-medium">Last 10</span>
                </div>
                <div class="divide-y divide-[#F0F4F8]">
                    @forelse($recentActivity as $act)
                        <div class="px-4 py-3 flex items-center gap-3">
                            <span class="text-lg">{{ $act['icon'] }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-navy truncate">{{ $act['label'] }}</p>
                                <p class="text-[10px] text-[#94A3B8]">
                                    {{ $act['voice'] ?? '' }} · {{ $act['created_at']->diffForHumans() }}
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                @if($act['status'] === 'completed' || $act['status'] === 'success')
                                    <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[9px] font-bold">Done</span>
                                @elseif($act['status'] === 'failed')
                                    <span class="inline-block px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-[9px] font-bold">Failed</span>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 text-[9px] font-bold">{{ ucfirst($act['status'] ?? 'pending') }}</span>
                                @endif
                                @if($act['cost_npr'] > 0)
                                    <p class="text-[10px] text-[#94A3B8] mt-0.5">₨{{ number_format($act['cost_npr'], 1) }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <p class="text-sm text-[#94A3B8]">No activity yet. Start creating!</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Credit History --}}
            <div class="bg-white rounded-2xl shadow-soft border border-[#E8EDF2] overflow-hidden">
                <div class="px-4 pt-4 pb-2 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-navy">Credit History</h3>
                    <span class="text-[10px] text-[#94A3B8] font-medium">Last 20</span>
                </div>
                <div class="divide-y divide-[#F0F4F8]">
                    @forelse($creditHistory as $tx)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                                    {{ $tx['is_positive'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500' }}">
                                    {{ $tx['is_positive'] ? '+' : '−' }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-navy truncate">{{ $tx['description'] }}</p>
                                    <p class="text-[10px] text-[#94A3B8]">{{ $tx['created_at']->diffForHumans() }}</p>
                                </div>
                            </div>
                            <span class="text-xs font-bold flex-shrink-0 {{ $tx['is_positive'] ? 'text-green-600' : 'text-red-500' }}">
                                {{ $tx['is_positive'] ? '+' : '−' }}₨{{ number_format($tx['amount_npr'], 1) }}
                            </span>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <p class="text-sm text-[#94A3B8]">No transactions yet.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Pending Purchases --}}
            <div id="pendingPurchases" class="hidden">
                <div class="bg-white rounded-2xl shadow-soft border border-[#E8EDF2] overflow-hidden">
                    <div class="px-4 pt-4 pb-2">
                        <h3 class="text-sm font-bold text-navy">⏳ Pending Requests</h3>
                    </div>
                    <div id="pendingPurchasesList" class="divide-y divide-[#F0F4F8]"></div>
                </div>
            </div>
        </div>

    {{-- ═══ BUY CREDITS POPUP (full-screen overlay, outside tabs) ═══ --}}
    <div id="buyCreditsPopup" class="hidden absolute inset-0 z-[90] bg-black/50 backdrop-blur-sm">
        <div class="absolute inset-0 overflow-y-auto no-scrollbar pt-16 pb-24 px-5">
            {{-- Close --}}
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-extrabold text-white">💳 Buy Credits</h2>
                <button onclick="closeBuyCreditsPopup()" class="w-8 h-8 rounded-full bg-white/20 text-white flex items-center justify-center text-lg hover:bg-white/30 transition">✕</button>
            </div>

            {{-- Step 1: Package Selection --}}
            <div id="popupStep1" class="space-y-2">
                <p class="text-xs text-white/70 font-medium mb-1">Select a package · 1 Credit = ₨1</p>
                @php
                    $packages = [
                        ['amount' => 100,   'bonus_pct' => 0,  'bonus' => 0,    'total' => 100],
                        ['amount' => 500,   'bonus_pct' => 2,  'bonus' => 10,   'total' => 510],
                        ['amount' => 1000,  'bonus_pct' => 4,  'bonus' => 40,   'total' => 1040],
                        ['amount' => 5000,  'bonus_pct' => 6,  'bonus' => 300,  'total' => 5300],
                        ['amount' => 10000, 'bonus_pct' => 10, 'bonus' => 1000, 'total' => 11000],
                    ];
                @endphp
                @foreach($packages as $pkg)
                    <button onclick="selectCreditPackage({{ $pkg['amount'] }}, {{ $pkg['bonus'] }}, {{ $pkg['total'] }})"
                        class="w-full flex items-center justify-between p-3.5 rounded-2xl bg-white/95 backdrop-blur border border-white/20 hover:bg-white transition-all shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-coral to-orange-400 flex items-center justify-center text-white text-xs font-bold shadow">₨{{ $pkg['amount'] >= 1000 ? number_format($pkg['amount']/1000, 0).'k' : $pkg['amount'] }}</div>
                            <div class="text-left">
                                <p class="text-sm font-bold text-navy">₨{{ number_format($pkg['amount']) }}</p>
                                @if($pkg['bonus'] > 0)
                                    <p class="text-[10px] text-green-600 font-semibold">+{{ $pkg['bonus_pct'] }}% bonus (+{{ $pkg['bonus'] }} credits)</p>
                                @else
                                    <p class="text-[10px] text-[#94A3B8]">Standard</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-extrabold text-coral">{{ number_format($pkg['total']) }}</p>
                            <p class="text-[9px] text-[#94A3B8]">credits</p>
                        </div>
                    </button>
                @endforeach
            </div>

            {{-- Step 2: Payment (hidden until package selected) --}}
            <div id="popupStep2" class="hidden mt-3 space-y-3">
                <button onclick="backToPackages()" class="text-xs text-white/70 font-medium hover:text-white">← Back to packages</button>

                {{-- Selected Package --}}
                <div class="p-4 rounded-2xl bg-gradient-to-br from-navy to-[#2A3548] text-white">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-white/60">You pay</span>
                        <span class="text-xl font-extrabold" id="modalPayAmount">₨0</span>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-xs text-white/60">You get</span>
                        <span class="text-xl font-extrabold text-coral" id="modalGetCredits">0 credits</span>
                    </div>
                </div>

                {{-- QR Code --}}
                <div class="bg-white rounded-2xl p-4 text-center" id="qrSection">
                    <p class="text-[10px] text-[#94A3B8] font-bold uppercase tracking-wider mb-3">Scan QR to Pay</p>
                    <div id="qrLoading" class="py-6">
                        <div class="inline-block w-6 h-6 border-3 border-coral border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <img id="qrImage" class="hidden mx-auto max-w-[180px] rounded-xl border border-[#E8EDF2]" alt="Payment QR">
                    <a id="qrDownloadBtn" href="#" download="payment_qr.png" class="hidden mt-2 inline-block text-xs text-coral font-bold hover:underline">⬇️ Save QR Image</a>
                </div>

                {{-- Upload Screenshot --}}
                <div class="bg-white rounded-2xl p-4">
                    <p class="text-[10px] text-[#94A3B8] font-bold uppercase tracking-wider mb-2">Upload Payment Screenshot</p>
                    <input type="file" id="screenshotInput" accept="image/*" class="w-full text-xs bg-[#F8FAFC] border border-[#E8EDF2] rounded-lg p-2">
                    <img id="screenshotPreview" class="hidden mt-2 max-h-32 rounded-lg border border-[#E8EDF2] mx-auto">
                </div>

                {{-- Submit --}}
                <button id="submitPurchaseBtn" onclick="submitPurchaseRequest()"
                    class="w-full py-3.5 bg-coral hover:bg-orange-600 text-white font-bold text-sm rounded-full transition-colors shadow-lg btn-progress">
                    <span class="progress-fill"></span>
                    <span class="progress-text">✅ Submit Purchase Request</span>
                </button>
            </div>
        </div>
    </div>

        {{-- ─── TAB: VOICEOVER ─── --}}
        <div id="pane-voiceover" class="tab-pane hidden space-y-5">
            
            {{-- Text Input --}}
            <div>
                <label class="block text-sm font-bold text-navy mb-3 flex justify-between items-center">
                    Write Script
                    <span id="charCounter" class="text-xs font-medium text-[#94A3B8]">0 / 2000</span>
                </label>
                <div class="bg-white rounded-2xl shadow-soft p-1 border border-[#E8EDF2]">
                    <textarea id="textInput" rows="6" placeholder="Type or paste your text here… supports English & Nepali."
                              class="w-full bg-transparent border-none p-4 text-navy text-sm leading-relaxed placeholder-[#C0C9D6] resize-none rounded-xl"></textarea>
                </div>
            </div>

            {{-- Emotion / Tone Horizontal Scroll --}}
            <div>
                <label class="block text-sm font-bold text-navy mb-3">Tone & Emotion</label>
                <input type="hidden" id="emotionInput" value="Neutral">
                <div class="flex overflow-x-auto no-scrollbar gap-2.5 pb-2 -mx-6 px-6" id="emotionScroll">
                    <button type="button" class="emotion-btn active shrink-0 px-5 py-2.5 text-[13px] font-semibold rounded-full bg-navy text-white transition-all" data-emotion="Neutral">😐 Neutral</button>
                    <button type="button" class="emotion-btn shrink-0 px-5 py-2.5 text-[13px] font-medium rounded-full bg-white text-[#64748B] shadow-soft border border-[#E8EDF2] transition-all" data-emotion="Cheerful">😊 Cheerful</button>
                    <button type="button" class="emotion-btn shrink-0 px-5 py-2.5 text-[13px] font-medium rounded-full bg-white text-[#64748B] shadow-soft border border-[#E8EDF2] transition-all" data-emotion="Professional">💼 Professional</button>
                    <button type="button" class="emotion-btn shrink-0 px-5 py-2.5 text-[13px] font-medium rounded-full bg-white text-[#64748B] shadow-soft border border-[#E8EDF2] transition-all" data-emotion="Urgent">⚡ Urgent</button>
                    <button type="button" class="emotion-btn shrink-0 px-5 py-2.5 text-[13px] font-medium rounded-full bg-white text-[#64748B] shadow-soft border border-[#E8EDF2] transition-all" data-emotion="Calm">🧘 Calm</button>
                    <button type="button" class="emotion-btn shrink-0 px-5 py-2.5 text-[13px] font-medium rounded-full bg-white text-[#64748B] shadow-soft border border-[#E8EDF2] transition-all" data-emotion="Storyteller">📖 Story</button>
                </div>
            </div>

            {{-- Voice & Language --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white p-4 rounded-2xl shadow-soft border border-[#E8EDF2]">
                    <label class="block text-[10px] font-bold text-[#94A3B8] mb-2 uppercase tracking-wider">Language</label>
                    <select id="languageSelect" class="w-full bg-transparent text-navy font-semibold text-sm appearance-none cursor-pointer border-none">
                        <option value="Nepali" selected>🇳🇵 Nepali</option>
                        <option value="English">🇬🇧 English</option>
                    </select>
                </div>
                <div class="bg-white p-4 rounded-2xl shadow-soft border border-[#E8EDF2]">
                    <label class="block text-[10px] font-bold text-[#94A3B8] mb-2 uppercase tracking-wider">Voice</label>
                    <select id="voiceSelect" class="w-full bg-transparent text-navy font-semibold text-sm appearance-none cursor-pointer border-none">
                        <option value="Kore">सरस्वती (Kore)</option>
                        <option value="Aoede">अन्नपूर्णा (Aoede)</option>
                        <option value="Leda">लक्ष्मी (Leda)</option>
                        <option value="Zephyr">बायु (Zephyr)</option>
                        <option value="Puck">राजेश (Puck)</option>
                        <option value="Charon">सुर्य (Charon)</option>
                        <option value="Fenrir">भीम (Fenrir)</option>
                        <option value="Enceladus">गणेश (Enceladus)</option>
                        <option value="Achernar">तारा (Achernar)</option>
                        <option value="Orus">अर्जुन (Orus)</option>
                        <option value="Callirrhoe">सरस्वती (Callirrhoe)</option>
                        <option value="Autonoe">रूपा (Autonoe)</option>
                        <option value="Iapetus">प्रताप (Iapetus)</option>
                        <option value="Umbriel">छाया (Umbriel)</option>
                        <option value="Algenib">दीपक (Algenib)</option>
                    </select>
                </div>
            </div>

            {{-- Output Section --}}
            <div id="outputSection" class="hidden">
                <div class="bg-white p-5 rounded-2xl shadow-soft border border-[#E8EDF2]">
                    <h3 class="text-sm font-bold text-navy mb-4">Generated Audio</h3>
                    <audio id="audioPlayer" controls class="w-full h-10 mb-4 rounded-full"></audio>
                    <div class="flex justify-between items-center">
                        <a id="downloadBtn" href="#" download class="inline-flex items-center justify-center bg-coral hover:bg-orange-600 text-white text-xs font-bold py-2.5 px-6 rounded-full transition-colors shadow-md">
                            Download Audio
                        </a>
                        <span id="creditInfo" class="text-xs font-medium text-[#94A3B8]"></span>
                    </div>
                </div>
            </div>

            {{-- Generate Button --}}
            <div class="pt-2">
                <button id="generateBtn" type="button"
                        class="w-full py-4 bg-navy hover:bg-[#2A3548] text-white font-bold text-sm rounded-full transition-all active:scale-[0.98] shadow-lg shadow-navy/20 flex justify-center items-center gap-2">
                    <span id="btnText">Generate Audio (₨{{ number_format($creditCost, 0) }})</span>
                </button>
            </div>
            
            <div class="pb-6"></div>
        </div>

        {{-- ─── TAB: AD VIDEO ─── --}}
        <div id="pane-advideo" class="tab-pane hidden space-y-5">
            
            <!-- Step 1: Upload Form -->
            <form id="adUploadForm" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-navy mb-3">Upload Product Media</label>
                    <div class="relative bg-white shadow-soft rounded-2xl p-8 text-center border-2 border-dashed border-[#D1D9E2] hover:border-navy transition-colors group">
                        <input type="file" id="adMedia" name="media[]" accept="video/*" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" required>
                        <div class="pointer-events-none text-[#94A3B8] group-hover:text-navy transition-colors flex flex-col items-center justify-center">
                            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            <span class="text-sm font-semibold">Tap to select videos</span>
                            <span class="text-xs mt-1 text-[#C0C9D6]" id="fileCountText">Multiple files supported</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-navy mb-3">Key Highlights (Optional)</label>
                    <div class="bg-white rounded-2xl shadow-soft p-1 border border-[#E8EDF2]">
                        <input type="text" id="adHighlights" placeholder="e.g. 50% off sale, free shipping" 
                               class="w-full bg-transparent border-none px-4 py-3 text-navy text-sm placeholder-[#C0C9D6] rounded-xl">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white p-4 rounded-2xl shadow-soft border border-[#E8EDF2]">
                        <label class="block text-[10px] font-bold text-[#94A3B8] mb-2 uppercase tracking-wider">Language</label>
                        <select id="adLanguage" class="w-full bg-transparent text-navy font-semibold text-sm appearance-none cursor-pointer border-none">
                            <option value="Nepali" selected>🇳🇵 Nepali</option>
                            <option value="English">🇬🇧 English</option>
                        </select>
                    </div>
                    <div class="bg-white p-4 rounded-2xl shadow-soft border border-[#E8EDF2]">
                        <label class="block text-[10px] font-bold text-[#94A3B8] mb-2 uppercase tracking-wider">Aspect Ratio</label>
                        <select id="adAspectRatio" class="w-full bg-transparent text-navy font-semibold text-sm appearance-none cursor-pointer border-none">
                            <option value="original">Original</option>
                            <option value="9:16">📱 9:16</option>
                            <option value="16:9">💻 16:9</option>
                            <option value="1:1">🔲 1:1</option>
                        </select>
                    </div>
                </div>

                <div class="pt-1 pb-2">
                    <button type="submit" id="adUploadBtn" class="w-full py-4 bg-navy hover:bg-[#2A3548] text-white font-bold text-sm rounded-full transition-all active:scale-[0.98] shadow-lg shadow-navy/20 flex justify-center items-center gap-2">
                        Upload & Auto-Script
                    </button>
                </div>
                <p class="text-[10px] text-center text-[#94A3B8] pb-4">💡 Cost: Voiceover ₨{{ number_format($creditCost, 0) }} + Render ₨{{ number_format($videoRenderCost, 0) }} = <strong class="text-navy">₨{{ number_format($creditCost + $videoRenderCost, 0) }}</strong> total</p>
            </form>

            <!-- Step 2: Script Editor (Scene-Segmented) -->
            <div id="adScriptSection" class="hidden space-y-5 pb-6">
                <div>
                    <label class="block text-sm font-bold text-navy mb-3">Review Scene-by-Scene Script</label>
                    <p class="text-xs text-[#94A3B8] mb-3">Each segment syncs with the corresponding video scene. Edit text per scene for precise voiceover alignment.</p>
                    <div id="adScriptSegments" class="space-y-2 max-h-[400px] overflow-y-auto pr-1">
                        <!-- Segments rendered by JS -->
                    </div>
                </div>
                
                <div class="bg-white p-4 rounded-2xl shadow-soft border border-[#E8EDF2]">
                    <label class="block text-[10px] font-bold text-[#94A3B8] mb-2 uppercase tracking-wider">Voice Model</label>
                    <select id="adVoiceModel" class="w-full bg-transparent text-navy font-semibold text-sm appearance-none border-none cursor-pointer"></select>
                </div>

                <div class="flex gap-3">
                    <button type="button" id="adRegenerateScriptBtn" class="flex-1 py-3.5 bg-white hover:bg-[#F0F4F8] text-navy font-bold text-xs rounded-full transition-colors border border-[#E8EDF2]">
                        🔄 Regenerate
                    </button>
                    <button type="button" id="adGenerateVideoBtn" class="flex-[2] py-3.5 bg-coral hover:bg-orange-600 text-white font-bold text-xs rounded-full transition-all active:scale-[0.98] shadow-lg shadow-orange-500/20">
                        Approve & Render
                    </button>
                </div>
            </div>

            <!-- Step 3: Progress & Output -->
            <div id="adOutputSection" class="hidden text-center space-y-5 pb-6">
                <div id="adProgress" class="bg-white rounded-2xl shadow-soft p-8 text-center border border-[#E8EDF2]">
                    <h3 class="text-sm font-bold text-navy mb-1" id="adRenderLabel">Rendering Video...</h3>
                    <p class="text-xs text-[#94A3B8] mb-4" id="adRenderSubtext">Generating voiceover & mixing</p>
                    <div class="w-full bg-[#F0F4F8] rounded-full h-3 overflow-hidden">
                        <div id="adRenderBar" class="h-full rounded-full bg-gradient-to-r from-coral to-orange-400 transition-all duration-500 ease-out" style="width: 0%"></div>
                    </div>
                    <p class="text-xs font-bold text-coral mt-2" id="adRenderPct">0%</p>
                </div>

                <div id="adVideoContainer" class="hidden bg-white rounded-2xl shadow-soft p-3 border border-[#E8EDF2]">
                    <video id="adVideoPlayer" controls class="w-full rounded-xl bg-slate-900 aspect-[9/16] object-cover"></video>
                    <div class="mt-4 flex flex-col gap-2">
                        <div class="flex gap-3">
                            <button type="button" id="adDownloadBtn" data-url="" class="flex-[2] flex items-center justify-center py-3 bg-navy hover:bg-[#2A3548] text-white font-bold text-xs rounded-full transition-colors shadow-lg">
                                ⬇️ Download MP4
                            </button>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="adEditRerenderBtn" class="flex-1 py-3 bg-white hover:bg-[#F0F4F8] text-navy font-bold text-xs rounded-full transition-colors border border-coral/30">
                                ✏️ Edit & Re-render
                            </button>
                            <button type="button" id="adResetBtn" class="flex-1 py-3 bg-white hover:bg-[#F0F4F8] text-navy font-bold text-xs rounded-full transition-colors border border-[#E8EDF2]">
                                ➕ Make Another
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Library Tab -->
        <div id="pane-library" class="tab-pane hidden w-full max-w-md mx-auto">
            <div class="flex items-center justify-between mb-4 mt-2">
                <h2 class="text-base font-bold text-navy">My Videos</h2>
                <button onclick="loadLibrary()" class="text-xs text-coral font-bold hover:underline">↻ Refresh</button>
            </div>
            <div id="libraryGrid" class="space-y-3 pb-24 max-h-[70vh] overflow-y-auto">
                <div class="text-center py-12 text-[#94A3B8] text-sm">Loading...</div>
            </div>
        </div>
    </main>

    {{-- Bottom Navigation Bar --}}
    <nav class="absolute bottom-0 w-full bg-navy rounded-t-[24px] shadow-nav px-6 py-4 z-50 flex justify-around items-center">
        <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-btn flex flex-col items-center gap-1 transition-colors group">
            <div class="nav-icon text-coral group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
            </div>
            <span class="text-[10px] font-bold text-coral nav-text">Dashboard</span>
        </button>
        <button onclick="switchTab('voiceover')" id="nav-voiceover" class="nav-btn flex flex-col items-center gap-1 transition-colors group">
            <div class="nav-icon text-[#64748B] group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
            </div>
            <span class="text-[10px] font-bold text-[#64748B] nav-text">Voiceover</span>
        </button>
        <button onclick="switchTab('advideo')" id="nav-advideo" class="nav-btn flex flex-col items-center gap-1 transition-colors group">
            <div class="nav-icon text-[#64748B] group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
            </div>
            <span class="text-[10px] font-bold text-[#64748B] nav-text">Ad Video</span>
        </button>
        <button onclick="switchTab('library'); loadLibrary();" id="nav-library" class="nav-btn flex flex-col items-center gap-1 transition-colors group">
            <div class="nav-icon text-[#64748B] group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <span class="text-[10px] font-bold text-[#64748B] nav-text">Library</span>
        </button>
    </nav>
</div>

<script>
    // Toggle Profile Dropdown
    const profileBtn = document.getElementById('profileBtn');
    const userMenu = document.querySelector('.user-menu');
    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('active');
    });
    document.addEventListener('click', () => userMenu.classList.remove('active'));

    // Tab Switching Logic
    function switchTab(tab) {
        // Hide/Show content panes
        document.querySelectorAll('.tab-pane').forEach(el => {
            el.classList.add('hidden');
            el.classList.remove('block');
        });
        document.getElementById('pane-' + tab).classList.remove('hidden');
        document.getElementById('pane-' + tab).classList.add('block');

        // Update Bottom Nav colors
        document.querySelectorAll('.nav-btn .nav-icon, .nav-btn .nav-text').forEach(el => {
            el.classList.remove('text-coral');
            el.classList.add('text-[#64748B]');
        });
        const activeNav = document.getElementById('nav-' + tab);
        activeNav.querySelector('.nav-icon').classList.remove('text-[#64748B]');
        activeNav.querySelector('.nav-icon').classList.add('text-coral');
        activeNav.querySelector('.nav-text').classList.remove('text-[#64748B]');
        activeNav.querySelector('.nav-text').classList.add('text-coral');
    }

    // Main App Logic
    document.addEventListener('DOMContentLoaded', function () {
        const BASE = "{{ url('/') }}";
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;

        const languageSelect  = document.getElementById('languageSelect');
        const voiceSelect     = document.getElementById('voiceSelect');
        const emotionScroll   = document.getElementById('emotionScroll');
        const emotionInput    = document.getElementById('emotionInput');
        const textInput       = document.getElementById('textInput');
        const charCounter     = document.getElementById('charCounter');
        const generateBtn     = document.getElementById('generateBtn');
        const btnText         = document.getElementById('btnText');
        const outputSection   = document.getElementById('outputSection');
        const audioPlayer     = document.getElementById('audioPlayer');
        const downloadBtn     = document.getElementById('downloadBtn');
        const toast           = document.getElementById('toast');

        const voiceMap = {
            Nepali: [
                {id: 'Kore', name: 'सरस्वती (Kore)'},
                {id: 'Aoede', name: 'अन्नपूर्णा (Aoede)'},
                {id: 'Leda', name: 'लक्ष्मी (Leda)'},
                {id: 'Zephyr', name: 'बायु (Zephyr)'},
                {id: 'Puck', name: 'राजेश (Puck)'},
                {id: 'Charon', name: 'सुर्य (Charon)'},
                {id: 'Fenrir', name: 'भीम (Fenrir)'},
                {id: 'Enceladus', name: 'गणेश (Enceladus)'},
                {id: 'Achernar', name: 'तारा (Achernar)'},
                {id: 'Orus', name: 'अर्जुन (Orus)'},
                {id: 'Callirrhoe', name: 'सरस्वती (Callirrhoe)'},
                {id: 'Autonoe', name: 'रूपा (Autonoe)'},
                {id: 'Iapetus', name: 'प्रताप (Iapetus)'},
                {id: 'Umbriel', name: 'छाया (Umbriel)'},
                {id: 'Algenib', name: 'दीपक (Algenib)'},
            ],
            English: [
                {id: 'Puck', name: 'राजेश (Puck)'},
                {id: 'Charon', name: 'सुर्य (Charon)'},
                {id: 'Kore', name: 'सरस्वती (Kore)'},
                {id: 'Fenrir', name: 'भीम (Fenrir)'},
                {id: 'Aoede', name: 'अन्नपूर्णा (Aoede)'},
                {id: 'Achernar', name: 'तारा (Achernar)'},
                {id: 'Enceladus', name: 'गणेश (Enceladus)'},
                {id: 'Orus', name: 'अर्जुन (Orus)'},
                {id: 'Leda', name: 'लक्ष्मी (Leda)'},
                {id: 'Zephyr', name: 'बायु (Zephyr)'},
                {id: 'Callirrhoe', name: 'सरस्वती (Callirrhoe)'},
                {id: 'Autonoe', name: 'रूपा (Autonoe)'},
                {id: 'Iapetus', name: 'प्रताप (Iapetus)'},
                {id: 'Umbriel', name: 'छाया (Umbriel)'},
                {id: 'Algenib', name: 'दीपक (Algenib)'},
            ]
        };

        // Voice Filtering
        languageSelect.addEventListener('change', function () {
            const voices = voiceMap[this.value] || voiceMap.Nepali;
            voiceSelect.innerHTML = voices.map(v => `<option value="${v.id}">${v.name}</option>`).join('');
        });

        // Emotion Scroll Logic
        emotionScroll.addEventListener('click', function (e) {
            const btn = e.target.closest('.emotion-btn');
            if (!btn) return;
            
            emotionScroll.querySelectorAll('.emotion-btn').forEach(b => {
                b.classList.remove('bg-navy', 'text-white');
                b.classList.add('bg-white', 'text-slate-500', 'shadow-soft');
            });
            btn.classList.remove('bg-white', 'text-slate-500', 'shadow-soft');
            btn.classList.add('bg-navy', 'text-white');
            emotionInput.value = btn.dataset.emotion;
            
            // Scroll to center the button smoothly
            btn.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        });

        // Character Counter
        textInput.addEventListener('input', function () {
            const len = this.value.length;
            charCounter.textContent = `${len} / 2000`;
            if(len > 2000) charCounter.classList.add('text-red-500');
            else charCounter.classList.remove('text-red-500');
        });

        function showToast(message, type = 'error') {
            toast.textContent = message;
            toast.className = `toast ${type}`;
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => toast.classList.remove('show'), 4000);
        }

        // Voiceover Generation
        generateBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            const text     = textInput.value.trim();
            const language = languageSelect.value;
            const voice    = voiceSelect.value;
            const emotion  = emotionInput.value;

            if (!text) return showToast('Please enter text to synthesize.');
            if (text.length > 2000) return showToast('Text exceeds 2000 characters.');

            generateBtn.disabled = true;
            btnText.innerHTML = '<span class="spinner"></span> Synthesizing...';

            try {
                const response = await fetch(BASE + '/api/generate-audio', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ text, language, voice, emotion }),
                });

                if (!response.ok) {
                    const errMsg = await parseErrorResponse(response);
                    throw new Error(errMsg);
                }
                const data = await response.json();

                audioPlayer.src = data.audio_url;
                downloadBtn.href = data.audio_url;
                outputSection.classList.remove('hidden');

                if (data.npr_used !== undefined) {
                    document.getElementById('creditInfo').textContent = `Cost: ₨${parseFloat(data.npr_used).toFixed(0)}`;
                }
                showToast('Audio generated successfully!', 'success');
            } catch (err) {
                showToast(err.message || 'Error generating audio.');
            } finally {
                generateBtn.disabled = false;
                btnText.innerHTML = 'Generate Audio (₨{{ number_format($creditCost, 0) }})';
            }
        });

        // ==========================================
        // AD VIDEO LOGIC
        // ==========================================
        let currentJobId = null;

        // File Selection Visuals
        const fileInput = document.getElementById('adMedia');
        const fileCountText = document.getElementById('fileCountText');
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileCountText.textContent = `${this.files.length} video(s) selected`;
                fileCountText.classList.add('text-navy', 'font-bold');
            } else {
                fileCountText.textContent = "Multiple files supported";
                fileCountText.classList.remove('text-navy', 'font-bold');
            }
        });

        // Helper to extract error message from any Laravel error response
        async function parseErrorResponse(res) {
            let data;
            try {
                const text = await res.text();
                // If response is HTML (redirect), session has expired
                if (text.startsWith('<!DOCTYPE') || text.startsWith('<html')) {
                    if (res.status === 401 || res.status === 302) {
                        return 'Session expired. Please refresh the page and login again.';
                    }
                    return `Server error (${res.status}). Please refresh the page.`;
                }
                data = JSON.parse(text);
            } catch (e) {
                return `Server error (${res.status}). Please refresh the page.`;
            }

            // Laravel validation errors (422) — has 'errors' object
            if (data.errors) {
                const firstField = Object.keys(data.errors)[0];
                return data.errors[firstField][0];
            }
            // Laravel auth/CSRF errors use 'message' key
            if (res.status === 419) {
                return 'Page expired. Please refresh the page and try again.';
            }
            if (res.status === 401) {
                return 'Session expired. Please refresh the page and login again.';
            }
            // Our custom error responses use 'error' key
            return data.error || data.message || `Request failed (${res.status})`;
        }

        /**
         * Convert a button into a progress bar.
         * @param {HTMLElement} btn - The button element
         * @param {string} label - Text label (e.g. "Uploading")
         * @param {number} estimatedMs - Estimated task duration in ms
         * @returns {object} Controller with update(pct), complete(), stop(origHtml)
         */
        function startBtnProgress(btn, label, estimatedMs = 15000) {
            btn.disabled = true;
            btn.classList.add('btn-progress');
            btn.innerHTML = `<span class="progress-fill"></span><span class="progress-text">${label} 0%</span>`;
            const fill = btn.querySelector('.progress-fill');
            const text = btn.querySelector('.progress-text');
            let pct = 0;
            const step = 300; // update every 300ms
            const maxPct = 95; // never hit 100% until actually done
            const increment = (maxPct / (estimatedMs / step));

            const timer = setInterval(() => {
                pct = Math.min(maxPct, pct + increment);
                fill.style.width = pct + '%';
                text.textContent = `${label} ${Math.round(pct)}%`;
            }, step);

            return {
                update(newPct, newLabel) {
                    pct = newPct;
                    fill.style.width = pct + '%';
                    text.textContent = `${newLabel || label} ${Math.round(pct)}%`;
                },
                complete() {
                    clearInterval(timer);
                    fill.style.width = '100%';
                    text.textContent = `${label} 100%`;
                },
                stop(origHtml) {
                    clearInterval(timer);
                    btn.classList.remove('btn-progress');
                    btn.innerHTML = origHtml;
                    btn.disabled = false;
                }
            };
        }

        // ── Scene-Segmented Script Helpers ──
        window.renderScriptSegments = function renderScriptSegments(scriptData) {
            const container = document.getElementById('adScriptSegments');
            let segments;
            try {
                segments = typeof scriptData === 'string' ? JSON.parse(scriptData) : scriptData;
            } catch(e) {
                // Fallback: treat as plain text
                container.innerHTML = `<div class="bg-white rounded-2xl shadow-soft p-1 border-2 border-coral/20">
                    <textarea id="adScriptText" rows="6" class="w-full bg-transparent border-none p-4 text-navy text-sm leading-relaxed focus:ring-0 resize-none rounded-xl">${scriptData}</textarea>
                </div>`;
                return;
            }

            container.innerHTML = segments.map((seg, i) => {
                const startMin = Math.floor(seg.start / 60);
                const startSec = Math.floor(seg.start % 60);
                const endMin = Math.floor(seg.end / 60);
                const endSec = Math.floor(seg.end % 60);
                const timeLabel = `${startMin}:${String(startSec).padStart(2,'0')} – ${endMin}:${String(endSec).padStart(2,'0')}`;
                return `<div class="bg-white rounded-xl border border-[#E8EDF2] p-3 shadow-sm hover:border-coral/40 transition-colors">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-coral/10 text-coral text-[10px] font-bold">${i+1}</span>
                        <span class="text-[10px] font-mono text-[#94A3B8] tracking-wide">${timeLabel}</span>
                    </div>
                    <textarea data-seg-index="${i}" data-seg-start="${seg.start}" data-seg-end="${seg.end}" rows="2"
                        class="seg-text w-full bg-[#F8FAFC] border border-[#E8EDF2] rounded-lg p-2 text-navy text-xs leading-relaxed focus:ring-1 focus:ring-coral/30 focus:border-coral/30 resize-none">${seg.text || ''}</textarea>
                </div>`;
            }).join('');
        }

        function collectScriptSegments() {
            const textareas = document.querySelectorAll('.seg-text');
            if (textareas.length === 0) {
                // Fallback: old-style single textarea
                const ta = document.getElementById('adScriptText');
                return ta ? ta.value : '';
            }
            const segments = [];
            textareas.forEach(ta => {
                segments.push({
                    scene: parseInt(ta.dataset.segIndex) + 1,
                    start: parseFloat(ta.dataset.segStart),
                    end: parseFloat(ta.dataset.segEnd),
                    text: ta.value.trim()
                });
            });
            return JSON.stringify(segments);
        }

        // Step 1: Upload & Script
        document.getElementById('adUploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!fileInput.files.length) return showToast('Please select video files.');

            const btn = document.getElementById('adUploadBtn');
            const origHtml = btn.innerHTML;
            const progress = startBtnProgress(btn, 'Uploading', 5000);

            const formData = new FormData();
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('media[]', fileInput.files[i]);
            }
            formData.append('language', document.getElementById('adLanguage').value);
            formData.append('aspect_ratio', document.getElementById('adAspectRatio').value);
            formData.append('highlights', document.getElementById('adHighlights').value);

            try {
                let res = await fetch(BASE + '/api/ad-video/upload', {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: formData
                });
                if (!res.ok) {
                    const errMsg = await parseErrorResponse(res);
                    throw new Error(errMsg);
                }
                let data = await res.json();
                currentJobId = data.job_id;

                progress.update(40, 'Analyzing Video');
                // Now switch to script generation phase
                setTimeout(() => progress.update(50, 'Writing Script'), 1000);
                setTimeout(() => progress.update(60, 'Writing Script'), 3000);

                res = await fetch(BASE + `/api/ad-video/${currentJobId}/script`, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                if (!res.ok) {
                    const errMsg = await parseErrorResponse(res);
                    throw new Error(errMsg);
                }
                data = await res.json();

                progress.complete();
                await new Promise(r => setTimeout(r, 400)); // Brief 100% flash

                document.getElementById('adUploadForm').classList.add('hidden');
                document.getElementById('adScriptSection').classList.remove('hidden');
                renderScriptSegments(data.script);

                const lang = document.getElementById('adLanguage').value;
                const voices = voiceMap[lang] || voiceMap.Nepali;
                document.getElementById('adVoiceModel').innerHTML = voices.map(v => `<option value="${v.id}">${v.name}</option>`).join('');

                showToast('Script generated successfully!', 'success');
            } catch(err) {
                showToast(err.message);
            } finally {
                progress.stop(origHtml);
            }
        });

        // Regenerate Script
        document.getElementById('adRegenerateScriptBtn').addEventListener('click', async function() {
            if (!currentJobId) return;
            const btn = this;
            const origHtml = btn.innerHTML;
            const progress = startBtnProgress(btn, 'Regenerating', 15000);
            try {
                const res = await fetch(BASE + `/api/ad-video/${currentJobId}/script`, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                if (!res.ok) {
                    const errMsg = await parseErrorResponse(res);
                    throw new Error(errMsg);
                }
                const data = await res.json();
                progress.complete();
                await new Promise(r => setTimeout(r, 300));
                renderScriptSegments(data.script);
                showToast('Script regenerated!', 'success');
            } catch(err) {
                showToast(err.message);
            } finally {
                progress.stop(origHtml);
            }
        });

        // Step 2: Render Video
        document.getElementById('adGenerateVideoBtn').addEventListener('click', async function() {
            const jobId = currentJobId || window._rerenderJobId;
            if (!jobId) return showToast('No job selected.');
            currentJobId = jobId;
            const btn = this;
            const origHtml = btn.innerHTML;
            const progress = startBtnProgress(btn, 'Submitting', 3000);

            try {
                const res = await fetch(BASE + `/api/ad-video/${jobId}/generate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        script: collectScriptSegments(),
                        voice_model: document.getElementById('adVoiceModel').value
                    })
                });
                if (!res.ok) {
                    const errMsg = await parseErrorResponse(res);
                    throw new Error(errMsg);
                }
                const data = await res.json();
                progress.complete();
                await new Promise(r => setTimeout(r, 300));

                document.getElementById('adScriptSection').classList.add('hidden');
                document.getElementById('adOutputSection').classList.remove('hidden');
                progress.stop(origHtml);
                
                pollAdStatus();
            } catch(err) {
                showToast(err.message);
                progress.stop(origHtml);
            }
        });

        // Reset
        document.getElementById('adResetBtn').addEventListener('click', function() {
            currentJobId = null;
            window._rerenderJobId = null;
            renderPollCount = 0;
            document.getElementById('adUploadForm').classList.remove('hidden');
            document.getElementById('adScriptSection').classList.add('hidden');
            document.getElementById('adScriptSegments').innerHTML = '';
            document.getElementById('adOutputSection').classList.add('hidden');
            document.getElementById('adVideoContainer').classList.add('hidden');
            document.getElementById('adVideoPlayer').src = '';
            // Reset progress bar
            const progressDiv = document.getElementById('adProgress');
            progressDiv.classList.remove('hidden', 'border-red-100', 'bg-red-50');
            progressDiv.innerHTML = '<h3 class="text-sm font-bold text-navy mb-1" id="adRenderLabel">Rendering Video...</h3><p class="text-xs text-[#94A3B8] mb-4" id="adRenderSubtext">Generating voiceover & mixing</p><div class="w-full bg-[#F0F4F8] rounded-full h-3 overflow-hidden"><div id="adRenderBar" class="h-full rounded-full bg-gradient-to-r from-coral to-orange-400 transition-all duration-500 ease-out" style="width: 0%"></div></div><p class="text-xs font-bold text-coral mt-2" id="adRenderPct">0%</p>';
            fileInput.value = '';
            fileCountText.textContent = "Multiple files supported";
            fileCountText.classList.remove('text-navy', 'font-bold');
        });

        // Download MP4
        document.getElementById('adDownloadBtn').addEventListener('click', async function() {
            const url = this.dataset.url;
            if (!url) return showToast('Video not ready yet.');
            const origText = this.innerHTML;
            this.innerHTML = '<span class="spinner"></span> Downloading...';
            this.disabled = true;
            try {
                const res = await fetch(url);
                const blob = await res.blob();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'yantra_ad_video_' + Date.now() + '.mp4';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
                showToast('Download started!', 'success');
            } catch(err) {
                showToast('Download failed: ' + err.message);
            } finally {
                this.innerHTML = origText;
                this.disabled = false;
            }
        });
        let renderPollCount = 0;
        async function pollAdStatus() {
            if (!currentJobId) return;
            renderPollCount++;
            // Estimated: ~60s render, polling every 3s = ~20 polls. Increment ~5% each poll, cap at 92%.
            const estimatedPct = Math.min(92, renderPollCount * 5);
            const bar = document.getElementById('adRenderBar');
            const pctLabel = document.getElementById('adRenderPct');
            const subtext = document.getElementById('adRenderSubtext');
            if (bar) bar.style.width = estimatedPct + '%';
            if (pctLabel) pctLabel.textContent = estimatedPct + '%';
            // Update subtext based on progress
            if (subtext) {
                if (estimatedPct < 30) subtext.textContent = 'Generating voiceover...';
                else if (estimatedPct < 60) subtext.textContent = 'Processing audio...';
                else if (estimatedPct < 85) subtext.textContent = 'Rendering final video...';
                else subtext.textContent = 'Almost done...';
            }

            try {
                const res = await fetch(BASE + `/api/ad-video/${currentJobId}/status`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const progressDiv = document.getElementById('adProgress');
                
                if (data.status === 'completed') {
                    if (bar) bar.style.width = '100%';
                    if (pctLabel) pctLabel.textContent = '100%';
                    if (subtext) subtext.textContent = 'Complete!';
                    await new Promise(r => setTimeout(r, 600));
                    progressDiv.classList.add('hidden');
                    const video = document.getElementById('adVideoPlayer');
                    video.src = data.video_url;
                    document.getElementById('adVideoContainer').classList.remove('hidden');
                    document.getElementById('adDownloadBtn').dataset.url = data.video_url;
                    if (data.script) {
                        document.getElementById('adEditRerenderBtn').dataset.script = data.script;
                    }
                    if (data.voice_model) {
                        document.getElementById('adEditRerenderBtn').dataset.voice = data.voice_model;
                    }
                    renderPollCount = 0;
                    showToast('🎉 Video ready!', 'success');
                } else if (data.status === 'failed') {
                    renderPollCount = 0;
                    progressDiv.classList.add('border-red-100', 'bg-red-50');
                    progressDiv.innerHTML = '<div class="text-2xl mb-2">❌</div><h3 class="text-sm font-bold text-red-600">Generation Failed</h3><p class="text-xs text-red-500 mt-1">' + (data.error || 'Unknown error') + '</p><button onclick="document.getElementById(\'adResetBtn\').click()" class="mt-4 text-xs font-bold text-navy hover:underline">Try Again</button>';
                } else {
                    setTimeout(pollAdStatus, 3000);
                }
            } catch(err) {
                setTimeout(pollAdStatus, 5000);
            }
        }

        // Edit & Re-render: go back to script step with existing script
        document.getElementById('adEditRerenderBtn').addEventListener('click', async function() {
            if (!currentJobId) return showToast('No video to edit.');
            
            const btn = this;
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Loading...';

            try {
                // Fetch current script from API
                const res = await fetch(BASE + `/api/ad-video/${currentJobId}/status`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                
                if (!data.script) throw new Error('No script found for this video.');

                // Hide output, show script section
                document.getElementById('adOutputSection').classList.add('hidden');
                document.getElementById('adUploadForm').classList.add('hidden');
                document.getElementById('adScriptSection').classList.remove('hidden');

                // Load the segments into the editor
                renderScriptSegments(data.script);

                // Populate and set voice model
                const voiceSelect = document.getElementById('adVoiceModel');
                if (voiceSelect && voiceSelect.options.length === 0) {
                    const allVoices = [{id:'Kore',n:'सरस्वती (Kore)'},{id:'Aoede',n:'अन्नपूर्णा (Aoede)'},{id:'Leda',n:'लक्ष्मी (Leda)'},{id:'Zephyr',n:'बायु (Zephyr)'},{id:'Puck',n:'राजेश (Puck)'},{id:'Charon',n:'सुर्य (Charon)'},{id:'Fenrir',n:'भीम (Fenrir)'},{id:'Enceladus',n:'गणेश (Enceladus)'},{id:'Achernar',n:'तारा (Achernar)'},{id:'Orus',n:'अर्जुन (Orus)'},{id:'Callirrhoe',n:'सरस्वती (Callirrhoe)'},{id:'Autonoe',n:'रूपा (Autonoe)'},{id:'Iapetus',n:'प्रताप (Iapetus)'},{id:'Umbriel',n:'छाया (Umbriel)'},{id:'Algenib',n:'दीपक (Algenib)'}];
                    voiceSelect.innerHTML = allVoices.map(v => `<option value="${v.id}">${v.n}</option>`).join('');
                }
                if (data.voice_model && voiceSelect) {
                    for (let opt of voiceSelect.options) {
                        if (opt.value === data.voice_model) { opt.selected = true; break; }
                    }
                }

                showToast('Edit the voiceover script below, then click Approve & Render.', 'success');
            } catch(err) {
                showToast(err.message || 'Failed to load script.');
            } finally {
                btn.innerHTML = origHtml;
                btn.disabled = false;
            }
        });
    });

    // ── Library Functions (outside DOMContentLoaded so switchTab can call it) ──
    async function loadLibrary() {
        const BASE = "{{ url('/') }}";
        const grid = document.getElementById('libraryGrid');
        grid.innerHTML = '<div class="text-center py-12"><div class="inline-block w-6 h-6 border-3 border-coral border-t-transparent rounded-full animate-spin"></div></div>';

        try {
            const res = await fetch(BASE + '/api/ad-video/library', {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            const videos = data.videos || [];

            if (videos.length === 0) {
                grid.innerHTML = '<div class="text-center py-16"><div class="text-3xl mb-3">🎬</div><p class="text-sm text-[#94A3B8]">No videos yet. Create your first ad!</p></div>';
                return;
            }

            grid.innerHTML = videos.map(v => {
                const statusBadge = {
                    'completed': '<span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[9px] font-bold">Done</span>',
                    'processing_video': '<span class="inline-block px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 text-[9px] font-bold animate-pulse">Rendering...</span>',
                    'failed': '<span class="inline-block px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-[9px] font-bold">Failed</span>',
                    'pending_approval': '<span class="inline-block px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 text-[9px] font-bold">Pending</span>',
                }[v.status] || '';

                let actions = '';
                if (v.status === 'completed' && v.video_url) {
                    actions = `
                        <button onclick="downloadLibraryVideo('${v.video_url}')" class="flex-1 py-2 bg-navy hover:bg-[#2A3548] text-white text-[10px] font-bold rounded-full transition-colors">
                            ⬇️ Download
                        </button>
                        <button onclick="rerenderFromLibrary(${v.id})" class="flex-1 py-2 bg-white hover:bg-[#F0F4F8] text-navy text-[10px] font-bold rounded-full transition-colors border border-coral/30">
                            ✏️ Re-render
                        </button>`;
                } else if (v.status === 'failed') {
                    actions = `<span class="text-[10px] text-red-400">${v.error || 'Unknown error'}</span>`;
                }

                return `<div class="bg-white rounded-xl border border-[#E8EDF2] p-3 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm">${v.status === 'completed' ? '🎥' : v.status === 'failed' ? '❌' : '⏳'}</span>
                            <div>
                                <div class="text-xs font-bold text-navy">${v.duration || '?'}s · ${v.language || 'English'}</div>
                                <div class="text-[10px] text-[#94A3B8]">${v.created_at}</div>
                            </div>
                        </div>
                        ${statusBadge}
                    </div>
                    ${v.status === 'completed' && v.video_url ? `<video src="${v.video_url}" class="w-full rounded-lg bg-slate-900 aspect-video object-cover mb-2" preload="metadata"></video>` : ''}
                    <div class="flex gap-2">${actions}</div>
                </div>`;
            }).join('');
        } catch(err) {
            grid.innerHTML = '<div class="text-center py-12 text-red-400 text-sm">Failed to load library</div>';
        }
    }

    function downloadLibraryVideo(url) {
        fetch(url).then(r => r.blob()).then(blob => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'yantra_ad_' + Date.now() + '.mp4';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }).catch(() => alert('Download failed'));
    }

    async function rerenderFromLibrary(jobId) {
        const BASE = "{{ url('/') }}";
        switchTab('advideo');

        // Brief delay for tab switch
        await new Promise(r => setTimeout(r, 150));

        try {
            // Fetch script from API
            const res = await fetch(BASE + `/api/ad-video/${jobId}/status`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!data.script) throw new Error('No script found.');

            window._rerenderJobId = jobId;

            document.getElementById('adUploadForm').classList.add('hidden');
            document.getElementById('adOutputSection').classList.add('hidden');
            document.getElementById('adScriptSection').classList.remove('hidden');
            renderScriptSegments(data.script);

            // Populate and set voice
            const voiceSelect = document.getElementById('adVoiceModel');
            if (voiceSelect) {
                if (voiceSelect.options.length === 0) {
                    const allVoices = [{id:'Kore',n:'सरस्वती (Kore)'},{id:'Aoede',n:'अन्नपूर्णा (Aoede)'},{id:'Leda',n:'लक्ष्मी (Leda)'},{id:'Zephyr',n:'बायु (Zephyr)'},{id:'Puck',n:'राजेश (Puck)'},{id:'Charon',n:'सुर्य (Charon)'},{id:'Fenrir',n:'भीम (Fenrir)'},{id:'Enceladus',n:'गणेश (Enceladus)'},{id:'Achernar',n:'तारा (Achernar)'},{id:'Orus',n:'अर्जुन (Orus)'},{id:'Callirrhoe',n:'सरस्वती (Callirrhoe)'},{id:'Autonoe',n:'रूपा (Autonoe)'},{id:'Iapetus',n:'प्रताप (Iapetus)'},{id:'Umbriel',n:'छाया (Umbriel)'},{id:'Algenib',n:'दीपक (Algenib)'}];
                    voiceSelect.innerHTML = allVoices.map(v => `<option value="${v.id}">${v.n}</option>`).join('');
                }
                if (data.voice_model) {
                    for (let opt of voiceSelect.options) {
                        if (opt.value === data.voice_model) { opt.selected = true; break; }
                    }
                }
            }
        } catch(err) {
            alert('Failed to load script: ' + err.message);
        }
    }

    // ── Credit Purchase Functions ──────────────────────────
    let selectedPackageAmount = 0;

    function openBuyCreditsPopup() {
        document.getElementById('buyCreditsPopup').classList.remove('hidden');
        document.getElementById('popupStep1').classList.remove('hidden');
        document.getElementById('popupStep2').classList.add('hidden');
    }

    function closeBuyCreditsPopup() {
        document.getElementById('buyCreditsPopup').classList.add('hidden');
        selectedPackageAmount = 0;
    }

    function backToPackages() {
        document.getElementById('popupStep1').classList.remove('hidden');
        document.getElementById('popupStep2').classList.add('hidden');
    }

    function selectCreditPackage(amount, bonus, total) {
        selectedPackageAmount = amount;
        document.getElementById('modalPayAmount').textContent = '₨' + amount.toLocaleString();
        document.getElementById('modalGetCredits').textContent = total.toLocaleString() + ' credits';
        document.getElementById('screenshotInput').value = '';
        document.getElementById('screenshotPreview').classList.add('hidden');

        // Show step 2, hide step 1
        document.getElementById('popupStep1').classList.add('hidden');
        document.getElementById('popupStep2').classList.remove('hidden');

        // Load QR
        const BASE = "{{ url('/') }}";
        const qrImg = document.getElementById('qrImage');
        const qrDl = document.getElementById('qrDownloadBtn');
        const qrLoading = document.getElementById('qrLoading');
        qrImg.classList.add('hidden');
        qrDl.classList.add('hidden');
        qrLoading.classList.remove('hidden');

        fetch(BASE + '/api/credit-purchase/qr', { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(data => {
                qrLoading.classList.add('hidden');
                if (data.qr_url) {
                    qrImg.src = data.qr_url;
                    qrImg.classList.remove('hidden');
                    qrDl.href = data.qr_url;
                    qrDl.classList.remove('hidden');
                } else {
                    document.getElementById('qrSection').innerHTML = '<p class="text-xs text-red-400 py-4">⚠️ Payment QR not configured. Contact admin.</p>';
                }
            })
            .catch(() => {
                qrLoading.classList.add('hidden');
                document.getElementById('qrSection').innerHTML = '<p class="text-xs text-red-400 py-4">⚠️ Failed to load QR.</p>';
            });
    }

    // Screenshot preview
    document.addEventListener('DOMContentLoaded', function() {
        const ssInput = document.getElementById('screenshotInput');
        if (ssInput) {
            ssInput.addEventListener('change', function() {
                const file = this.files[0];
                const preview = document.getElementById('screenshotPreview');
                if (file) {
                    const reader = new FileReader();
                    reader.onload = e => { preview.src = e.target.result; preview.classList.remove('hidden'); };
                    reader.readAsDataURL(file);
                } else {
                    preview.classList.add('hidden');
                }
            });
        }
        // Load pending purchases on dashboard
        loadPurchaseHistory();
    });

    async function submitPurchaseRequest() {
        const BASE = "{{ url('/') }}";
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const fileInput = document.getElementById('screenshotInput');
        const btn = document.getElementById('submitPurchaseBtn');

        if (!selectedPackageAmount) return;
        if (!fileInput.files[0]) {
            alert('Please upload a payment screenshot.');
            return;
        }

        const formData = new FormData();
        formData.append('package_amount', selectedPackageAmount);
        formData.append('screenshot', fileInput.files[0]);

        btn.disabled = true;
        btn.querySelector('.progress-text').textContent = 'Submitting...';

        try {
            const res = await fetch(BASE + '/api/credit-purchase', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Failed to submit.');

            closeBuyCreditsPopup();
            alert('✅ ' + data.message);
            loadPurchaseHistory();
        } catch(err) {
            alert('❌ ' + err.message);
        } finally {
            btn.disabled = false;
            btn.querySelector('.progress-text').textContent = 'Submit Purchase Request';
        }
    }

    async function loadPurchaseHistory() {
        const BASE = "{{ url('/') }}";
        try {
            const res = await fetch(BASE + '/api/credit-purchase/history', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            const purchases = data.purchases || [];
            const pending = purchases.filter(p => p.status === 'pending');

            const container = document.getElementById('pendingPurchases');
            const list = document.getElementById('pendingPurchasesList');

            if (pending.length > 0) {
                container.classList.remove('hidden');
                list.innerHTML = pending.map(p => `
                    <div class="px-4 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-navy">₨${p.package_amount.toLocaleString()} package</p>
                            <p class="text-[10px] text-[#94A3B8]">${p.total_credits.toLocaleString()} credits · ${p.created_at}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 text-[9px] font-bold">Pending</span>
                    </div>
                `).join('');
            } else {
                container.classList.add('hidden');
            }
        } catch(e) { /* silent */ }
    }
</script>
</body>
</html>

