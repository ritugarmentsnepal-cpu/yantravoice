@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">

    {{-- ── Hero Header ───────────────────────────────────── --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-[#0f0c29] via-[#302b63] to-[#24243e] rounded-3xl p-8 mb-8 shadow-2xl">
        <div class="absolute -right-16 -top-16 w-64 h-64 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-cyan-500/15 rounded-full blur-3xl"></div>
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-500 to-cyan-400 flex items-center justify-center text-2xl shadow-lg shadow-purple-500/30">🎥</div>
                <div>
                    <h1 class="text-3xl font-extrabold text-white tracking-tight">Text to Video</h1>
                    <p class="text-purple-200/70 text-sm font-medium">Powered by Google Veo 3.1 via OpenRouter</p>
                </div>
            </div>
            <p class="text-white/60 text-sm max-w-xl mt-2">Generate stunning AI videos from text descriptions. Choose your model, resolution, duration, and aspect ratio — or upload images for precise frame control.</p>
        </div>
    </div>

    {{-- ── Alert Container ────────────────────────────────── --}}
    <div id="t2v-alert" class="hidden mb-6 p-4 rounded-2xl text-sm font-medium"></div>

    {{-- ── Generation Form ────────────────────────────────── --}}
    <div id="t2v-form-wrapper" class="bg-gray-800/90 backdrop-blur rounded-3xl p-8 shadow-xl border border-gray-700/50 mb-8">
        <form id="t2v-form" enctype="multipart/form-data">
            @csrf

            {{-- Prompt --}}
            <div class="mb-6">
                <label for="prompt" class="block text-sm font-bold text-gray-200 mb-2">✨ Video Prompt</label>
                <textarea id="prompt" name="prompt" rows="4" maxlength="2000"
                    class="w-full bg-gray-900/80 border border-gray-600/50 rounded-2xl p-4 text-white text-sm placeholder-gray-500 focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none transition-all"
                    placeholder="Describe the video you want to create... e.g. 'A cinematic drone shot over misty mountains at sunrise, golden light breaking through clouds, 4K quality'"></textarea>
                <div class="flex justify-between mt-1.5">
                    <p class="text-[10px] text-gray-500">Be detailed — describe scene, camera movement, lighting, mood, and style</p>
                    <span id="charCount" class="text-[10px] text-gray-500 font-mono">0 / 2000</span>
                </div>
            </div>

            {{-- Negative Prompt (collapsible) --}}
            <div class="mb-6">
                <button type="button" id="toggleNegPrompt" class="flex items-center gap-2 text-xs font-semibold text-gray-400 hover:text-gray-200 transition-colors">
                    <svg id="negPromptArrow" class="w-3.5 h-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    Negative Prompt (optional)
                </button>
                <div id="negPromptWrap" class="hidden mt-3">
                    <textarea id="negative_prompt" name="negative_prompt" rows="2" maxlength="500"
                        class="w-full bg-gray-900/80 border border-gray-600/50 rounded-xl p-3 text-white text-sm placeholder-gray-500 focus:ring-2 focus:ring-red-500/40 focus:border-transparent outline-none resize-none transition-all"
                        placeholder="What to avoid... e.g. 'blurry, low quality, distorted faces, text overlay'"></textarea>
                    <p class="text-[10px] text-gray-500 mt-1">Specify things you do NOT want in the video</p>
                </div>
            </div>

            {{-- Settings Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-6">

                {{-- Model Variant --}}
                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">🤖 Model Variant</label>
                    <select id="model_variant" name="model_variant" class="w-full bg-gray-900/80 border border-gray-600/50 rounded-xl p-3 text-white text-sm focus:ring-2 focus:ring-purple-500 outline-none appearance-none cursor-pointer">
                        <option value="veo-3.1" selected>Veo 3.1 — Premium Quality</option>
                        <option value="veo-3.1-fast">Veo 3.1 Fast — Speed Optimized</option>
                        <option value="veo-3.1-lite">Veo 3.1 Lite — Cost Effective</option>
                    </select>
                    <p id="modelInfo" class="text-[10px] text-purple-400/80 mt-1.5 font-medium">Best cinematic quality, native audio sync</p>
                </div>

                {{-- Aspect Ratio --}}
                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">📐 Aspect Ratio</label>
                    <div class="flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="aspect_ratio" value="16:9" checked class="hidden peer">
                            <div class="peer-checked:border-purple-500 peer-checked:bg-purple-500/10 peer-checked:shadow-lg peer-checked:shadow-purple-500/10 border-2 border-gray-600/50 rounded-xl p-3 text-center transition-all hover:border-gray-500">
                                <div class="w-14 h-9 bg-gray-700 rounded-lg mx-auto mb-1.5 border border-gray-600"></div>
                                <span class="text-xs font-bold text-gray-200">16:9</span>
                                <p class="text-[9px] text-gray-500">Landscape</p>
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="aspect_ratio" value="9:16" class="hidden peer">
                            <div class="peer-checked:border-purple-500 peer-checked:bg-purple-500/10 peer-checked:shadow-lg peer-checked:shadow-purple-500/10 border-2 border-gray-600/50 rounded-xl p-3 text-center transition-all hover:border-gray-500">
                                <div class="w-9 h-14 bg-gray-700 rounded-lg mx-auto mb-1.5 border border-gray-600"></div>
                                <span class="text-xs font-bold text-gray-200">9:16</span>
                                <p class="text-[9px] text-gray-500">Portrait</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Resolution --}}
                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">🖥️ Resolution</label>
                    <div class="flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="resolution" value="720p" checked class="hidden peer">
                            <div class="peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 border-2 border-gray-600/50 rounded-xl p-3.5 text-center transition-all hover:border-gray-500">
                                <span class="text-sm font-extrabold text-gray-200">720p</span>
                                <p class="text-[9px] text-gray-500 mt-0.5">HD · Fast</p>
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="resolution" value="1080p" class="hidden peer">
                            <div class="peer-checked:border-cyan-500 peer-checked:bg-cyan-500/10 border-2 border-gray-600/50 rounded-xl p-3.5 text-center transition-all hover:border-gray-500">
                                <span class="text-sm font-extrabold text-gray-200">1080p</span>
                                <p class="text-[9px] text-gray-500 mt-0.5">Full HD</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Duration Pills --}}
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-300 mb-3">⏱️ Duration</label>
                <div class="flex gap-3">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="duration" value="4" class="hidden peer">
                        <div class="peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:shadow-lg peer-checked:shadow-amber-500/10 border-2 border-gray-600/50 rounded-xl py-4 text-center transition-all hover:border-gray-500">
                            <span class="text-2xl font-extrabold text-gray-200">4</span>
                            <span class="text-xs text-gray-400 font-medium ml-0.5">sec</span>
                            <p class="text-[9px] text-gray-500 mt-1">Quick Clip</p>
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="duration" value="6" class="hidden peer">
                        <div class="peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:shadow-lg peer-checked:shadow-amber-500/10 border-2 border-gray-600/50 rounded-xl py-4 text-center transition-all hover:border-gray-500">
                            <span class="text-2xl font-extrabold text-gray-200">6</span>
                            <span class="text-xs text-gray-400 font-medium ml-0.5">sec</span>
                            <p class="text-[9px] text-gray-500 mt-1">Standard</p>
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" name="duration" value="8" checked class="hidden peer">
                        <div class="peer-checked:border-amber-500 peer-checked:bg-amber-500/10 peer-checked:shadow-lg peer-checked:shadow-amber-500/10 border-2 border-gray-600/50 rounded-xl py-4 text-center transition-all hover:border-gray-500">
                            <span class="text-2xl font-extrabold text-gray-200">8</span>
                            <span class="text-xs text-gray-400 font-medium ml-0.5">sec</span>
                            <p class="text-[9px] text-gray-500 mt-1">Extended</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Reference Image / Ingredients Zone --}}
            <div id="frameUploadZone" class="mb-6">
                <label class="block text-xs font-bold text-gray-300 mb-3">🖼️ Reference Image / Ingredients (Optional)</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- First Frame --}}
                    <div class="border-2 border-dashed border-gray-600/50 rounded-2xl p-5 text-center hover:border-purple-500/50 transition-colors">
                        <div class="text-3xl mb-2">🎞️</div>
                        <h4 class="text-sm font-bold text-gray-200 mb-1">First Frame</h4>
                        <p class="text-[10px] text-gray-500 mb-3">Starting image for animation</p>
                        <input type="file" name="first_frame" id="first_frame" accept="image/*" class="hidden">
                        <label for="first_frame" class="inline-block px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-xs font-bold rounded-lg cursor-pointer transition-colors">
                            Choose Image
                        </label>
                        <p id="firstFrameName" class="text-[10px] text-green-400 mt-2 hidden"></p>
                        <img id="firstFramePreview" class="hidden mt-3 mx-auto max-h-32 rounded-lg border border-gray-600" />
                    </div>

                    {{-- Last Frame --}}
                    <div class="border-2 border-dashed border-gray-600/50 rounded-2xl p-5 text-center hover:border-purple-500/50 transition-colors">
                        <div class="text-3xl mb-2">🏁</div>
                        <h4 class="text-sm font-bold text-gray-200 mb-1">Last Frame <span class="text-gray-500 font-normal">(Optional)</span></h4>
                        <p class="text-[10px] text-gray-500 mb-3">Target ending image</p>
                        <input type="file" name="last_frame" id="last_frame" accept="image/*" class="hidden">
                        <label for="last_frame" class="inline-block px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-xs font-bold rounded-lg cursor-pointer transition-colors">
                            Choose Image
                        </label>
                        <p id="lastFrameName" class="text-[10px] text-green-400 mt-2 hidden"></p>
                        <img id="lastFramePreview" class="hidden mt-3 mx-auto max-h-32 rounded-lg border border-gray-600" />
                    </div>
                </div>
                <p class="text-[10px] text-gray-500 mt-2">Supported formats: JPEG, PNG, WebP · Max 10MB each</p>
            </div>

            {{-- Submit Bar --}}
            <div class="flex items-center justify-between pt-6 border-t border-gray-700/50">
                <div class="text-sm text-gray-400">
                    <span class="font-bold text-purple-400" id="user-credits">{{ auth()->user()->credits ?? 0 }}</span> credits available
                    <span class="text-gray-600 mx-1">·</span>
                    Cost: <span class="font-bold text-amber-400">{{ $cost }}</span> credits
                </div>
                <button type="submit" id="generate-btn"
                    class="px-8 py-3.5 bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 text-white font-bold text-sm rounded-2xl transition-all active:scale-[0.97] shadow-lg shadow-purple-500/25 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="btn-text">Generate Video</span>
                    <svg id="btn-spinner" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    {{-- ── Progress Tracker ────────────────────────────────── --}}
    <div id="t2v-progress" class="hidden mb-8">
        <div class="bg-gray-800/90 backdrop-blur rounded-3xl p-8 shadow-xl border border-gray-700/50">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-purple-500/20 to-cyan-500/20 mb-4">
                    <div class="w-10 h-10 rounded-full border-4 border-purple-500/30 border-t-purple-500 animate-spin"></div>
                </div>
                <h3 id="progress-title" class="text-xl font-bold text-white mb-2">Initializing...</h3>
                <p id="progress-subtitle" class="text-gray-400 text-sm mb-6">Please don't close this tab. Video generation typically takes 2-5 minutes.</p>
            </div>

            {{-- Progress Steps --}}
            <div class="flex items-center justify-center gap-2 mb-6">
                <div id="step-submit" class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-purple-500 animate-pulse"></div>
                    <span class="text-xs font-medium text-purple-400">Submitting</span>
                </div>
                <div class="w-8 h-px bg-gray-700"></div>
                <div id="step-generate" class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-gray-700"></div>
                    <span class="text-xs font-medium text-gray-600">Generating</span>
                </div>
                <div class="w-8 h-px bg-gray-700"></div>
                <div id="step-download" class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-gray-700"></div>
                    <span class="text-xs font-medium text-gray-600">Downloading</span>
                </div>
                <div class="w-8 h-px bg-gray-700"></div>
                <div id="step-done" class="flex items-center gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-gray-700"></div>
                    <span class="text-xs font-medium text-gray-600">Done</span>
                </div>
            </div>

            <div class="w-full bg-gray-900 rounded-full h-2 overflow-hidden">
                <div id="progress-bar" class="h-full rounded-full bg-gradient-to-r from-purple-500 to-cyan-400 transition-all duration-700 ease-out" style="width: 5%"></div>
            </div>
        </div>
    </div>

    {{-- ── Result Player ──────────────────────────────────── --}}
    <div id="t2v-result" class="hidden mb-8">
        <div class="bg-gray-800/90 backdrop-blur rounded-3xl overflow-hidden shadow-2xl border border-gray-700/50">
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <span class="inline-block px-3 py-1 bg-green-900/50 text-green-300 text-xs font-bold rounded-full border border-green-700/30">✅ Generation Complete</span>
                </div>

                {{-- Video Player --}}
                <div id="result-video-wrapper" class="bg-black rounded-2xl overflow-hidden shadow-2xl mb-6 mx-auto" style="max-width: 720px;">
                    <video id="result-video" controls autoplay loop class="w-full" playsinline>
                        Your browser does not support the video tag.
                    </video>
                </div>

                {{-- Actions --}}
                <div class="flex flex-wrap gap-3 justify-center">
                    <a id="download-btn" href="#" download class="px-6 py-3 bg-gradient-to-r from-purple-600 to-cyan-500 text-white font-bold text-sm rounded-xl hover:shadow-lg hover:shadow-purple-500/25 transition-all flex items-center gap-2">
                        📥 Download MP4
                    </a>
                    <button onclick="resetForm()" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white font-bold text-sm rounded-xl transition-colors flex items-center gap-2">
                        ✨ Generate Another
                    </button>
                </div>

                {{-- Prompt Summary --}}
                <div class="mt-6 p-4 bg-gray-900/60 rounded-xl border border-gray-700/30">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Prompt</p>
                    <p id="result-prompt" class="text-sm text-gray-300 italic"></p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Generation History ─────────────────────────────── --}}
    @if(isset($jobs) && $jobs->count() > 0)
    <div class="bg-gray-800/90 backdrop-blur rounded-3xl p-8 shadow-xl border border-gray-700/50">
        <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
            📂 My Generations
            <span class="px-2 py-0.5 bg-gray-700 text-gray-400 text-xs font-bold rounded-full">{{ $jobs->count() }}</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-gray-300">
                <thead class="text-[10px] uppercase text-gray-500 tracking-wider">
                    <tr class="border-b border-gray-700/50">
                        <th class="pb-3 pr-4">Prompt</th>
                        <th class="pb-3 pr-4">Model</th>
                        <th class="pb-3 pr-4">Settings</th>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3 pr-4">Date</th>
                        <th class="pb-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/30">
                    @foreach($jobs as $j)
                    <tr class="hover:bg-gray-700/20 transition-colors">
                        <td class="py-4 pr-4 max-w-[200px]">
                            <p class="text-sm text-gray-200 truncate font-medium" title="{{ $j->prompt }}">{{ $j->prompt }}</p>
                        </td>
                        <td class="py-4 pr-4">
                            <span class="text-xs font-mono text-purple-400">{{ $j->model_variant }}</span>
                        </td>
                        <td class="py-4 pr-4">
                            <div class="flex gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 bg-gray-700/60 text-gray-400 text-[10px] font-bold rounded">{{ $j->resolution }}</span>
                                <span class="px-2 py-0.5 bg-gray-700/60 text-gray-400 text-[10px] font-bold rounded">{{ $j->duration }}s</span>
                                <span class="px-2 py-0.5 bg-gray-700/60 text-gray-400 text-[10px] font-bold rounded">{{ $j->aspect_ratio }}</span>
                            </div>
                        </td>
                        <td class="py-4 pr-4">
                            @if($j->status === 'completed')
                                <span class="px-2.5 py-1 bg-green-900/40 text-green-300 text-[10px] font-bold rounded-full border border-green-700/30">Ready</span>
                            @elseif($j->status === 'failed')
                                <span class="px-2.5 py-1 bg-red-900/40 text-red-300 text-[10px] font-bold rounded-full border border-red-700/30" title="{{ $j->error_message }}">Failed</span>
                            @elseif($j->status === 'polling')
                                <span class="px-2.5 py-1 bg-amber-900/40 text-amber-300 text-[10px] font-bold rounded-full border border-amber-700/30 animate-pulse">Polling...</span>
                            @else
                                <span class="px-2.5 py-1 bg-amber-900/40 text-amber-300 text-[10px] font-bold rounded-full border border-amber-700/30 capitalize">{{ str_replace('_', ' ', $j->status) }}</span>
                            @endif
                        </td>
                        <td class="py-4 pr-4 text-xs text-gray-500">{{ $j->created_at->format('M d, Y') }}</td>
                        <td class="py-4">
                            @if($j->status === 'completed')
                                <a href="{{ route('t2v.show', $j->id) }}" class="text-purple-400 hover:text-purple-300 font-bold text-xs transition-colors">View →</a>
                            @elseif(in_array($j->status, ['pending', 'generating', 'polling']))
                                <span class="flex items-center gap-1.5 text-amber-500/70 text-xs font-medium animate-pulse">
                                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('t2v-form');
    const formWrapper = document.getElementById('t2v-form-wrapper');
    const alertEl = document.getElementById('t2v-alert');
    const progressEl = document.getElementById('t2v-progress');
    const resultEl = document.getElementById('t2v-result');
    const submitBtn = document.getElementById('generate-btn');
    const btnText = document.getElementById('btn-text');
    const btnSpinner = document.getElementById('btn-spinner');
    const charCount = document.getElementById('charCount');
    const promptInput = document.getElementById('prompt');

    // ── Character counter ─────────────────────────────
    promptInput.addEventListener('input', () => {
        charCount.textContent = promptInput.value.length + ' / 2000';
    });

    // ── Model variant info ────────────────────────────
    const modelInfo = document.getElementById('modelInfo');
    const modelSelect = document.getElementById('model_variant');
    const modelDescriptions = {
        'veo-3.1': 'Best cinematic quality, native audio sync',
        'veo-3.1-fast': 'Faster generation, great for rapid iteration',
        'veo-3.1-lite': 'Most cost-effective, ideal for high-volume use',
    };
    modelSelect.addEventListener('change', () => {
        modelInfo.textContent = modelDescriptions[modelSelect.value] || '';
    });

    // ── Negative prompt toggle ────────────────────────
    document.getElementById('toggleNegPrompt').addEventListener('click', () => {
        const wrap = document.getElementById('negPromptWrap');
        const arrow = document.getElementById('negPromptArrow');
        wrap.classList.toggle('hidden');
        arrow.style.transform = wrap.classList.contains('hidden') ? '' : 'rotate(90deg)';
    });

    // ── File input previews ───────────────────────────
    function setupFilePreview(inputId, nameId, previewId) {
        const input = document.getElementById(inputId);
        const nameEl = document.getElementById(nameId);
        const previewEl = document.getElementById(previewId);
        input.addEventListener('change', () => {
            if (input.files.length > 0) {
                nameEl.textContent = '✓ ' + input.files[0].name;
                nameEl.classList.remove('hidden');
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewEl.src = e.target.result;
                    previewEl.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        });
    }
    setupFilePreview('first_frame', 'firstFrameName', 'firstFramePreview');
    setupFilePreview('last_frame', 'lastFrameName', 'lastFramePreview');

    // ── Alert helper ──────────────────────────────────
    function showAlert(msg, isError = false) {
        alertEl.classList.remove('hidden', 'bg-red-900/50', 'text-red-200', 'bg-green-900/50', 'text-green-200',
            'border', 'border-red-500/30', 'border-green-500/30');
        if (isError) {
            alertEl.classList.add('bg-red-900/50', 'text-red-200', 'border', 'border-red-500/30');
        } else {
            alertEl.classList.add('bg-green-900/50', 'text-green-200', 'border', 'border-green-500/30');
        }
        alertEl.textContent = msg;
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ── Progress step UI ──────────────────────────────
    function setProgressStep(step, percent) {
        const steps = ['submit', 'generate', 'download', 'done'];
        const titles = {
            submit: 'Submitting to OpenRouter...',
            generate: 'Veo 3.1 is generating your video...',
            download: 'Downloading your video...',
            done: 'Complete!'
        };
        const subtitles = {
            submit: 'Sending your prompt and settings to the AI model.',
            generate: 'This may take 2-5 minutes. Sit tight!',
            download: 'Saving the final video to your account.',
            done: 'Your video is ready to play!'
        };

        document.getElementById('progress-title').textContent = titles[step] || 'Processing...';
        document.getElementById('progress-subtitle').textContent = subtitles[step] || '';
        document.getElementById('progress-bar').style.width = percent + '%';

        const activeIdx = steps.indexOf(step);
        steps.forEach((s, i) => {
            const dot = document.querySelector(`#step-${s} div`);
            const label = document.querySelector(`#step-${s} span`);
            if (i < activeIdx) {
                dot.className = 'w-3 h-3 rounded-full bg-green-500';
                label.className = 'text-xs font-medium text-green-400';
            } else if (i === activeIdx) {
                dot.className = 'w-3 h-3 rounded-full bg-purple-500 animate-pulse';
                label.className = 'text-xs font-medium text-purple-400';
            } else {
                dot.className = 'w-3 h-3 rounded-full bg-gray-700';
                label.className = 'text-xs font-medium text-gray-600';
            }
        });
    }

    // ── Status polling ────────────────────────────────
    function pollJobStatus(jobId) {
        fetch(`/text-to-video/status/${jobId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'completed') {
                    setProgressStep('done', 100);
                    setTimeout(() => {
                        progressEl.classList.add('hidden');
                        resultEl.classList.remove('hidden');
                        document.getElementById('result-video').src = data.video_path;
                        document.getElementById('download-btn').href = data.video_path;
                        document.getElementById('result-prompt').textContent = promptInput.value;
                        resultEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 800);
                } else if (data.status === 'failed') {
                    progressEl.classList.add('hidden');
                    formWrapper.classList.remove('hidden');
                    setLoading(false);
                    showAlert('Generation failed: ' + (data.error || 'Unknown error'), true);
                } else if (data.status === 'generating') {
                    setProgressStep('submit', 25);
                    setTimeout(() => pollJobStatus(jobId), 5000);
                } else if (data.status === 'polling') {
                    setProgressStep('generate', 50);
                    setTimeout(() => pollJobStatus(jobId), 5000);
                } else {
                    setTimeout(() => pollJobStatus(jobId), 5000);
                }
            })
            .catch(err => {
                // Network error — retry
                setTimeout(() => pollJobStatus(jobId), 8000);
            });
    }

    // ── Loading state ─────────────────────────────────
    function setLoading(isLoading) {
        submitBtn.disabled = isLoading;
        if (isLoading) {
            btnText.textContent = 'Processing...';
            btnSpinner.classList.remove('hidden');
        } else {
            btnText.textContent = 'Generate Video';
            btnSpinner.classList.add('hidden');
        }
    }

    // ── Form submit ───────────────────────────────────
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!promptInput.value.trim()) {
            showAlert('Please enter a video description.', true);
            return;
        }

        setLoading(true);
        alertEl.classList.add('hidden');

        const formData = new FormData(form);

        fetch('/text-to-video/generate', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                setLoading(false);
                showAlert(data.message || 'An error occurred.', true);
            } else {
                // Show progress tracker
                formWrapper.classList.add('hidden');
                resultEl.classList.add('hidden');
                progressEl.classList.remove('hidden');
                progressEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setProgressStep('submit', 15);

                // Start polling
                pollJobStatus(data.job_id);
            }
        })
        .catch(err => {
            setLoading(false);
            showAlert('Network error. Please try again.', true);
        });
    });

    // ── Reset form ────────────────────────────────────
    window.resetForm = function() {
        resultEl.classList.add('hidden');
        progressEl.classList.add('hidden');
        formWrapper.classList.remove('hidden');
        setLoading(false);
        promptInput.value = '';
        charCount.textContent = '0 / 2000';
        document.getElementById('negative_prompt').value = '';
        formWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
});
</script>
@endsection
