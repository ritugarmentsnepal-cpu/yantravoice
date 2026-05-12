<?php

namespace App\Jobs;

use App\Models\AdVideoJob;
use App\Models\ApiSetting;
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
    private string $ffmpeg = '/opt/homebrew/bin/ffmpeg';
    private string $ffprobe = '/opt/homebrew/bin/ffprobe';

    public function __construct(public AdVideoJob $adJob) {}

    public function handle(): void
    {
        try {
            $script = $this->adJob->generated_script;
            $segments = json_decode($script, true);

            // Fallback: if script is plain text (legacy), treat as single segment
            if (!is_array($segments) || !isset($segments[0]['text'])) {
                $segments = [['scene' => 1, 'start' => 0, 'end' => $this->adJob->target_duration ?: 15, 'text' => $script]];
            }

            $tmpDir = storage_path('app/tmp_render_' . $this->adJob->id);
            @mkdir($tmpDir, 0777, true);

            // ── Step 1: Build full script from segments ──
            $fullScript = '';
            foreach ($segments as $seg) {
                $text = trim($seg['text'] ?? '');
                if (!empty($text)) {
                    $fullScript .= $text . ' ';
                }
            }
            $fullScript = trim($fullScript);

            if (empty($fullScript)) {
                throw new \Exception("Script is empty — nothing to generate.");
            }

            // ── Step 2: Generate ONE continuous TTS audio ──
            $rawAudioPath = $tmpDir . '/voiceover_raw.wav';
            $success = false;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $this->generateSegmentTTS($fullScript, $rawAudioPath);
                    $success = true;
                    break;
                } catch (\Exception $e) {
                    Log::warning("TTS attempt {$attempt} failed: " . $e->getMessage());
                    if ($attempt < 3) {
                        sleep($attempt * 2);
                    }
                }
            }

            if (!$success || !file_exists($rawAudioPath) || filesize($rawAudioPath) === 0) {
                throw new \Exception("Failed to generate voiceover audio after 3 attempts.");
            }

            // ── Step 3: Measure durations and time-stretch audio to match video ──
            $videoPaths = json_decode($this->adJob->media_path, true);
            if (!is_array($videoPaths)) {
                $videoPaths = [$this->adJob->media_path];
            }

            // Get total video duration
            $videoDuration = 0;
            foreach ($videoPaths as $vp) {
                $videoDuration += $this->getMediaDuration(storage_path('app/public/' . $vp));
            }
            if ($videoDuration <= 0) {
                $videoDuration = $this->adJob->target_duration ?: 15;
            }

            // Get actual TTS audio duration
            $audioDuration = $this->getMediaDuration($rawAudioPath);

            // Apply a single uniform tempo change to match video duration
            $finalAudioPath = $tmpDir . '/voiceover.wav';
            if ($audioDuration > 0 && abs($audioDuration - $videoDuration) > 0.5) {
                $tempoRatio = $audioDuration / $videoDuration;
                // Clamp to 0.7x–1.4x to keep speech natural
                $tempoRatio = max(0.7, min(1.4, $tempoRatio));
                
                Log::info("Audio Sync Job#{$this->adJob->id}: audio={$audioDuration}s video={$videoDuration}s tempo={$tempoRatio}");
                
                $cmd = sprintf('%s -y -i %s -af "atempo=%s" -c:a pcm_s16le %s 2>&1',
                    $this->ffmpeg,
                    escapeshellarg($rawAudioPath),
                    $tempoRatio,
                    escapeshellarg($finalAudioPath)
                );
                shell_exec($cmd);
            } else {
                // Already close enough, use as-is
                copy($rawAudioPath, $finalAudioPath);
            }

            if (!file_exists($finalAudioPath) || filesize($finalAudioPath) === 0) {
                // Fallback to raw audio if tempo adjustment failed
                copy($rawAudioPath, $finalAudioPath);
            }

            $this->adJob->update(['tts_audio_path' => 'tmp']);

            // ── Step 4: Render final video ──
            $outputFile = 'ad_videos/final_' . $this->adJob->id . '_' . time() . '.mp4';
            $outputPath = storage_path('app/public/' . $outputFile);

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
                $dims = $this->getVideoDimensions(storage_path('app/public/' . $videoPaths[0]));
                $targetW = $dims['w'];
                $targetH = $dims['h'];
            }

            if (count($videoPaths) === 1) {
                $this->processSingleVideo($videoPaths[0], $finalAudioPath, $outputPath, $targetW, $targetH, $tmpDir);
            } else {
                $this->processMultipleVideos($videoPaths, $finalAudioPath, $outputPath, $targetW, $targetH, $tmpDir);
            }

            if (!file_exists($outputPath)) {
                throw new \Exception("FFmpeg failed to produce output file.");
            }

            $this->adJob->update(['status' => 'completed', 'output_video_path' => $outputFile]);
            
            // Cleanup
            array_map('unlink', glob("$tmpDir/*"));
            @rmdir($tmpDir);

        } catch (\Exception $e) {
            Log::error("Ad Video Render Failed Job#{$this->adJob->id}: " . $e->getMessage());
            $this->adJob->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }

    private function getBlurFilter(int $w, int $h): string
    {
        return "[0:v]split[original][copy];[copy]scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},boxblur=luma_radius=min(h\\,w)/20:luma_power=1:chroma_radius=min(cw\\,ch)/20:chroma_power=1[bg];[original]scale={$w}:{$h}:force_original_aspect_ratio=decrease[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1[vout]";
    }

    private function processSingleVideo(string $videoPath, string $audioPath, string $outputPath, ?int $targetW, ?int $targetH, string $tmpDir): void
    {
        $fullVideoPath = storage_path('app/public/' . $videoPath);

        if ($targetW !== null && $targetH !== null) {
            $filter = $this->getBlurFilter($targetW, $targetH);
            $cmd = sprintf(
                '%s -y -i %s -i %s -filter_complex "%s" -map "[vout]" -map 1:a:0 -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k %s 2>&1',
                $this->ffmpeg,
                escapeshellarg($fullVideoPath),
                escapeshellarg($audioPath),
                $filter,
                escapeshellarg($outputPath)
            );
        } else {
            $cmd = sprintf(
                '%s -y -i %s -i %s -map 0:v:0 -map 1:a:0 -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k %s 2>&1',
                $this->ffmpeg,
                escapeshellarg($fullVideoPath),
                escapeshellarg($audioPath),
                escapeshellarg($outputPath)
            );
        }

        Log::info("FFmpeg Single Video Job#{$this->adJob->id}: $cmd");
        $out = shell_exec($cmd);

        if (!file_exists($outputPath)) {
            Log::error("FFmpeg Output Job#{$this->adJob->id}: " . $out);
            throw new \Exception("Failed to process single video.");
        }
    }

    private function processMultipleVideos(array $videoPaths, string $audioPath, string $outputPath, int $targetW, int $targetH, string $tmpDir): void
    {
        $numVideos = count($videoPaths);
        $transitionDur = 1.0;
        
        $stdFiles = [];
        $actualDurations = [];

        foreach ($videoPaths as $idx => $path) {
            $fullPath = storage_path('app/public/' . $path);
            $stdFile = $tmpDir . "/std_{$idx}.mp4";
            
            $clipDur = $this->getAudioDuration($fullPath);
            if ($clipDur <= $transitionDur) {
                $clipDur = $transitionDur + 0.5;
            }
            $actualDurations[] = $clipDur;
            $stdFiles[] = $stdFile;
            
            $blurFilter = $this->getBlurFilter($targetW, $targetH) . ";[vout]fps=30,tpad=stop_mode=clone:stop_duration=5[final]";
            
            $cmd = sprintf(
                '%s -y -i %s -t %.3f -filter_complex "%s" -map "[final]" -c:v libx264 -preset ultrafast -pix_fmt yuv420p -an %s 2>&1',
                $this->ffmpeg, escapeshellarg($fullPath), $clipDur, $blurFilter, escapeshellarg($stdFile)
            );
            shell_exec($cmd);
        }

        $filterGraph = "";
        $inputs = "";
        for ($i = 0; $i < $numVideos; $i++) {
            $inputs .= "-i " . escapeshellarg($stdFiles[$i]) . " ";
        }

        if ($numVideos == 2) {
            $offset = $actualDurations[0] - $transitionDur;
            $filterGraph = "[0:v][1:v]xfade=transition=fade:duration={$transitionDur}:offset={$offset}[v]";
        } else {
            $currentOffset = $actualDurations[0] - $transitionDur;
            $filterGraph = "[0:v][1:v]xfade=transition=fade:duration={$transitionDur}:offset={$currentOffset}[v01];";
            
            for ($i = 2; $i < $numVideos; $i++) {
                $currentOffset += $actualDurations[$i - 1] - $transitionDur;
                $prev = $i == 2 ? "v01" : "v0" . ($i - 1);
                $out = $i == $numVideos - 1 ? "v" : "v0{$i}";
                $filterGraph .= "[{$prev}][{$i}:v]xfade=transition=fade:duration={$transitionDur}:offset={$currentOffset}[{$out}]";
                if ($i < $numVideos - 1) $filterGraph .= ";";
            }
        }

        $cmd = sprintf(
            '%s -y %s -i %s -filter_complex "%s" -map "[v]" -map %d:a -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k %s 2>&1',
            $this->ffmpeg,
            $inputs,
            escapeshellarg($audioPath),
            $filterGraph,
            $numVideos,
            escapeshellarg($outputPath)
        );

        Log::info("FFmpeg Multi Video Job#{$this->adJob->id}: $cmd");
        $out = shell_exec($cmd);

        if (!file_exists($outputPath)) {
            Log::error("FFmpeg Output Job#{$this->adJob->id}: " . $out);
            throw new \Exception("Failed to process multiple videos with transitions.");
        }
    }

    private function getVideoDimensions(string $path): array
    {
        if (!file_exists($path)) return ['w' => 1080, 'h' => 1920];
        $cmd = sprintf('%s -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 %s', $this->ffprobe, escapeshellarg($path));
        $out = trim(shell_exec($cmd));
        if ($out && strpos($out, 'x') !== false) {
            $parts = explode('x', $out);
            return ['w' => (int)$parts[0], 'h' => (int)$parts[1]];
        }
        return ['w' => 1080, 'h' => 1920];
    }

    private function getAudioDuration(string $path): float
    {
        return $this->getMediaDuration($path);
    }

    private function getMediaDuration(string $path): float
    {
        if (!file_exists($path)) return 15.0;
        $cmd = sprintf('%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s', $this->ffprobe, escapeshellarg($path));
        $result = trim(shell_exec($cmd) ?? '');
        return $result ? (float) $result : 15.0;
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
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: http://localhost',
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
            throw new \Exception("TTS API Error (HTTP {$httpCode}): " . substr($pcmData, 0, 200));
        }

        if (empty($pcmData)) {
            throw new \Exception("No audio data returned from TTS API.");
        }

        $wavData = $this->pcmToWav($pcmData, 24000, 16, 1);
        file_put_contents($outputPath, $wavData);
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
