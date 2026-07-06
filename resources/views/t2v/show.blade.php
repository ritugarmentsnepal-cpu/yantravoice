@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4">

    {{-- Back navigation --}}
    <a href="{{ route('t2v.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-400 hover:text-white font-medium mb-6 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Text-to-Video
    </a>

    {{-- Video Player Card --}}
    <div class="bg-gray-800/90 backdrop-blur rounded-3xl overflow-hidden shadow-2xl border border-gray-700/50 mb-6">
        {{-- Video --}}
        <div class="bg-black">
            <video controls autoplay loop playsinline class="w-full max-h-[500px] mx-auto">
                <source src="{{ asset('storage/' . $job->output_path) }}" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>

        {{-- Info & Actions --}}
        <div class="p-8">
            <div class="flex items-start justify-between flex-wrap gap-4 mb-6">
                <div>
                    <span class="inline-block px-3 py-1 bg-green-900/40 text-green-300 text-[10px] font-bold rounded-full border border-green-700/30 mb-3">✅ Completed</span>
                    <h2 class="text-xl font-bold text-white">Generated Video</h2>
                    <p class="text-gray-500 text-xs mt-1">Created {{ $job->created_at->diffForHumans() }}</p>
                </div>
                <a href="{{ asset('storage/' . $job->output_path) }}" download
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-cyan-500 text-white font-bold text-sm rounded-xl hover:shadow-lg hover:shadow-purple-500/25 transition-all flex items-center gap-2">
                    📥 Download MP4
                </a>
            </div>

            {{-- Prompt --}}
            <div class="bg-gray-900/60 rounded-xl p-5 border border-gray-700/30 mb-5">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-2">Prompt</p>
                <p class="text-sm text-gray-200 leading-relaxed">{{ $job->prompt }}</p>
                @if($job->negative_prompt)
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mt-4 mb-1">Negative Prompt</p>
                    <p class="text-sm text-red-300/70">{{ $job->negative_prompt }}</p>
                @endif
            </div>

            {{-- Metadata Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-gray-900/40 rounded-xl p-4 text-center border border-gray-700/20">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Model</p>
                    <p class="text-sm font-bold text-purple-400">{{ $job->model_variant }}</p>
                </div>
                <div class="bg-gray-900/40 rounded-xl p-4 text-center border border-gray-700/20">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Resolution</p>
                    <p class="text-sm font-bold text-cyan-400">{{ $job->resolution }}</p>
                </div>
                <div class="bg-gray-900/40 rounded-xl p-4 text-center border border-gray-700/20">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Duration</p>
                    <p class="text-sm font-bold text-amber-400">{{ $job->duration }}s</p>
                </div>
                <div class="bg-gray-900/40 rounded-xl p-4 text-center border border-gray-700/20">
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Aspect Ratio</p>
                    <p class="text-sm font-bold text-green-400">{{ $job->aspect_ratio }}</p>
                </div>
            </div>

            {{-- Generation Mode Badge --}}
            @if($job->generation_mode === 'image_to_video')
            <div class="mt-4 p-3 bg-purple-900/20 rounded-xl border border-purple-700/20 text-center">
                <span class="text-xs font-bold text-purple-300">🖼️ Image-to-Video Generation</span>
            </div>
            @endif

            {{-- Credits Info --}}
            <div class="mt-4 flex items-center justify-between text-xs text-gray-500">
                <span>Credits charged: <strong class="text-gray-300">{{ number_format($job->credits_charged, 0) }}</strong></span>
                <span>Job ID: <code class="text-gray-600 font-mono">{{ $job->id }}</code></span>
            </div>
        </div>
    </div>

</div>
@endsection
