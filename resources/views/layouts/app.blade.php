<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Yantra Studio - UGC Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <nav class="bg-gray-800 border-b border-gray-700 px-6 py-4 flex justify-between items-center">
        <div class="text-xl font-extrabold text-white">Yantra Studio</div>
        <div class="flex items-center gap-4">
            <a href="{{ route('t2v.index') }}" class="text-sm {{ request()->is('text-to-video*') ? 'text-purple-400 font-bold' : 'text-gray-400 hover:text-white' }} transition-colors">🎥 Text-to-Video</a>
            <a href="{{ route('ugc.index') }}" class="text-sm {{ request()->is('ugc*') ? 'text-orange-400 font-bold' : 'text-gray-400 hover:text-white' }} transition-colors">🎬 UGC</a>
            <a href="{{ url('/studio') }}" class="text-sm text-gray-400 hover:text-white transition-colors">← Studio</a>
        </div>
    </nav>
    <main>
        @yield('content')
    </main>
</body>
</html>
