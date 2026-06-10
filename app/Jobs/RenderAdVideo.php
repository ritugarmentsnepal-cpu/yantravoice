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
            Log::info("Intelligent Ad Video Render START Job#{$this->adJob->id}");

            // Verify FFmpeg
            $ffmpegCheck = trim(shell_exec(self::ffmpegPath() . ' -version 2>&1 | head -1') ?? '');
            if (empty($ffmpegCheck) || str_contains($ffmpegCheck, 'not found')) {
                throw new \Exception('FFmpeg is not installed.');
            }

            $scriptJson = $this->adJob->generated_script;
            $segments = json_decode($scriptJson, true);

            if (!is_array($segments) || count($segments) === 0) {
                throw new \Exception("Invalid script timeline format.");
            }

            @mkdir($tmpDir, 0777, true);

            $videoPaths = json_decode($this->adJob->media_path, true);
            if (!is_array($videoPaths)) {
                $videoPaths = [$this->adJob->media_path];
            }

            // Determine dimensions
            $targetW = null; $targetH = null;
            $aspect = $this->adJob->aspect_ratio ?: 'original';
            if ($aspect === '9:16') { $targetW = 1080; $targetH = 1920; }
            elseif ($aspect === '16:9') { $targetW = 1920; $targetH = 1080; }
            elseif ($aspect === '1:1') { $targetW = 1080; $targetH = 1080; }
            else {
                $dims = $this->getVideoDimensions(storage_path('app/public/' . $videoPaths[0]));
                $targetW = $dims['w']; $targetH = $dims['h'];
            }

            $fontPath = storage_path('app/fonts/Montserrat-Bold.ttf');
            if (!file_exists($fontPath)) {
                // Fallback to a system font if missing
                $fontPath = '/System/Library/Fonts/Supplemental/Arial.ttf';
            }

            $stdFiles = [];
            $audioFiles = [];
            $actualDurations = [];
            $transitionDur = 0.5;

            // ── PROCESS EACH SEGMENT ──
            foreach ($segments as $idx => $seg) {
                $clipIdx = $seg['clip_index'] ?? 0;
                if (!isset($videoPaths[$clipIdx])) {
                    $clipIdx = 0; // Fallback
                }
                $rawPath = storage_path('app/public/' . $videoPaths[$clipIdx]);

                $start = (float)($seg['start_time'] ?? 0);
                $end = (float)($seg['end_time'] ?? ($start + 3));
                $vDur = max(2.0, $end - $start);

                $text = trim($seg['voiceover_text'] ?? $seg['text'] ?? '');
                $audioPath = $tmpDir . "/audio_{$idx}.wav";
                $aDur = 0;

                if (!empty($text)) {
                    for ($attempt = 1; $attempt <= 3; $attempt++) {
                        try {
                            $this->generateSegmentTTS($text, $audioPath);
                            break;
                        } catch (\Exception $e) {
                            if ($attempt === 3) Log::warning("TTS failed for segment {$idx}: " . $e->getMessage());
                            sleep(1);
                        }
                    }
                }

                if (file_exists($audioPath) && filesize($audioPath) > 0) {
                    $aDur = $this->getMediaDuration($audioPath);
                    $audioFiles[$idx] = $audioPath;
                    
                    // Extend video duration if audio is longer
                    if ($aDur > $vDur) {
                        $vDur = $aDur + 0.5; // Give 0.5s padding
                    }
                } else {
                    $audioFiles[$idx] = null;
                }

                $stdFile = $tmpDir . "/std_{$idx}.mp4";
                $actualDurations[$idx] = $vDur;
                
                // Build video filter
                $blurFilter = $this->getBlurFilter($targetW, $targetH);
                $overlayText = trim($seg['text_overlay'] ?? '');
                
                $textFilter = "";
                $overlayInput = "";
                if (!empty($overlayText) && file_exists($fontPath)) {
                    $overlayImgPath = $tmpDir . "/overlay_{$idx}.png";
                    $this->createTextOverlayImage($overlayText, $fontPath, $overlayImgPath, $targetH);
                    
                    if (file_exists($overlayImgPath)) {
                        $overlayInput = "-i " . escapeshellarg($overlayImgPath);
                        // Overlay image is the second input (index 1).
                        $textFilter = ";[vout][1:v]overlay=(W-w)/2:H-h-" . intval($targetH / 5) . "[final]";
                    } else {
                        $textFilter = ";[vout]copy[final]";
                    }
                } else {
                    $textFilter = ";[vout]copy[final]";
                }

                $cmd = sprintf(
                    '%s -y -ss %.3f -t %.3f -i %s %s -filter_complex "%s" -map "[final]" -c:v libx264 -preset ultrafast -pix_fmt yuv420p -r 30 -video_track_timescale 90000 -an %s 2>&1',
                    self::ffmpegPath(), $start, $vDur, escapeshellarg($rawPath), $overlayInput, $blurFilter . $textFilter, escapeshellarg($stdFile)
                );
                
                $out = $this->runCommand($cmd, "Standardize Segment {$idx}");
                
                if (!file_exists($stdFile)) {
                    throw new \Exception("Failed to generate segment {$idx}. FFmpeg output: " . substr($out, -200));
                }
                
                $stdFiles[$idx] = $stdFile;
                
                // CRITICAL FIX: The source video might be shorter than $vDur, 
                // so we MUST measure the actual duration of the generated clip to calculate correct xfade offsets.
                $actualDurations[$idx] = $this->getMediaDuration($stdFile);
            }

            // ── BUILD XFADE GRAPH ──
            $numVideos = count($stdFiles);
            $inputs = "";
            foreach ($stdFiles as $file) {
                $inputs .= "-i " . escapeshellarg($file) . " ";
            }

            $filterGraph = "";
            $currentOffset = 0;
            $videoLabels = [];
            
            if ($numVideos === 1) {
                $filterGraph = "[0:v]copy[v]";
            } else {
                $prevLabel = "0:v";
                $currentOffset = max(0, $actualDurations[0] - $transitionDur);
                
                for ($i = 1; $i < $numVideos; $i++) {
                    $transType = $segments[$i-1]['transition'] ?? 'fade';
                    if ($transType === 'none' || !in_array($transType, ['fade', 'wipeleft', 'wiperight', 'slideup', 'slidedown', 'pixelize'])) {
                        $transType = 'fade';
                    }
                    
                    $isLast = ($i === $numVideos - 1);
                    $outLabel = $isLast ? "v" : "xf{$i}";
                    
                    $filterGraph .= "[{$prevLabel}][{$i}:v]xfade=transition={$transType}:duration={$transitionDur}:offset={$currentOffset}[{$outLabel}]";
                    
                    if (!$isLast) {
                        $filterGraph .= ";";
                        $currentOffset += max(0, $actualDurations[$i] - $transitionDur);
                    }
                    
                    $prevLabel = $outLabel;
                }
            }

            // ── BUILD AUDIO MIX GRAPH ──
            $audioOffset = 0;
            $audioInputsStr = "";
            $audioMixStr = "";
            $validAudioCount = 0;
            
            foreach ($audioFiles as $idx => $aFile) {
                if ($aFile) {
                    $delayMs = intval($audioOffset * 1000);
                    $inputs .= "-i " . escapeshellarg($aFile) . " ";
                    // The audio inputs start at index $numVideos
                    $aIdx = $numVideos + $validAudioCount;
                    $audioMixStr .= "[{$aIdx}:a]adelay={$delayMs}|{$delayMs}[a{$idx}];";
                    $audioLabels[] = "[a{$idx}]";
                    $validAudioCount++;
                }
                // Advance offset for next audio
                $audioOffset += max(0, $actualDurations[$idx] - $transitionDur);
            }
            
            if ($validAudioCount > 0) {
                $filterGraph .= ";" . $audioMixStr;
                foreach ($audioLabels as $label) {
                    $filterGraph .= $label;
                }
                $filterGraph .= "amix=inputs={$validAudioCount}:dropout_transition=0:normalize=0[a]";
            }

            // ── RENDER FINAL VIDEO ──
            $outputFile = 'ad_videos/final_' . $this->adJob->id . '_' . time() . '.mp4';
            $outputPath = storage_path('app/public/' . $outputFile);
            @mkdir(dirname($outputPath), 0777, true);

            $mapAudio = $validAudioCount > 0 ? '-map "[a]" -c:a aac -b:a 192k' : '';

            $cmd = sprintf(
                '%s -y %s -filter_complex "%s" -map "[v]" %s -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p %s 2>&1',
                self::ffmpegPath(),
                $inputs,
                $filterGraph,
                $mapAudio,
                escapeshellarg($outputPath)
            );

            $out = $this->runCommand($cmd, "Render Final Video");

            if (!file_exists($outputPath)) {
                throw new \Exception("Failed to render final video. FFmpeg output: " . substr($out, -500));
            }

            Log::info("Ad Video Render COMPLETE Job#{$this->adJob->id}");

            $this->adJob->update(['status' => 'completed', 'output_video_path' => $outputFile]);

        } catch (\Exception $e) {
            Log::error("Ad Video Render Failed Job#{$this->adJob->id}: " . $e->getMessage());
            $this->adJob->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        } finally {
            $this->cleanupTempDir($tmpDir);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Ad Video Job PERMANENTLY FAILED Job#{$this->adJob->id}: " . ($exception?->getMessage() ?? 'Unknown'));
        $this->adJob->update([
            'status' => 'failed',
            'error_message' => 'Render failed after all retries: ' . ($exception?->getMessage() ?? 'Unknown error'),
        ]);

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
        }
        $this->cleanupTempDir(storage_path('app/tmp_render_' . $this->adJob->id));
    }

    private function cleanupTempDir(string $tmpDir): void
    {
        if (is_dir($tmpDir)) {
            $files = glob("$tmpDir/*");
            if ($files) array_map('unlink', $files);
            @rmdir($tmpDir);
        }
    }

    private function createTextOverlayImage(string $text, string $fontPath, string $outputPath, int $targetH): void
    {
        $fontSize = intval($targetH / 12);
        $padding = 20;

        $bbox = @imagettfbbox($fontSize, 0, $fontPath, $text);
        if ($bbox === false) {
            return;
        }

        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);

        $imgW = $textWidth + ($padding * 2);
        $imgH = $textHeight + ($padding * 2);

        $img = imagecreatetruecolor($imgW, $imgH);
        imagesavealpha($img, true);

        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);

        // Semi-transparent black background for text
        $boxColor = imagecolorallocatealpha($img, 0, 0, 0, 60); 
        imagefilledrectangle($img, 0, 0, $imgW, $imgH, $boxColor);

        $textColor = imagecolorallocate($img, 255, 255, 255);
        
        $x = $padding;
        $y = $imgH - $padding - 5; 

        imagettftext($img, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);

        imagepng($img, $outputPath);
        imagedestroy($img);
    }

    private function runCommand(string $cmd, string $label = 'Command'): string
    {
        Log::info("{$label} Job#{$this->adJob->id}: {$cmd}");
        $output = shell_exec($cmd) ?? '';
        if (str_contains(strtolower($output), 'error') && str_contains(strtolower($output), 'no such file')) {
            Log::warning("{$label} may have failed Job#{$this->adJob->id}");
        }
        return $output;
    }

    private function getBlurFilter(int $w, int $h): string
    {
        return "[0:v]split[original][copy];[copy]scale={$w}:{$h}:force_original_aspect_ratio=increase,crop={$w}:{$h},boxblur=luma_radius=min(h\\\\,w)/20:luma_power=1:chroma_radius=min(cw\\\\,ch)/20:chroma_power=1[bg];[original]scale={$w}:{$h}:force_original_aspect_ratio=decrease[fg];[bg][fg]overlay=(W-w)/2:(H-h)/2,setsar=1,fps=30,settb=AVTB[vout]";
    }

    private function getVideoDimensions(string $path): array
    {
        if (!file_exists($path)) return ['w' => 1080, 'h' => 1920];
        $cmd = sprintf('%s -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 %s', self::ffprobePath(), escapeshellarg($path));
        $out = trim(shell_exec($cmd) ?? '');
        if ($out && strpos($out, 'x') !== false) {
            $parts = explode('x', $out);
            $w = (int)$parts[0]; $h = (int)$parts[1];
            if ($w > 0 && $h > 0) return ['w' => $w, 'h' => $h];
        }
        return ['w' => 1080, 'h' => 1920];
    }

    private function getMediaDuration(string $path): float
    {
        if (!file_exists($path)) return 15.0;
        $cmd = sprintf('%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s', self::ffprobePath(), escapeshellarg($path));
        $result = trim(shell_exec($cmd) ?? '');
        $duration = $result ? (float) $result : 0;
        return $duration > 0 ? $duration : 15.0;
    }

    private function generateSegmentTTS(string $text, string $outputPath): void
    {
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) throw new \Exception("API Key not found.");
        $voice = $this->adJob->voice_model ?: 'Puck';

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
            CURLOPT_TIMEOUT => 120,
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

        if ($pcmData === false || $curlError) throw new \Exception("TTS API request failed: " . $curlError);
        if ($httpCode !== 200) throw new \Exception("TTS API Error (HTTP {$httpCode}): " . substr($pcmData, 0, 300));
        if (empty($pcmData)) throw new \Exception("No audio data returned from TTS API.");

        if ($pcmData[0] === '{' || $pcmData[0] === '[') {
            $errorData = json_decode($pcmData, true);
            $msg = $errorData['error']['message'] ?? $errorData['message'] ?? 'Unknown TTS error';
            throw new \Exception("TTS API returned error: " . $msg);
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
