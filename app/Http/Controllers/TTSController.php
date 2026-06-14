<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\VoiceoverLog;
use App\Models\CreditTransaction;
use App\Models\AdminExpense;
use App\Models\ApiSetting;
use App\Traits\DetectsMediaBinaries;

class TTSController extends Controller
{
    use DetectsMediaBinaries;

    /**
     * Generate audio from text using the admin-configured OpenRouter API key.
     * Credits are deducted from the authenticated user.
     */
    public function generate(Request $request)
    {
        $user = auth()->user();

        // 1. Validate incoming request — allow larger text for video-based scripts
        $maxLen = $request->input('source') === 'video_analysis' ? 10000 : 2000;
        $validated = $request->validate([
            'text'     => "required|string|max:{$maxLen}",
            'language' => 'required|string|in:English,Nepali',
            'voice'    => 'required|string',
            'emotion'  => 'required|string',
            'source'   => 'nullable|string|in:text,video_analysis',
        ]);

        // 2. Get the admin-configured API key
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            return response()->json([
                'error' => 'TTS service is not configured. Please contact the administrator.',
            ], 503);
        }

        // 3. Calculate costs
        $adminCostUsd = ApiSetting::getAdminCostUsd();  // admin's actual cost in USD
        $creditCost   = ApiSetting::getCreditCost();     // user charge in credits
        $nprCost      = ApiSetting::creditsToNpr($creditCost);

        // 4. Check user credits
        if (!$user->hasCredits($creditCost)) {
            $userNpr = ApiSetting::creditsToNpr($user->credits);
            return response()->json([
                'error' => "Insufficient balance. You need {$creditCost} credits (Rs. {$nprCost}) but have {$user->credits} credits (Rs. {$userNpr}).",
            ], 402);
        }

        // 5. Build the formatted prompt
        $formattedPrompt = "[{$validated['emotion']}] {$validated['text']}";
        $charCount = mb_strlen($validated['text']);

