<?php

namespace App\Jobs;

use App\Models\AdVideoJob;
use App\Models\ApiSetting;
use App\Models\CreditTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenderAdVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 2;

    private static function ffmpegPath(): string
    {
        $home = getenv('HOME') ?: '/home/yantrauser';
        foreach (["{$home}/bin/ffmpeg", '/usr/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $p) {
            if (file_exists($p)) return $p;
        }
        return 'ffmpeg';
    }

    private static function ffprobePath(): string
    {
        $home = getenv('HOME') ?: '/home/yantrauser';
        foreach (["{$home}/bin/ffprobe", '/usr/bin/ffprobe', '/opt/homebrew/bin/ffprobe', '/usr/local/bin/ffprobe'] as $p) {
            if (file_exists($p)) return $p;
        }
        return 'ffprobe';
    }

    public function __construct(public AdVideoJob $adJob) {}

    public function handle(): void
    {
        $tmpDir = storage_path('app/tmp_render_' . $this->adJob->id);

        try {
            Log::info("Ad Video Render START Job#{$this->adJob->id}");

            // Verify FFmpeg is available
            $ffmpegCheck = trim(shell_exec(self::ffmpegPath() . ' -version 2>&1 | head -1') ?? '');
            Log::info("FFmpeg check Job#{$this->adJob->id}: " . ($ffmpegCheck ?: 'NOT FOUND'));
            if (empty($ffmpegCheck) || str_contains($ffmpegCheck, 'not found')) {
                throw new \Exception('FFmpeg is not installed on this server. Please install it with: apt-get install -y ffmpeg');
            }

            $script = $this->adJob->generated_script;
            $segments = json_decode($script, true);

            // Fallback: if script is plain text (legacy), treat as single segment
            if (!is_array($segments) || !isset($segments[0]['text'])) {
                $segments = [['scene' => 1, 'start' => 0, 'end' => $this->adJob->target_duration ?: 15, 'text' => $script]];
            }

            @mkdir($tmpDir, 0777, true);

            // ── Step 1: Generate TTS audio PER SEGMENT and sync each to its video duration ──
            $videoPaths = json_decode($this->adJob->media_path, true);
            if (!is_array($videoPaths)) {
                $videoPaths = [$this->adJob->media_path];
            }

            // Calculate total video duration
            $videoDuration = 0;
            foreach ($videoPaths as $vp) {
                $dur = $this->getMediaDuration(storage_path('app/public/' . $vp));
                Log::info("Video clip duration Job#{$this->adJob->id}: {$vp} = {$dur}s");
                $videoDuration += $dur;
            }
            if ($videoDuration <= 0) {
                $videoDuration = $this->adJob->target_duration ?: 15;
            }

            $syncedSegmentFiles = [];
            $totalSegments = count($segments);

            foreach ($segments as $idx => $seg) {
                $text = trim($seg['text'] ?? '');
                if (empty($text)) continue;

                $segDuration = ($seg['end'] ?? 0) - ($seg['start'] ?? 0);
                if ($segDuration <= 0) {
                    $segDuration = $videoDuration / max(1, $totalSegments);
                }

                Log::info("Segment #{$idx} Job#{$this->adJob->id}: text=" . mb_substr($text, 0, 50) . "... target={$segDuration}s");

                // Generate TTS for this segment
                $segRawPath = $tmpDir . "/seg_{$idx}_raw.wav";
                $success = false;
                $lastErr = '';
                for ($attempt = 1; $attempt <= 3; $attempt++) {
                    try {
                        $this->generateSegmentTTS($text, $segRawPath);
                        $success = true;
                        break;
                    } catch (\Exception $e) {
                        $lastErr = $e->getMessage();
                        Log::warning("TTS seg#{$idx} attempt {$attempt} failed Job#{$this->adJob->id}: {$lastErr}");
                        if ($attempt < 3) sleep($attempt);
                    }
                }

                if (!$success || !file_exists($segRawPath) || filesize($segRawPath) === 0) {
                    Log::error("Segment #{$idx} TTS failed completely Job#{$this->adJob->id}: {$lastErr}");
                    continue; // Skip this segment
                }

                // Measure actual TTS audio duration
                $segAudioDur = $this->getMediaDuration($segRawPath);
                Log::info("Segment #{$idx} Job#{$this->adJob->id}: tts_audio={$segAudioDur}s target_video={$segDuration}s");

                // Tempo-adjust this segment to match its video duration
                $segSyncedPath = $tmpDir . "/seg_{$idx}_synced.wav";
                if ($segAudioDur > 0 && abs($segAudioDur - $segDuration) > 0.2) {
                    $tempoRatio = $segAudioDur / $segDuration;
                    // Per-segment: allow wider range (0.7-1.5) since segments are short
                    $tempoRatio = max(0.7, min(1.5, $tempoRatio));

                    Log::info("Segment #{$idx} Job#{$this->adJob->id}: tempo={$tempoRatio}");

                    $cmd = sprintf('%s -y -i %s -af "atempo=%s" -c:a pcm_s16le -t %.3f %s 2>&1',
                        self::ffmpegPath(),
                        escapeshellarg($segRawPath),
                        $tempoRatio,
                        $segDuration,
                        escapeshellarg($segSyncedPath)
                    );
                    $this->runCommand($cmd, "Tempo adjust seg#{$idx}");
                } else {
                    // Duration already matches — just trim to exact length
                    $cmd = sprintf('%s -y -i %s -c:a pcm_s16le -t %.3f %s 2>&1',
                        self::ffmpegPath(),
                        escapeshellarg($segRawPath),
                        $segDuration,
                        escapeshellarg($segSyncedPath)
                    );
                    $this->runCommand($cmd, "Trim seg#{$idx}");
                }

                if (file_exists($segSyncedPath) && filesize($segSyncedPath) > 0) {
                    $syncedSegmentFiles[] = $segSyncedPath;
                } else {
                    // Fallback: use raw audio trimmed to segment duration
                    Log::warning("Segment #{$idx} sync failed Job#{$this->adJob->id}, using raw");
                    $syncedSegmentFiles[] = $segRawPath;
                }
            }

            if (empty($syncedSegmentFiles)) {
                throw new \Exception("No TTS segments were generated successfully.");
            }

            // ── Step 2: Concatenate all synced segments into one final audio ──
            $finalAudioPath = $tmpDir . '/voiceover.wav';

            if (count($syncedSegmentFiles) === 1) {
                copy($syncedSegmentFiles[0], $finalAudioPath);
            } else {
                // Create concat list file
                $concatList = $tmpDir . '/concat_list.txt';
                $listContent = '';
                foreach ($syncedSegmentFiles as $f) {
                    $listContent .= "file " . escapeshellarg($f) . "\n";
                }
                file_put_contents($concatList, $listContent);

                $cmd = sprintf('%s -y -f concat -safe 0 -i %s -c:a pcm_s16le %s 2>&1',
                    self::ffmpegPath(),
                    escapeshellarg($concatList),
                    escapeshellarg($finalAudioPath)
                );
                $this->runCommand($cmd, "Concatenate segments");
            }

            if (!file_exists($finalAudioPath) || filesize($finalAudioPath) === 0) {
                throw new \Exception("Failed to create final voiceover audio.");
            }

            $finalAudioDur = $this->getMediaDuration($finalAudioPath);
            Log::info("Final voiceover Job#{$this->adJob->id}: {$finalAudioDur}s (video={$videoDuration}s, segments=" . count($syncedSegmentFiles) . ")");

            // ── Step 4: Render final video ──
            $outputFile = 'ad_videos/final_' . $this->adJob->id . '_' . time() . '.mp4';
            $outputPath = storage_path('app/public/' . $outputFile);

            // Ensure output directory exists
            @mkdir(dirname($outputPath), 0777, true);

            $targetW = null;
            $targetH = null;
            $aspect = $this->adJob->aspect_ratio ?: 'original';

            if ($aspect === '9:16') {
                $targetW = 1080; $targetH = 1920;
            } elseif ($aspect === '16:9') {
                $targetW = 1920; $targetH = 1080;
            } elseif ($aspect === '1:1') {
                $targetW = 1080; $targetH = 1080;
            } elseif ($aspect === 'original' && count($videoPaths) > 1) {
                // For multiple videos with original aspect, use first video's dimensions
                $dims = $this->getVideoDimensions(storage_path('app/public/' . $videoPaths[0]));
                $targetW = $dims['w'];
                $targetH = $dims['h'];
            }

            if (count($videoPaths) === 1) {
                $this->processSingleVideo($videoPaths[0], $finalAudioPath, $outputPath, $targetW, $targetH, $tmpDir);
            } else {
                // Ensure we have dimensions for multi-video (required for standardization)
                if ($targetW === null || $targetH === null) {
                    $dims = $this->getVideoDimensions(storage_path('app/public/' . $videoPaths[0]));
                    $targetW = $dims['w'];
                    $targetH = $dims['h'];
                }
                $this->processMultipleVideos($videoPaths, $finalAudioPath, $outputPath, $targetW, $targetH, $tmpDir);
            }

            if (!file_exists($outputPath)) {
                throw new \Exception("FFmpeg failed to produce output file.");
            }

            $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);
            Log::info("Ad Video Render COMPLETE Job#{$this->adJob->id}: {$fileSize}MB");

            $this->adJob->update(['status' => 'completed', 'output_video_path' => $outputFile]);

        } catch (\Exception $e) {
            Log::error("Ad Video Render Failed Job#{$this->adJob->id}: " . $e->getMessage());
            $this->adJob->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        } finally {
            // Always clean up temp files, whether success or failure
            $this->cleanupTempDir($tmpDir);
        }
    }

    /**
     * Handle a permanently failed job (after all retries exhausted).
     * Refunds credits to the user.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("Ad Video Job PERMANENTLY FAILED Job#{$this->adJob->id}: " . ($exception?->getMessage() ?? 'Unknown'));

        $this->adJob->update([
            'status' => 'failed',
            'error_message' => 'Render failed after all retries: ' . ($exception?->getMessage() ?? 'Unknown error'),
        ]);

        // Refund credits
        $user = $this->adJob->user;
        if ($user && $this->adJob->credits_charged > 0) {
            $refundAmount = $this->adJob->credits_charged;
            $user->increment('credits', $refundAmount);
            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => $refundAmount,
                'type' => 'refund',
                'description' => "Refund: Ad video render failed (Job #{$this->adJob->id})",
            ]);
            Log::info("Credits refunded Job#{$this->adJob->id}: {$refundAmount} credits to user #{$user->id}");
        }

        // Cleanup temp files
        $this->cleanupTempDir(storage_path('app/tmp_render_' . $this->adJob->id));
    }

    /**
     * Clean up temporary render directory.
     */
    private function cleanupTempDir(string $tmpDir): void
    {
        if (is_dir($tmpDir)) {
            $files = glob("$tmpDir/*");
            if ($files) {
                array_map('unlink', $files);
            }
            @rmdir($tmpDir);
        }
    }

    /**
     * Run a shell command with logging and basic error detection.
     */
    private function runCommand(string $cmd, string $label = 'Command'): string
    {
        Log::info("{$label} Job#{$this->adJob->id}: {$cmd}");
        $output = shell_exec($cmd) ?? '';
        
        // Check for common FFmpeg error patterns in output
        if (str_contains(strtolower($output), 'error') && str_contains(strtolower($output), 'no such file')) {
            Log::warning("{$label} may have failed Job#{$this->adJob->id}: " . substr($output, -500));
        }
        
        return $output;
    }

    private function getBlurFilter(int $w, int $h): string
    {
        return "[0:v]split[original][copy];[copy]scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},boxblur=luma_radius=min(h\\\\,w)/20:luma_power=1:chroma_radius=min(cw\\\\,ch)/20:chroma_power=1[bg];[original]scale={$w}:{$h}:force_original_aspect_ratio=decrease[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1[vout]";
    }

    private function processSingleVideo(string $videoPath, string $audioPath, string $outputPath, ?int $targetW, ?int $targetH, string $tmpDir): void
    {
        $fullVideoPath = storage_path('app/public/' . $videoPath);

        if ($targetW !== null && $targetH !== null) {
            $filter = $this->getBlurFilter($targetW, $targetH);
            $cmd = sprintf(
                '%s -y -i %s -i %s -filter_complex "%s" -map "[vout]" -map 1:a:0 -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k -shortest %s 2>&1',
                self::ffmpegPath(),
                escapeshellarg($fullVideoPath),
                escapeshellarg($audioPath),
                $filter,
                escapeshellarg($outputPath)
            );
        } else {
            $cmd = sprintf(
                '%s -y -i %s -i %s -map 0:v:0 -map 1:a:0 -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k -shortest %s 2>&1',
                self::ffmpegPath(),
                escapeshellarg($fullVideoPath),
                escapeshellarg($audioPath),
                escapeshellarg($outputPath)
            );
        }

        $out = $this->runCommand($cmd, "FFmpeg Single Video");

        if (!file_exists($outputPath)) {
            Log::error("FFmpeg Output Job#{$this->adJob->id}: " . substr($out, -1000));
            throw new \Exception("Failed to process single video. FFmpeg output: " . substr($out, -300));
        }
    }

    private function processMultipleVideos(array $videoPaths, string $audioPath, string $outputPath, int $targetW, int $targetH, string $tmpDir): void
    {
        $numVideos = count($videoPaths);
        $transitionDur = 0.5; // Reduced from 1.0 to be safer with short clips
        
        $stdFiles = [];
        $actualDurations = [];

        // Step 1: Standardize each video clip to same resolution/fps
        foreach ($videoPaths as $idx => $path) {
            $fullPath = storage_path('app/public/' . $path);
            $stdFile = $tmpDir . "/std_{$idx}.mp4";
            
            $clipDur = $this->getMediaDuration($fullPath);
            Log::info("Multi-video clip#{$idx} Job#{$this->adJob->id}: duration={$clipDur}s");
            
            if ($clipDur <= $transitionDur) {
                $clipDur = $transitionDur + 1.0;
            }
            $actualDurations[] = $clipDur;
            $stdFiles[] = $stdFile;
            
            // Standardize: resize, set fps, NO tpad (was adding 5s padding per clip)
            $blurFilter = $this->getBlurFilter($targetW, $targetH) . ";[vout]fps=30[final]";
            
            $cmd = sprintf(
                '%s -y -i %s -t %.3f -filter_complex "%s" -map "[final]" -c:v libx264 -preset ultrafast -pix_fmt yuv420p -an %s 2>&1',
                self::ffmpegPath(), escapeshellarg($fullPath), $clipDur, $blurFilter, escapeshellarg($stdFile)
            );
            $out = $this->runCommand($cmd, "Standardize clip#{$idx}");
            
            if (!file_exists($stdFile)) {
                Log::error("Standardize clip#{$idx} failed Job#{$this->adJob->id}: " . substr($out, -500));
                throw new \Exception("Failed to standardize video clip #{$idx}");
            }
        }

        // Step 2: Build xfade filter graph with robust label naming
        $inputs = "";
        for ($i = 0; $i < $numVideos; $i++) {
            $inputs .= "-i " . escapeshellarg($stdFiles[$i]) . " ";
        }

        $filterGraph = "";
        if ($numVideos == 2) {
            $offset = max(0, $actualDurations[0] - $transitionDur);
            $filterGraph = "[0:v][1:v]xfade=transition=fade:duration={$transitionDur}:offset={$offset}[v]";
        } else {
            // Chain xfade transitions: [0]+[1]→[xf0], [xf0]+[2]→[xf1], ..., last→[v]
            $currentOffset = max(0, $actualDurations[0] - $transitionDur);
            $prevLabel = "0:v";
            
            for ($i = 1; $i < $numVideos; $i++) {
                $isLast = ($i === $numVideos - 1);
                $outLabel = $isLast ? "v" : "xf{$i}";
                
                $filterGraph .= "[{$prevLabel}][{$i}:v]xfade=transition=fade:duration={$transitionDur}:offset={$currentOffset}[{$outLabel}]";
                
                if (!$isLast) {
                    $filterGraph .= ";";
                    $currentOffset += max(0, $actualDurations[$i] - $transitionDur);
                }
                
                $prevLabel = $outLabel;
            }
        }

        // Step 3: Combine with xfade + audio
        $cmd = sprintf(
            '%s -y %s -i %s -filter_complex "%s" -map "[v]" -map %d:a -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k -shortest %s 2>&1',
            self::ffmpegPath(),
            $inputs,
            escapeshellarg($audioPath),
            $filterGraph,
            $numVideos,
            escapeshellarg($outputPath)
        );

        $out = $this->runCommand($cmd, "FFmpeg Multi Video");

        if (!file_exists($outputPath)) {
            Log::error("FFmpeg Multi Output Job#{$this->adJob->id}: " . substr($out, -1000));
            throw new \Exception("Failed to combine multiple videos. FFmpeg output: " . substr($out, -300));
        }
    }

    private function getVideoDimensions(string $path): array
    {
        if (!file_exists($path)) return ['w' => 1080, 'h' => 1920];
        $cmd = sprintf('%s -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 %s', self::ffprobePath(), escapeshellarg($path));
        $out = trim(shell_exec($cmd) ?? '');
        if ($out && strpos($out, 'x') !== false) {
            $parts = explode('x', $out);
            $w = (int)$parts[0];
            $h = (int)$parts[1];
            if ($w > 0 && $h > 0) {
                return ['w' => $w, 'h' => $h];
            }
        }
        return ['w' => 1080, 'h' => 1920];
    }

    private function getMediaDuration(string $path): float
    {
        if (!file_exists($path)) {
            Log::warning("getMediaDuration: file not found: {$path}");
            return 15.0;
        }
        $cmd = sprintf('%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s', self::ffprobePath(), escapeshellarg($path));
        $result = trim(shell_exec($cmd) ?? '');
        $duration = $result ? (float) $result : 0;
        if ($duration <= 0) {
            Log::warning("getMediaDuration: got zero/negative for {$path}, falling back to 15s");
            return 15.0;
        }
        return $duration;
    }

    /**
     * Generate TTS audio for a single text segment and save as WAV.
     */
    private function generateSegmentTTS(string $text, string $outputPath): void
    {
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            throw new \Exception("API Key not found.");
        }

        $voice = $this->adJob->voice_model ?: 'Puck';

        // Use raw cURL to avoid Guzzle tmpfile issues
        $payload = json_encode([
            'model' => 'google/gemini-3.1-flash-tts-preview',
            'input' => $text,
            'voice' => $voice,
            'response_format' => 'pcm'
        ]);

        $ch = curl_init('https://openrouter.ai/api/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 120, // Increased from 60s for longer scripts
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . config('app.url', 'https://yantravoice.62.72.29.212.nip.io'),
                'X-Title: Yantra Voice Studio',
            ],
        ]);

        $pcmData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($pcmData === false || $curlError) {
            throw new \Exception("TTS API request failed: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \Exception("TTS API Error (HTTP {$httpCode}): " . substr($pcmData, 0, 300));
        }

        if (empty($pcmData)) {
            throw new \Exception("No audio data returned from TTS API.");
        }

        // Verify we got actual PCM data and not a JSON error response
        if ($pcmData[0] === '{' || $pcmData[0] === '[') {
            $errorData = json_decode($pcmData, true);
            $msg = $errorData['error']['message'] ?? $errorData['message'] ?? 'Unknown TTS error';
            throw new \Exception("TTS API returned error: " . $msg);
        }

        $wavData = $this->pcmToWav($pcmData, 24000, 16, 1);
        file_put_contents($outputPath, $wavData);

        Log::info("TTS generated Job#{$this->adJob->id}: " . strlen($pcmData) . " bytes PCM, voice={$voice}");
    }

    private function pcmToWav(string $pcmData, int $sampleRate = 24000, int $bitsPerSample = 16, int $channels = 1): string
    {
        $dataSize = strlen($pcmData);
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);
        $chunkSize = 36 + $dataSize;

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
