<?php

namespace App\Jobs;

use App\Models\CreditTransaction;
use App\Models\TextToVideoJob;
use App\Models\User;
use App\Services\VeoVideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessTextToVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public User $user;
    public int  $jobRecordId;

    /**
     * Maximum seconds the queue worker should allow this job to run.
     */
    public $timeout = 720; // 12 minutes

    /**
     * Number of times the job may be attempted.
     */
    public $tries = 1;

    public function __construct(User $user, int $jobRecordId)
    {
        $this->user        = $user;
        $this->jobRecordId = $jobRecordId;
    }

    /**
     * Execute the job.
     */
    public function handle(VeoVideoService $veoService): void
    {
        $job = TextToVideoJob::find($this->jobRecordId);
        if (!$job) {
            Log::error('T2V: Job record not found', ['id' => $this->jobRecordId]);
            return;
        }

        try {
            // ── Step 1: Submit to OpenRouter ─────────────────
            $job->update(['status' => 'generating']);

            $openrouterJobId = $veoService->submitGeneration($job);

            // Job is submitted. Set status to polling. 
            // The T2vPollWorker (or Webhook) will handle checking for completion.
            $job->update([
                'openrouter_job_id' => $openrouterJobId,
                'status'            => 'polling',
            ]);

            Log::info('T2V: Submitted to OpenRouter. Waiting for async poll worker.', [
                'job_id'     => $job->id,
                'or_job_id'  => $openrouterJobId,
            ]);

        } catch (\Exception $e) {
            Log::error('T2V: Submission failed', [
                'job_id'  => $job->id,
                'error'   => $e->getMessage(),
            ]);

            $job->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->refundCredits($e->getMessage());
        }
    }

    /**
     * Handle a job failure from the queue system.
     */
    public function failed(\Throwable $exception): void
    {
        $job = TextToVideoJob::find($this->jobRecordId);
        if ($job && $job->status !== 'failed') {
            $job->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
            $this->refundCredits($exception->getMessage());
        }
    }

    /**
     * Refund credits to the user on failure.
     */
    private function refundCredits(string $reason): void
    {
        $job = TextToVideoJob::find($this->jobRecordId);
        $refundAmount = $job ? $job->credits_charged : 0;

        if ($refundAmount > 0) {
            $this->user->increment('credits', $refundAmount);

            CreditTransaction::create([
                'user_id'     => $this->user->id,
                'amount'      => $refundAmount,
                'type'        => 'refund',
                'description' => 'Refund for failed T2V generation: ' . Str::limit($reason, 50),
            ]);
        }
    }
}
