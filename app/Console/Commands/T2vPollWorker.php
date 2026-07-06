<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\TextToVideoJob;
use App\Models\User;
use App\Services\VeoVideoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class T2vPollWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 't2v:poll-worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll OpenRouter for completed Veo 3.1 videos and download them';

    /**
     * Execute the console command.
     */
    public function handle(VeoVideoService $veoService)
    {
        $this->info('Starting T2V Polling Worker...');

        $jobs = TextToVideoJob::where('status', 'polling')
            ->whereNotNull('openrouter_job_id')
            ->get();

        if ($jobs->count() === 0) {
            $this->info('No pending T2V jobs found. Exiting.');
            return;
        }

        $this->info("Found {$jobs->count()} jobs waiting for completion...");

        foreach ($jobs as $job) {
            try {
                $this->line("Polling OpenRouter Job ID: {$job->openrouter_job_id}");

                $pollResult = $veoService->pollStatus($job->openrouter_job_id);
                $status     = $pollResult['status'];

                if ($status === 'completed') {
                    $this->info("Video generated! Downloading...");
                    
                    $videoUrls = $pollResult['video_urls'];
                    if (empty($videoUrls) || !is_array($videoUrls)) {
                        throw new \Exception('OpenRouter returned completed status but no video URLs.');
                    }

                    $videoUrl = $videoUrls[0];
                    $filename = 't2v-videos/' . $job->id . '_' . Str::random(8) . '.mp4';

                    $veoService->downloadVideo($videoUrl, $filename);

                    $metadata = $job->metadata ?? [];
                    $metadata['usage'] = $pollResult['usage'] ?? null;
                    $metadata['completed_at'] = now()->toIso8601String();

                    $job->update([
                        'status'      => 'completed',
                        'video_url'   => $videoUrl,
                        'output_path' => $filename,
                        'metadata'    => $metadata,
                    ]);

                    $this->info("Video downloaded to {$filename}. Job completed.");

                } elseif ($status === 'failed' || $status === 'error') {
                    $errorMsg = $pollResult['error'] ?? 'Unknown OpenRouter error';
                    throw new \Exception('OpenRouter video generation failed: ' . $errorMsg);
                } else {
                    $this->line("Still generating (Status: {$status}). Will check again next run.");
                }

            } catch (\Exception $e) {
                $this->error("Exception processing job {$job->id}: " . $e->getMessage());
                Log::error('T2vPollWorker: Job failed', [
                    'job_id' => $job->id,
                    'error'  => $e->getMessage()
                ]);

                $job->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

                $this->refundCredits($job, $e->getMessage());
            }
        }
    }

    /**
     * Refund credits to the user on failure.
     */
    private function refundCredits(TextToVideoJob $job, string $reason): void
    {
        $refundAmount = $job->credits_charged;

        if ($refundAmount > 0 && $job->user_id) {
            $user = User::find($job->user_id);
            if ($user) {
                $user->increment('credits', $refundAmount);

                CreditTransaction::create([
                    'user_id'     => $user->id,
                    'amount'      => $refundAmount,
                    'type'        => 'refund',
                    'description' => 'Refund for failed T2V generation: ' . Str::limit($reason, 50),
                ]);
            }
        }
    }
}