        // 6. Call the OpenRouter TTS API
        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'https://yantravoice.app'),
                    'X-Title'       => 'Yantra Voice Studio',
                ])
                ->post('https://openrouter.ai/api/v1/audio/speech', [
                    'model'           => 'google/gemini-3.1-flash-tts-preview',
                    'input'           => $formattedPrompt,
                    'voice'           => $validated['voice'],
                    'response_format' => 'pcm',
                ]);

            // 7. Handle error responses
            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message']
                    ?? $errorBody['message']
                    ?? 'API request failed with status ' . $response->status();

                // Log the failed attempt (no credit deduction)
                VoiceoverLog::create([
                    'user_id'          => $user->id,
                    'language'         => $validated['language'],
                    'voice_model'      => $validated['voice'],
                    'emotion'          => $validated['emotion'],
                    'input_text'       => $validated['text'],
                    'formatted_prompt' => $formattedPrompt,
                    'file_path'        => '',
                    'status'           => 'failed',
                    'credits_charged'  => 0,
                    'api_cost'         => 0,
                    'char_count'       => $charCount,
                ]);

                return response()->json([
                    'error' => $errorMessage,
                ], $response->status() >= 400 && $response->status() < 600 ? $response->status() : 500);
            }

            // 8. Success — convert raw PCM to WAV and then to MP3
            $audioData = $response->body();

            // Check if API returned JSON error instead of binary audio
            if (str_starts_with(trim($audioData), '{')) {
                $errObj = json_decode($audioData, true);
                $msg = $errObj['error']['message'] ?? $errObj['message'] ?? 'API returned invalid audio format.';

                // Log failed attempt
                VoiceoverLog::create([
                    'user_id'          => $user->id,
                    'language'         => $validated['language'],
                    'voice_model'      => $validated['voice'],
                    'emotion'          => $validated['emotion'],
                    'input_text'       => $validated['text'],
                    'formatted_prompt' => $formattedPrompt,
                    'file_path'        => '',
                    'status'           => 'failed',
                    'credits_charged'  => 0,
                    'api_cost'         => 0,
                    'char_count'       => $charCount,
                ]);
                return response()->json(['error' => "TTS API Error: " . $msg], 500);
            }

            // Convert PCM to WAV first
            $wavData = $this->pcmToWav($audioData, 24000, 16, 1);
            $wavFilename = Str::uuid() . '.wav';
            $wavPath = 'audio/' . $wavFilename;
            $saved = Storage::disk('public')->put($wavPath, $wavData);
            if (!$saved) {
                throw new \Exception('Failed to save audio file to disk. Check storage permissions.');
            }

            // Convert WAV to MP3 via FFmpeg
            $mp3Filename = Str::uuid() . '.mp3';
            $mp3Path = 'audio/' . $mp3Filename;
            $wavFullPath = storage_path('app/public/' . $wavPath);
            $mp3FullPath = storage_path('app/public/' . $mp3Path);

            $ffmpeg = self::ffmpegPath();
            $convertCmd = sprintf(
                '%s -y -i %s -codec:a libmp3lame -qscale:a 2 %s 2>&1',
                $ffmpeg,
                escapeshellarg($wavFullPath),
                escapeshellarg($mp3FullPath)
            );
            shell_exec($convertCmd);

            // Use MP3 if conversion succeeded, otherwise fall back to WAV
            $finalFilename = $mp3Filename;
            $finalPath = $mp3Path;
            $finalExt = 'mp3';
            if (!file_exists($mp3FullPath) || filesize($mp3FullPath) === 0) {
                $finalFilename = $wavFilename;
                $finalPath = $wavPath;
                $finalExt = 'wav';
            } else {
                // Clean up the intermediate WAV file
                @unlink($wavFullPath);
            }

            // 9. Log the successful generation
            $source = $validated['source'] ?? 'text';
            $log = VoiceoverLog::create([
                'user_id'          => $user->id,
                'language'         => $validated['language'],
                'voice_model'      => $validated['voice'],
                'emotion'          => $validated['emotion'],
                'input_text'       => $validated['text'],
                'formatted_prompt' => $formattedPrompt,
                'file_path'        => $finalPath,
                'status'           => 'success',
                'credits_charged'  => $creditCost,
                'api_cost'         => $adminCostUsd,
                'char_count'       => $charCount,
            ]);

            // 10. Deduct credits from user
            $user->deductCredits($creditCost);

            // 11. Record the credit transaction
            $descPrefix = $source === 'video_analysis' ? 'Video voiceover' : 'TTS generation';
            CreditTransaction::create([
                'user_id'          => $user->id,
                'amount'           => -$creditCost,
                'type'             => 'generation_debit',
                'description'      => "{$descPrefix}: {$charCount} chars, {$validated['voice']}",
                'voiceover_log_id' => $log->id,
            ]);

            // 12. Auto-record admin expense (API cost tracking)
            AdminExpense::create([
                'category'         => 'api_cost',
                'amount'           => $adminCostUsd,
                'currency'         => 'USD',
                'description'      => "{$descPrefix} API: {$charCount} chars, {$validated['voice']}",
                'expense_date'     => now()->toDateString(),
                'voiceover_log_id' => $log->id,
                'is_auto'          => true,
            ]);

            // 13. Return the audio URL + updated balance
            $freshUser = $user->fresh();
            return response()->json([
                'audio_url'         => asset('storage/' . $finalPath),
                'audio_format'      => $finalExt,
                'credits_used'      => $creditCost,
                'credits_remaining' => $freshUser->credits,
                'npr_used'          => $nprCost,
                'npr_remaining'     => ApiSetting::creditsToNpr($freshUser->credits),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'error' => 'Failed to connect to the TTS API. Please try again later.',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze an uploaded video to generate a scene-by-scene voiceover script.
     * Uses Gemini Vision to understand each scene and write matching narration.
     */
    public function analyzeVideo(Request $request)
    {
        try {
            $request->validate([
                'video'      => 'required|file|mimes:mp4,mov,avi,webm,mkv,3gp,m4v,mpeg,mpg,wmv,flv,ogv|max:102400',
                'language'   => 'required|string|in:English,Nepali',
                'highlights' => 'nullable|string|max:1000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 422);
        }

        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            return response()->json(['error' => 'API key not configured.'], 500);
        }

        $user = auth()->user();
        $lang = $request->language;
        $ffmpeg = self::ffmpegPath();
        $ffprobe = self::ffprobePath();

        // Track files for cleanup in finally block
        $fullPath = null;
        $extractedFiles = [];

        try {
            // Store uploaded video temporarily
            $videoPath = $request->file('video')->store('voiceover_videos', 'public');
            $fullPath = storage_path('app/public/' . $videoPath);

            // Get video duration via ffprobe
            $durationStr = shell_exec(sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
                $ffprobe, escapeshellarg($fullPath)
            ));
            $totalDuration = max(1, (float) trim($durationStr ?? ''));

            // Reject videos longer than 5 minutes to prevent abuse
            $maxDurationSeconds = 300; // 5 minutes
            if ($totalDuration > $maxDurationSeconds) {
                return response()->json([
                    'error' => 'Video is too long (' . round($totalDuration) . 's). Maximum allowed is ' . ($maxDurationSeconds / 60) . ' minutes.'
                ], 422);
            }

            // ── Build scene segments ──
            $maxScenes = 16; // More scenes than Ad Video (8) for denser voiceover
            $segmentDuration = max(3, ceil($totalDuration / $maxScenes));
            $scenes = [];

            for ($t = 0; $t < $totalDuration; $t += $segmentDuration) {
                if (count($scenes) >= $maxScenes) break;
                $segStart = round($t, 1);
                $segEnd = round(min($t + $segmentDuration, $totalDuration), 1);
                $segLen = $segEnd - $segStart;
                if ($segLen < 1) continue;

                // Extract frame from midpoint
                $midpoint = $t + ($segLen / 2);
                $thumbPath = storage_path('app/public/voiceover_videos/thumb_vo_' . time() . '_seg' . count($scenes) . '.jpg');
                $timeStr = sprintf('%02d:%02d:%02d', floor($midpoint/3600), floor(($midpoint/60)%60), floor($midpoint)%60);

                shell_exec(sprintf(
                    '%s -y -ss %s -i %s -vframes 1 -vf "scale=512:-1" -q:v 3 %s 2>&1',
                    $ffmpeg, $timeStr, escapeshellarg($fullPath), escapeshellarg($thumbPath)
                ));

                $frameBase64 = null;
                if (file_exists($thumbPath) && filesize($thumbPath) > 0) {
                    $frameBase64 = "data:image/jpeg;base64," . base64_encode(file_get_contents($thumbPath));
                    $extractedFiles[] = $thumbPath;
                }

                // Higher word density than Ad Video: 2.3 (Nepali) / 2.8 (English)
                $wordsPerSec = ($lang === 'Nepali') ? 2.3 : 2.8;
                $wordCount = max(3, round($segLen * $wordsPerSec));
                $scenes[] = [
                    'scene'    => count($scenes) + 1,
                    'start'    => $segStart,
                    'end'      => $segEnd,
                    'duration' => $segLen,
                    'words'    => $wordCount,
                    'frame'    => $frameBase64,
                ];
            }

            if (empty($scenes)) {
                return response()->json(['error' => 'Could not analyze the video. Please try a different file.'], 500);
            }

            $totalWords = array_sum(array_column($scenes, 'words'));

            // ── Build scene descriptions for prompt ──
            $sceneDescriptions = "";
            foreach ($scenes as $s) {
                $sceneDescriptions .= "- Scene {$s['scene']}: [{$s['start']}s – {$s['end']}s] (write ~{$s['words']} words for this scene)\n";
            }

            $langInstruction = $lang === 'Nepali'
                ? 'Write ALL text in Devanagari Nepali (नेपाली). Use natural, fluent spoken Nepali. Mix in common English terms where natural.'
                : 'Write in clear, professional English.';

            $highlightsInstruction = '';
            if ($request->filled('highlights')) {
                $highlightsInstruction = "\nKEY HIGHLIGHTS TO INCLUDE: " . $request->input('highlights') . "\nMake sure to incorporate these highlights naturally into the voiceover script where appropriate.\n";
            }

            $prompt = <<<PROMPT
You are a professional voiceover narration writer. Your job is to write voiceover scripts that precisely describe and match what is happening in each scene of a video.
{$highlightsInstruction}
TASK: Write a scene-by-scene voiceover script. The video is {$totalDuration} seconds long, divided into {$totalWords} total words across scenes.

{$langInstruction}

SCENES (each image corresponds to one scene):
{$sceneDescriptions}
RULES:
1. Write EXACTLY one line of voiceover per scene
2. Each line MUST match its scene's word count target EXACTLY (±1 word). This is CRITICAL — the word count controls the audio duration.
3. Total words across all scenes: EXACTLY ~{$totalWords} words. Do NOT write more.
4. Write about what is VISIBLE in each scene's image — describe the visuals, actions, products, people, settings accurately
5. Keep the narration flowing naturally — the entire script will be read as one continuous voiceover
6. Use a descriptive, engaging narrator tone — NOT an advertisement tone
7. Make the narration informative, vivid, and compelling
8. Ensure smooth transitions between scenes

OUTPUT FORMAT: Return ONLY a valid JSON array. No markdown, no code fences, no explanation. Example:
[{"scene":1,"start":0,"end":4,"text":"Your voiceover line here"},{"scene":2,"start":4,"end":8,"text":"Next line here"}]
PROMPT;

            // ── Build API payload with per-scene images ──
            $contentParts = [['type' => 'text', 'text' => $prompt]];
            foreach ($scenes as $s) {
                if ($s['frame']) {
                    $contentParts[] = ['type' => 'text', 'text' => "Scene {$s['scene']} [{$s['start']}s–{$s['end']}s]:"];
                    $contentParts[] = ['type' => 'image_url', 'image_url' => ['url' => $s['frame']]];
                }
            }

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
                    'X-Title: YantraVoice Video Analysis',
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

            // Strip markdown code fences if AI adds them
            $rawContent = preg_replace('/^```(?:json)?\s*/i', '', $rawContent);
            $rawContent = preg_replace('/\s*```$/', '', $rawContent);

            $segments = json_decode($rawContent, true);
            if (!is_array($segments) || empty($segments)) {
                throw new \Exception("AI returned invalid script format. Please try again.");
            }

            // Merge timing from our computed scenes
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

            return response()->json([
                'script'   => $segments,
                'duration' => round($totalDuration),
                'scenes'   => count($segments),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            // Always clean up temporary files, even on error
            foreach ($extractedFiles as $file) {
                @unlink($file);
            }
            if ($fullPath && file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    public function generateSample(Request $request)
    {
        $validated = $request->validate([
            'voice'    => 'required|string',
            'language' => 'required|string|in:English,Nepali',
        ]);

        $voice = $validated['voice'];
        $lang = $validated['language'];
        $sampleFilename = 'sample_' . $voice . '_' . $lang . '.mp3';
        $samplePath = 'audio/samples/' . $sampleFilename;
        $fullSamplePath = storage_path('app/public/' . $samplePath);

        if (file_exists($fullSamplePath)) {
            return response()->json(['audio_url' => asset('storage/' . $samplePath)]);
        }

        $text = $lang === 'Nepali' ? "नमस्ते, यो मेरो आवाजको नमुना हो।" : "Hello, this is a sample of my voice.";
        $emotion = "Neutral";
        
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            return response()->json(['error' => 'API not configured'], 503);
        }

        $formattedPrompt = "[{$emotion}] {$text}";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'https://yantravoice.app'),
                    'X-Title'       => 'Yantra Voice Studio',
                ])
                ->post('https://openrouter.ai/api/v1/audio/speech', [
                    'model'           => 'google/gemini-3.1-flash-tts-preview',
                    'input'           => $formattedPrompt,
                    'voice'           => $voice,
                    'response_format' => 'pcm',
                ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to generate sample.'], 500);
            }

            $audioData = $response->body();
            if (str_starts_with(trim($audioData), '{')) {
                return response()->json(['error' => 'API returned invalid audio.'], 500);
            }

            $wavData = $this->pcmToWav($audioData, 24000, 16, 1);
            $wavFilename = 'temp_' . Str::uuid() . '.wav';
            $wavPath = 'audio/samples/' . $wavFilename;
            
            Storage::disk('public')->put($wavPath, $wavData);
            $wavFullPath = storage_path('app/public/' . $wavPath);
            
            if (!file_exists(dirname($fullSamplePath))) {
                mkdir(dirname($fullSamplePath), 0755, true);
            }

            $ffmpeg = self::ffmpegPath();
            $convertCmd = sprintf(
                '%s -y -i %s -codec:a libmp3lame -qscale:a 2 %s 2>&1',
                $ffmpeg,
                escapeshellarg($wavFullPath),
                escapeshellarg($fullSamplePath)
            );
            shell_exec($convertCmd);

            @unlink($wavFullPath);

            if (file_exists($fullSamplePath)) {
                return response()->json(['audio_url' => asset('storage/' . $samplePath)]);
            }

            return response()->json(['error' => 'Failed to convert sample.'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function pcmToWav(string $pcmData, int $sampleRate = 24000, int $bitsPerSample = 16, int $channels = 1): string
    {
        $dataSize = strlen($pcmData);
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);
        $chunkSize = 36 + $dataSize;

        // Build the 44-byte WAV header
        $header = pack('A4', 'RIFF');
        $header .= pack('V', $chunkSize);
        $header .= pack('A4', 'WAVE');
        $header .= pack('A4', 'fmt ');
        $header .= pack('V', 16);
        $header .= pack('v', 1);
        $header .= pack('v', $channels);
        $header .= pack('V', $sampleRate);
        $header .= pack('V', $byteRate);
        $header .= pack('v', $blockAlign);
        $header .= pack('v', $bitsPerSample);
        $header .= pack('A4', 'data');
        $header .= pack('V', $dataSize);

        return $header . $pcmData;
    }
}
