<?php

namespace App\Http\Controllers;

use App\Models\AdVideoJob;
use App\Models\ApiSetting;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdVideoController extends Controller
{
    /**
     * Auto-detect FFmpeg/FFprobe binary paths across macOS and Linux.
     */
    private static function ffmpegPath(): string
    {
        foreach (['/usr/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $p) {
            if (file_exists($p)) return $p;
        }
        return 'ffmpeg'; // fallback to PATH
    }

    private static function ffprobePath(): string
    {
        foreach (['/usr/bin/ffprobe', '/opt/homebrew/bin/ffprobe', '/usr/local/bin/ffprobe'] as $p) {
            if (file_exists($p)) return $p;
        }
        return 'ffprobe'; // fallback to PATH
    }

    /**
     * Step 1: Upload media and create the job record.
     */
    public function uploadMedia(Request $request)
    {
        try {
            $request->validate([
                'media' => 'required|array|min:1|max:10',
                'media.*' => 'required|file|mimes:mp4,mov,avi,webm,mkv,3gp,m4v,mpeg,mpg,wmv,flv,ogv|max:102400',
                'language' => 'required|string',
                'aspect_ratio' => 'nullable|string|in:original,9:16,16:9,1:1',
                'highlights' => 'nullable|string|max:500',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        }

        try {
            $user = auth()->user();
            $cost = ApiSetting::getVideoRenderCost();

            if ($user->credits < $cost) {
                return response()->json(['error' => 'Insufficient credits. Cost: ' . $cost . ' cr'], 403);
            }

            $paths = [];
            $totalDuration = 0;
            
            foreach ($request->file('media') as $file) {
                $path = $file->store('ad_videos', 'public');
                $paths[] = $path;
                
                // Calculate exact duration using ffprobe (graceful fallback)
                $fullPath = storage_path('app/public/' . $path);
                $duration = 15.0; // default fallback
                try {
                    $durationStr = shell_exec(sprintf('%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1', self::ffprobePath(), escapeshellarg($fullPath)));
                    $parsed = (float) trim($durationStr ?? '');
                    if ($parsed > 0) {
                        $duration = $parsed;
                    }
                } catch (\Exception $e) {
                    \Log::warning("ffprobe failed for {$path}: " . $e->getMessage());
                }
                $totalDuration += $duration;
            }

            $job = AdVideoJob::create([
                'user_id' => $user->id,
                'media_path' => json_encode($paths),
                'target_duration' => round($totalDuration), // Store exact duration in seconds
                'language' => $request->language,
                'aspect_ratio' => $request->aspect_ratio ?: 'original',
                'user_highlights' => $request->highlights,
                'status' => 'pending',
                'credits_charged' => $cost,
            ]);

            return response()->json([
                'job_id' => $job->id,
                'media_url' => Storage::url($paths[0]), // Preview first video
                'cost' => $cost
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload processing failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Step 2: Use Gemini Vision to generate the script.
     */
    public function generateScript(Request $request, AdVideoJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            return response()->json(['error' => 'OpenRouter API key not configured.'], 500);
        }

        $lang = $job->language;
        $ffmpeg = self::ffmpegPath();
        $ffprobe = self::ffprobePath();

        // Build highlights context
        $highlightSection = $job->user_highlights 
            ? "The seller highlights: " . $job->user_highlights
            : "Identify the product's strongest selling points from the visuals.";

        $langInstruction = $lang === 'Nepali' 
            ? 'Write ALL text in Devanagari Nepali (नेपाली). Use natural spoken Nepali like a real Nepali salesperson on Facebook Live. Mix in common English product terms where natural.'
            : 'Write in clear, energetic English for Nepali online shoppers.';

        // ── Build scene segments from the video timeline ──
        $videoPaths = json_decode($job->media_path, true);
        if (!is_array($videoPaths) || count($videoPaths) === 0) {
            $videoPaths = [$job->media_path];
        }

        $maxScenes = 8; // Cap to prevent oversized API payloads
        $segmentDuration = 4; // seconds per scene segment
        $scenes = [];
        $extractedFiles = [];
        $cumulativeTime = 0;

        foreach ($videoPaths as $idx => $path) {
            $fullPath = storage_path('app/public/' . $path);
            $durationStr = shell_exec(sprintf('%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s', $ffprobe, escapeshellarg($fullPath)));
            $clipDuration = (float) trim($durationStr);
            if ($clipDuration <= 0) $clipDuration = 1;

            // Divide this clip into segments
            for ($t = 0; $t < $clipDuration; $t += $segmentDuration) {
                if (count($scenes) >= $maxScenes) break; // Cap scene count
                $segStart = round($cumulativeTime + $t, 1);
                $segEnd = round($cumulativeTime + min($t + $segmentDuration, $clipDuration), 1);
                $segLen = $segEnd - $segStart;
                if ($segLen < 1) continue; // Skip tiny tail segments

                // Extract frame from midpoint of this segment
                $midpoint = $t + ($segLen / 2);
                $thumbPath = storage_path('app/public/ad_videos/thumb_' . $job->id . '_seg' . count($scenes) . '.jpg');
                $timeStr = sprintf('%02d:%02d:%02d', floor($midpoint/3600), floor(($midpoint/60)%60), floor($midpoint)%60);
                
                shell_exec(sprintf('%s -y -ss %s -i %s -vframes 1 -vf "scale=320:-1" -q:v 5 %s 2>&1', 
                    $ffmpeg, $timeStr, escapeshellarg($fullPath), escapeshellarg($thumbPath)));

                $frameBase64 = null;
                if (file_exists($thumbPath) && filesize($thumbPath) > 0) {
                    $frameBase64 = "data:image/jpeg;base64," . base64_encode(file_get_contents($thumbPath));
                    $extractedFiles[] = $thumbPath;
                }

                $wordCount = max(3, round($segLen * 2.5));
                $scenes[] = [
                    'scene' => count($scenes) + 1,
                    'start' => $segStart,
                    'end' => $segEnd,
                    'duration' => $segLen,
                    'words' => $wordCount,
                    'frame' => $frameBase64,
                ];
            }
            $cumulativeTime += $clipDuration;
        }

        // Clean up extracted files
        foreach ($extractedFiles as $file) {
            @unlink($file);
        }

        if (empty($scenes)) {
            return response()->json(['error' => 'Could not analyze the video.'], 500);
        }

        $totalDuration = round($cumulativeTime);
        $totalWords = array_sum(array_column($scenes, 'words'));

        // ── Build the scene description for the prompt ──
        $sceneDescriptions = "";
        foreach ($scenes as $s) {
            $sceneDescriptions .= "- Scene {$s['scene']}: [{$s['start']}s – {$s['end']}s] (write ~{$s['words']} words for this scene)\n";
        }

        $prompt = <<<PROMPT
You are Nepal's #1 e-commerce ad voiceover writer for Facebook/TikTok.

TASK: Write a scene-by-scene voiceover script. The video is {$totalDuration} seconds long, divided into scenes.

{$langInstruction}

{$highlightSection}

SCENES (each image corresponds to one scene):
{$sceneDescriptions}
RULES:
1. Write EXACTLY one line of voiceover per scene
2. Each line must match its scene's word count target
3. Total words across all scenes: ~{$totalWords}
4. Write about what is VISIBLE in each scene's image
5. Start with a hook, end with a CTA
6. Use urgency, social proof, and excitement

OUTPUT FORMAT: Return ONLY a valid JSON array. No markdown, no code fences, no explanation. Example:
[{"scene":1,"start":0,"end":4,"text":"Your voiceover line here"},{"scene":2,"start":4,"end":8,"text":"Next line here"}]
PROMPT;

        // ── Build the API payload with per-scene images ──
        $contentParts = [['type' => 'text', 'text' => $prompt]];
        foreach ($scenes as $s) {
            if ($s['frame']) {
                $contentParts[] = ['type' => 'text', 'text' => "Scene {$s['scene']} [{$s['start']}s–{$s['end']}s]:"];
                $contentParts[] = ['type' => 'image_url', 'image_url' => ['url' => $s['frame']]];
            }
        }

        try {
            $payload = json_encode([
                'model' => 'google/gemini-2.5-flash',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a JSON-only API. Return valid JSON arrays. No markdown, no code fences, no explanation.'],
                    ['role' => 'user', 'content' => $contentParts]
                ],
                'temperature' => 0.7,
            ]);

            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'HTTP-Referer: ' . url('/'),
                    'X-Title: YantraVoice Ad Studio',
                ],
            ]);

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false || $curlError) {
                throw new \Exception("API request failed: " . $curlError);
            }

            $result = json_decode($responseBody, true);

            if (!isset($result['choices'][0]['message']['content'])) {
                throw new \Exception($result['error']['message'] ?? 'AI did not return a response.');
            }

            $rawContent = trim($result['choices'][0]['message']['content']);
            
            // Strip markdown code fences if AI adds them despite instructions
            $rawContent = preg_replace('/^```(?:json)?\s*/i', '', $rawContent);
            $rawContent = preg_replace('/\s*```$/', '', $rawContent);

            $segments = json_decode($rawContent, true);
            if (!is_array($segments) || empty($segments)) {
                throw new \Exception("AI returned invalid script format. Please try again.");
            }

            // Ensure each segment has required fields and merge timing from our scenes
            foreach ($segments as $i => &$seg) {
                if (isset($scenes[$i])) {
                    $seg['start'] = $scenes[$i]['start'];
                    $seg['end'] = $scenes[$i]['end'];
                }
                $seg['scene'] = $i + 1;
                if (!isset($seg['text'])) {
                    $seg['text'] = '';
                }
            }
            unset($seg);

            $scriptJson = json_encode($segments);

            $job->update([
                'generated_script' => $scriptJson,
                'status' => 'pending_approval'
            ]);

            return response()->json(['script' => $scriptJson]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Step 3: Approve script and start video generation.
     */
    public function generateVideo(Request $request, AdVideoJob $job)
    {
        $request->validate([
            'script' => 'required|string',
            'voice_model' => 'required|string'
        ]);

        if ($job->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Allow both pending_approval and completed (re-render)
        if (!in_array($job->status, ['pending_approval', 'completed'])) {
            return response()->json(['error' => 'Invalid job state'], 403);
        }

        $isRerender = $job->status === 'completed';
        $voiceoverCost = ApiSetting::getCreditCost();
        $renderCost    = $isRerender ? max(1, intval(ApiSetting::getVideoRenderCost() / 2)) : 0; // render already paid on first run
        $cost = $voiceoverCost + $renderCost;

        // Deduct credits
        $user = auth()->user();
        if ($user->credits < $cost) {
            return response()->json(['error' => 'Insufficient credits. Cost: ' . $cost . ' cr'], 403);
        }

        $user->decrement('credits', $cost);
        CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => -$cost,
            'type' => 'generation_debit',
            'description' => ($isRerender ? 'Re-render' : 'Ad Video') . ": Voiceover Rs. {$voiceoverCost}" . ($renderCost > 0 ? " + Render Rs. {$renderCost}" : ''),
        ]);

        $job->update([
            'generated_script' => $request->script,
            'voice_model' => $request->voice_model,
            'status' => 'processing_video',
            'error_message' => null,
            'credits_charged' => $cost, // Track total charged for refund on failure
        ]);

        \App\Jobs\RenderAdVideo::dispatch($job);

        return response()->json(['status' => 'processing', 'cost' => $cost]);
    }

    /**
     * Step 4: Poll status.
     */
    public function checkStatus(AdVideoJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $response = [
            'status' => $job->status,
            'error' => $job->error_message,
            'video_url' => $job->output_video_path ? asset('storage/' . $job->output_video_path) : null,
            'script' => $job->generated_script,
            'voice_model' => $job->voice_model,
        ];

        // Include updated credits when job is completed
        if ($job->status === 'completed' || $job->status === 'failed') {
            $response['credits_remaining'] = auth()->user()->credits ?? 0;
        }

        return response()->json($response);
    }

    /**
     * Get user's video library.
     */
    public function library()
    {
        $jobs = AdVideoJob::where('user_id', auth()->id())
            ->whereIn('status', ['completed', 'processing_video', 'failed', 'pending_approval'])
            ->orderByDesc('created_at')
            ->take(50)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'status' => $job->status,
                    'duration' => $job->target_duration,
                    'language' => $job->language,
                    'voice_model' => $job->voice_model,
                    'video_url' => $job->output_video_path ? asset('storage/' . $job->output_video_path) : null,
                    'script' => $job->generated_script,
                    'created_at' => $job->created_at->diffForHumans(),
                    'created_date' => $job->created_at->format('M d, Y h:i A'),
                    'error' => $job->error_message,
                ];
            });

        return response()->json(['videos' => $jobs]);
    }
}
