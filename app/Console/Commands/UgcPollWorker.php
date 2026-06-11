<?php

namespace App\Console\Commands;

use App\Models\ApiSetting;
use App\Models\UgcVideoJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UgcPollWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ugc:poll-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll HeyGen API for completed transparent WebM videos and compile HyperFrames locally';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting UGC Webhook Polling Worker...');

        $heygenKey = ApiSetting::getHeyGenApiKey();
        if (!$heygenKey) {
            $this->error('HeyGen API key not configured.');
            return;
        }

        $jobs = UgcVideoJob::where('status', 'rendering_avatar')->whereNotNull('heygen_video_id')->get();

        if ($jobs->count() > 0) {
            $this->info("Found {$jobs->count()} jobs waiting for HeyGen avatar render...");
        } else {
            $this->info("No pending jobs found. Exiting.");
            return;
        }

        foreach ($jobs as $job) {
                try {
                    $this->line("Polling HeyGen Video ID: {$job->heygen_video_id}");

                    $ch = curl_init("https://api.heygen.com/v1/video_status.get?video_id={$job->heygen_video_id}");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPGET => true,
                        CURLOPT_HTTPHEADER => [
                            'X-Api-Key: ' . $heygenKey,
                            'Accept: application/json'
                        ],
                    ]);

                    $response = curl_exec($ch);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($curlError || !$response) {
                        $this->error("Curl Error polling video {$job->heygen_video_id}: {$curlError}");
                        continue;
                    }

                    $data = json_decode($response, true);
                    $status = $data['data']['status'] ?? null;

                    if ($status === 'completed' || $status === 'success') {
                        $videoUrl = $data['data']['video_url'] ?? null;
                        if ($videoUrl) {
                            $this->info("HeyGen render complete! Downloading transparent WebM...");

                            // Ensure tmp directory exists
                            $dir = storage_path('app/public/tmp');
                            if (!is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }

                            // Download WebM
                            $filename = "avatar_{$job->id}.webm";
                            $path = "tmp/{$filename}";
                            $absPath = storage_path("app/public/{$path}");
                            
                            $videoBinary = file_get_contents($videoUrl);
                            if ($videoBinary) {
                                file_put_contents($absPath, $videoBinary);
                                
                                $job->update([
                                    'avatar_video_path' => $path,
                                    'status' => 'compiling_hyperframes'
                                ]);

                                $this->info("Asset downloaded to {$path}. Executing HyperFrames Compiler...");
                                
                                // Setup HyperFrames render command
                                $editorUrl = url("/ugc/editor/{$job->id}");
                                $outputPathRel = "ugc/final_{$job->id}.mp4";
                                $outputPathAbs = storage_path("app/public/{$outputPathRel}");
                                
                                $outputDir = dirname($outputPathAbs);
                                if (!is_dir($outputDir)) {
                                    mkdir($outputDir, 0755, true);
                                }

                                $command = [
                                    'npx', 
                                    'hyperframes', 
                                    'render', 
                                    '--workers', '1',
                                    '--url', $editorUrl, 
                                    '--output', $outputPathAbs
                                ];

                                $process = new Process($command);
                                $process->setTimeout(600); 
                                $process->run();

                                if ($process->isSuccessful()) {
                                    $this->info("HyperFrames compilation successful.");
                                    $job->update([
                                        'status' => 'completed',
                                        'output_video_path' => $outputPathRel
                                    ]);

                                    // Cleanup temporary WebM
                                    if (file_exists($absPath)) {
                                        unlink($absPath);
                                        $this->info("Deleted temporary WebM asset.");
                                    }
                                } else {
                                    $this->error("HyperFrames compilation failed: " . $process->getErrorOutput());
                                    $job->update([
                                        'status' => 'failed',
                                        'error_message' => 'HyperFrames Compilation Failed.'
                                    ]);
                                }

                            } else {
                                $this->error("Failed to download video binary from {$videoUrl}");
                            }
                        }
                    } elseif ($status === 'failed' || $status === 'error') {
                        $job->update([
                            'status' => 'failed',
                            'error_message' => 'HeyGen avatar generation failed. Status: ' . $status
                        ]);
                        $this->error("HeyGen video generation failed for job {$job->id}.");
                    }

                } catch (\Exception $e) {
                    $this->error("Exception processing job {$job->id}: " . $e->getMessage());
                }
            }
    }
}
