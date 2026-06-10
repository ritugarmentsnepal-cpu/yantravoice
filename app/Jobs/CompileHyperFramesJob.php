<?php

namespace App\Jobs;

use App\Models\UgcVideoJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CompileHyperFramesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $jobId;
    public $timeout = 600; // Allow 10 minutes for rendering

    /**
     * Create a new job instance.
     */
    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $job = UgcVideoJob::find($this->jobId);
        if (!$job) {
            return;
        }

        try {
            $job->update(['status' => 'compiling_hyperframes']);

            $editorUrl = url("/ugc/editor/{$job->id}");
            $outputPathRel = "ugc/final_{$job->id}.mp4";
            $outputPathAbs = storage_path("app/public/{$outputPathRel}");

            // Ensure output directory exists
            $outputDir = dirname($outputPathAbs);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Execute the hyperframes render command
            // We use Process to run the shell command safely
            // Note: npx must be in the server's PATH or we use the absolute path
            $command = [
                'npx', 
                'hyperframes', 
                'render', 
                '--url', $editorUrl, 
                '--output', $outputPathAbs
            ];

            // Wait 10 minutes max for Puppeteer to record and FFmpeg to encode
            $process = new Process($command);
            $process->setTimeout(600); 
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $job->update([
                'status' => 'completed',
                'output_video_path' => $outputPathRel
            ]);

            // Server Storage Cleanup (Garbage Collection)
            // 1. Delete the heavy HeyGen avatar WebM
            if ($job->avatar_video_path && file_exists(storage_path('app/public/' . $job->avatar_video_path))) {
                unlink(storage_path('app/public/' . $job->avatar_video_path));
            }

        } catch (\Exception $e) {
            Log::error("HyperFrames Compilation Failed for Job {$this->jobId}: " . $e->getMessage());

            $job->update([
                'status' => 'failed',
                'error_message' => 'HyperFrames Compilation Failed: ' . substr($e->getMessage(), 0, 200)
            ]);

            // Note: If you want to refund credits here as well, you'd trigger a refund 
            // However, the rendering stage might be considered non-refundable or we can 
            // dispatch a generic refund logic.
            // For now, setting status to failed is sufficient.
        }
    }
}
