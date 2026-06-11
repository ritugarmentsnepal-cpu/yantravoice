@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-10">
    <div class="mb-6">
        <a href="{{ route('ugc.index') }}" class="text-gray-400 hover:text-white flex items-center space-x-2">
            <span>&larr;</span> <span>Back to Dashboard</span>
        </a>
    </div>

    <div class="bg-gray-800 rounded-xl p-8 shadow-xl border border-gray-700 flex flex-col md:flex-row gap-8">
        
        <!-- Video Player -->
        <div class="flex-shrink-0 w-full md:w-[350px] mx-auto bg-black rounded-2xl overflow-hidden shadow-2xl relative" style="aspect-ratio: 9/16;">
            <iframe id="final-video-iframe" src="{{ route('ugc.editor', $job->id) }}" class="w-full h-full object-cover border-0" allow="autoplay; fullscreen"></iframe>
        </div>

        <!-- Details & Actions -->
        <div class="flex-grow flex flex-col justify-center">
            <div class="mb-8">
                <span class="inline-block px-3 py-1 bg-green-900 text-green-300 text-xs font-bold rounded-full mb-4">Completed</span>
                <h2 class="text-3xl font-bold text-white mb-2">Your Video is Ready!</h2>
                <p class="text-gray-400 italic">"{{ $job->prompt }}"</p>
            </div>

            <div class="space-y-4">
                <button onclick="document.getElementById('final-video-iframe').contentWindow.location.reload();" class="block w-full text-center px-6 py-4 bg-yellow-500 hover:bg-yellow-400 text-black font-bold rounded-lg transition-colors shadow-lg">
                    🔄 Replay Video
                </button>
                
                <a href="{{ route('ugc.index') }}" class="block w-full text-center px-6 py-4 bg-gray-700 hover:bg-gray-600 text-white font-bold rounded-lg transition-colors">
                    Generate Another
                </a>
            </div>

            <div class="mt-4 p-4 bg-gray-800 rounded-lg border border-gray-700">
                <p class="text-sm text-gray-300 mb-2">
                    <span class="text-yellow-500 font-bold">💡 Tip:</span> Since we use dynamic CSS rendering, use a screen recorder to save this video as an MP4.
                </p>
                <a href="{{ asset('storage/' . $job->output_video_path) }}" download="avatar-{{ $job->id }}.webm" class="text-xs text-blue-400 hover:underline">Download raw transparent avatar (WebM)</a>
            </div>

            <div class="mt-4 p-4 bg-gray-900 rounded-lg text-sm text-gray-500 border border-gray-700">
                <p><strong>Job ID:</strong> #{{ $job->id }}</p>
                <p><strong>Generated:</strong> {{ $job->created_at->format('F j, Y g:i A') }}</p>
                <p><strong>Credits Used:</strong> {{ $job->credits_charged }}</p>
            </div>
        </div>

    </div>
</div>
@endsection
