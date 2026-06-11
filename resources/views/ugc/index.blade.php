@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-10">
    <div class="bg-gray-800 rounded-xl p-8 shadow-xl border border-gray-700 relative overflow-hidden">
        <h2 class="text-3xl font-bold text-white mb-2">AI UGC Short Generator (Beta)</h2>
        <p class="text-gray-400 mb-8">Paste a product URL or describe what you want to sell, and our AI Director will script and render a high-converting UGC video.</p>

        <div id="alert-container" class="hidden mb-6 p-4 rounded-lg"></div>

        <form id="ugc-form" class="space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Avatar Selection -->
                <div>
                    <label for="avatar_id" class="block text-sm font-medium text-gray-300 mb-2">Presenter Avatar</label>
                    <select id="avatar_id" name="avatar_id" class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white focus:ring-2 focus:ring-yellow-500 outline-none">
                        @if(isset($avatars))
                            @foreach($avatars as $avatar)
                                <option value="{{ $avatar->id }}">{{ $avatar->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Style Preset -->
                <div>
                    <label for="style_preset" class="block text-sm font-medium text-gray-300 mb-2">Editing Style</label>
                    <select id="style_preset" name="style_preset" class="w-full bg-gray-900 border border-gray-600 rounded-lg p-3 text-white focus:ring-2 focus:ring-yellow-500 outline-none">
                        <option value="aggressive_cuts">⚡ Aggressive Cuts (TikTok Growth)</option>
                        <option value="minimalist_tech">🏢 Minimalist Tech (Clean UI)</option>
                        <option value="cinematic">🎬 Cinematic (Storyteller)</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="prompt" class="block text-sm font-medium text-gray-300 mb-2">Product Description or URL</label>
                <textarea id="prompt" name="prompt" rows="4" class="w-full bg-gray-900 border border-gray-600 rounded-lg p-4 text-white focus:ring-2 focus:ring-yellow-500 focus:border-transparent outline-none" placeholder="Enter your video concept or landing page URL..."></textarea>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-700">
                <div class="text-sm text-gray-400">
                    <span class="font-bold text-yellow-500" id="user-credits">{{ auth()->user()->credits ?? 0 }}</span> credits available
                </div>
                <button type="submit" id="generate-btn" class="px-6 py-3 bg-yellow-500 hover:bg-yellow-400 text-black font-bold rounded-lg transition-colors flex items-center space-x-2">
                    <span id="btn-text">Generate Video (50 Credits)</span>
                    <svg id="btn-spinner" class="animate-spin -ml-1 mr-3 h-5 w-5 text-black hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
            </div>
        </form>

        <!-- Dynamic Tracking State -->
        <div id="loading-state" class="hidden mt-10 p-8 border border-gray-700 rounded-xl bg-gray-900 text-center">
            <h3 id="loading-title" class="text-2xl text-white font-bold mb-3">Initializing AI Director...</h3>
            <p class="text-gray-400">This process takes 1-2 minutes. Please don't close this tab.</p>
            <div class="mt-6 w-full bg-gray-800 rounded-full h-2.5">
                <div id="loading-bar" class="bg-yellow-500 h-2.5 rounded-full transition-all duration-500" style="width: 10%"></div>
            </div>
        </div>

        <!-- Inline Video Player (Hidden initially) -->
        <div id="result-player" class="hidden mt-10 flex flex-col md:flex-row gap-8 p-8 border border-gray-700 rounded-xl bg-gray-900">
            <div class="flex-shrink-0 w-full md:w-[350px] mx-auto bg-black rounded-xl overflow-hidden shadow-2xl relative" style="aspect-ratio: 9/16;">
                <iframe id="final-video-iframe" class="w-full h-full object-cover border-0" allow="autoplay; fullscreen"></iframe>
            </div>
            <div class="flex-grow flex flex-col justify-center text-left">
                <span class="inline-block px-3 py-1 bg-green-900 text-green-300 text-xs font-bold rounded-full w-max mb-4">Generation Complete</span>
                <h3 class="text-2xl font-bold text-white mb-4">Your Video is Ready!</h3>
                <p class="text-gray-400 mb-8" id="result-prompt"></p>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700 mb-4">
                    <p class="text-sm text-gray-300">
                        <span class="text-yellow-500 font-bold">💡 Tip:</span> Because your server doesn't have a dedicated GPU rendering cluster, we are delivering the final video to you via our **Dynamic CSS Player**.
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        To save this as an MP4, simply use a screen recorder on your phone or PC while the video plays!
                    </p>
                </div>
                <button onclick="document.getElementById('final-video-iframe').contentWindow.location.reload();" class="inline-block text-center px-6 py-4 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-colors shadow-lg">
                    🔄 Replay Video
                </button>
            </div>
        </div>

    </div>

    @if(isset($jobs) && $jobs->count() > 0)
    <div class="bg-gray-800 rounded-xl p-8 shadow-xl border border-gray-700 mt-8">
        <h3 class="text-2xl font-bold text-white mb-6">My UGC Videos</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-gray-300">
                <thead class="text-sm uppercase bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 rounded-tl-lg">Prompt</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3 rounded-tr-lg">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($jobs as $job)
                    <tr class="border-b border-gray-700">
                        <td class="px-4 py-4 max-w-xs truncate" title="{{ $job->prompt }}">{{ $job->prompt }}</td>
                        <td class="px-4 py-4">
                            @if($job->status === 'completed')
                                <span class="bg-green-900 text-green-300 text-xs font-bold px-2 py-1 rounded">Ready</span>
                            @elseif($job->status === 'failed')
                                <span class="bg-red-900 text-red-300 text-xs font-bold px-2 py-1 rounded">Failed</span>
                            @else
                                <span class="bg-yellow-900 text-yellow-300 text-xs font-bold px-2 py-1 rounded capitalize">{{ str_replace('_', ' ', $job->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-sm">{{ $job->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-4">
                            @if($job->status === 'completed')
                                <a href="{{ route('ugc.show', $job->id) }}" class="text-blue-400 hover:text-blue-300 font-medium text-sm">View Result &rarr;</a>
                            @elseif(in_array($job->status, ['compiling_hyperframes', 'rendering_avatar', 'generating']))
                                <span class="text-gray-500 text-sm">Processing...</span>
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
    const form = document.getElementById('ugc-form');
    const submitBtn = document.getElementById('generate-btn');
    const btnText = document.getElementById('btn-text');
    const btnSpinner = document.getElementById('btn-spinner');
    
    const loadingState = document.getElementById('loading-state');
    const loadingTitle = document.getElementById('loading-title');
    const loadingBar = document.getElementById('loading-bar');
    
    const alertContainer = document.getElementById('alert-container');
    const resultPlayer = document.getElementById('result-player');
    const finalVideoIframe = document.getElementById('final-video-iframe');
    const resultPrompt = document.getElementById('result-prompt');

    function showAlert(message, isError = false) {
        alertContainer.classList.remove('hidden', 'bg-red-900/50', 'text-red-200', 'bg-green-900/50', 'text-green-200');
        if(isError) {
            alertContainer.classList.add('bg-red-900/50', 'text-red-200', 'border', 'border-red-500');
        } else {
            alertContainer.classList.add('bg-green-900/50', 'text-green-200', 'border', 'border-green-500');
        }
        alertContainer.innerText = message;
    }

    function updateProgressUI(status) {
        if(status === 'generating') {
            loadingTitle.innerText = "Structuring Script Blueprint (OpenRouter)...";
            loadingBar.style.width = "25%";
        } else if (status === 'rendering_avatar') {
            loadingTitle.innerText = "Synthesizing Transparent Actor (HeyGen)...";
            loadingBar.style.width = "50%";
        } else if (status === 'compiling_hyperframes') {
            loadingTitle.innerText = "Compiling Kinetic Typography & Layout (Yantra Engine)...";
            loadingBar.style.width = "85%";
        }
    }

    function checkStatus(jobId) {
        fetch(`/ugc/status/${jobId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'completed') {
                    // Show final video iframe
                    loadingState.classList.add('hidden');
                    resultPlayer.classList.remove('hidden');
                    form.classList.add('hidden'); // Hide form
                    
                    finalVideoIframe.src = `/ugc/editor/${jobId}`;
                    
                } else if (data.status === 'failed') {
                    setLoading(false);
                    showAlert('Generation failed: ' + (data.error || 'Unknown error'), true);
                } else {
                    updateProgressUI(data.status);
                    setTimeout(() => checkStatus(jobId), 3000);
                }
            })
            .catch(err => {
                setLoading(false);
                showAlert('Error checking status.', true);
            });
    }

    function setLoading(isLoading) {
        if(isLoading) {
            submitBtn.disabled = true;
            btnText.innerText = 'Processing...';
            btnSpinner.classList.remove('hidden');
            loadingState.classList.remove('hidden');
            resultPlayer.classList.add('hidden');
            form.classList.add('opacity-50', 'pointer-events-none');
            loadingBar.style.width = "10%";
            loadingTitle.innerText = "Initializing AI Director...";
        } else {
            submitBtn.disabled = false;
            btnText.innerText = 'Generate Video (50 Credits)';
            btnSpinner.classList.add('hidden');
            loadingState.classList.add('hidden');
            form.classList.remove('opacity-50', 'pointer-events-none');
        }
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const prompt = document.getElementById('prompt').value;
        const avatar_id = document.getElementById('avatar_id').value;
        const style_preset = document.getElementById('style_preset').value;

        if(!prompt.trim()) {
            showAlert('Please enter a description or URL.', true);
            return;
        }

        setLoading(true);
        alertContainer.classList.add('hidden');
        resultPrompt.innerText = `"${prompt}"`;

        fetch('/ugc/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify({ prompt, avatar_id, style_preset })
        })
        .then(res => res.json())
        .then(data => {
            if(!data.success) {
                setLoading(false);
                showAlert(data.message || 'An error occurred', true);
            } else {
                updateProgressUI('generating');
                checkStatus(data.job_id);
            }
        })
        .catch(err => {
            setLoading(false);
            showAlert('Network error. Please try again.', true);
        });
    });
});
</script>
@endsection
