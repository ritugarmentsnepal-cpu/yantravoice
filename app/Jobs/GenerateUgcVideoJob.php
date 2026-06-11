<?php

namespace App\Jobs;

use App\Models\ApiSetting;
use App\Models\CreditTransaction;
use App\Models\UgcVideoJob;
use App\Models\User;
use App\Services\UgcGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateUgcVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $prompt;
    public $cost;
    public $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $prompt, int $cost, int $jobId)
    {
        $this->user = $user;
        $this->prompt = $prompt;
        $this->cost = $cost;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle(UgcGenerationService $director): void
    {
        $job = UgcVideoJob::find($this->jobId);
        if (!$job) {
            return;
        }

        try {
            // Generate the blueprint
            $blueprint = $director->generateBlueprint($this->prompt, $job->style_preset ?? 'aggressive_cuts');

            // Update the job with blueprint
            $job->update([
                'video_blueprint' => $blueprint,
            ]);

            // Extract all dialogue from the generated blueprint to form the full script
            $fullScript = '';
            foreach ($blueprint['scenes'] ?? [] as $scene) {
                if (!empty($scene['dialogue'])) {
                    $fullScript .= $scene['dialogue'] . " ";
                }
            }

            $heygenKey = ApiSetting::getHeyGenApiKey();
            if (!$heygenKey) {
                throw new \Exception("HeyGen API key is not configured in settings.");
            }
            if (empty(trim($fullScript))) {
                throw new \Exception("Generated script dialogue is empty.");
            }

            $heygenAvatarId = $job->avatar ? $job->avatar->heygen_avatar_id : null;
            if (!$heygenAvatarId) {
                throw new \Exception("No valid HeyGen Avatar selected. Please sync avatars in settings.");
            }

            $payload = [
                'video_inputs' => [
                    [
                        'character' => [
                            'type' => 'avatar',
                            'avatar_id' => $heygenAvatarId, 
                            'avatar_style' => 'normal'
                        ],
                        'voice' => [
                            'type' => 'text',
                            'input_text' => trim($fullScript),
                            'voice_id' => '1bd001e7e50f421d891986aad5158bc8', // Generic Voice ID
                        ]
                    ]
                ],
                // Enforce WebM for Native Alpha Matting 
                'output_format' => 'webm',
                'dimension' => [
                    'width' => 1080,
                    'height' => 1920
                ],
                'test' => false,
            ];

            $ch = curl_init('https://api.heygen.com/v2/video/generate');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'X-Api-Key: ' . $heygenKey,
                    'Content-Type: application/json'
                ],
            ]);

            $responseBody = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($responseBody) {
                $heygenData = json_decode($responseBody, true);
                if (isset($heygenData['data']['video_id'])) {
                    $job->update([
                        'heygen_video_id' => $heygenData['data']['video_id'],
                        'status' => 'rendering_avatar'
                    ]);
                } else {
                    throw new \Exception('HeyGen API Error: ' . $responseBody);
                }
            } else {
                throw new \Exception('HeyGen Curl Error: ' . $curlError);
            }

        } catch (\Exception $e) {
            Log::error('UGC Generation Failed: ' . $e->getMessage());

            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            $this->refundCredits($e->getMessage());
        }
    }

    /**
     * Handle a job failure explicitly.
     */
    public function failed(\Throwable $exception)
    {
        $job = UgcVideoJob::find($this->jobId);
        if ($job && $job->status !== 'failed') {
            $job->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage()
            ]);
            $this->refundCredits($exception->getMessage());
        }
    }

    private function refundCredits(string $reason)
    {
        $this->user->increment('credits', $this->cost);
        
        CreditTransaction::create([
            'user_id' => $this->user->id,
            'amount' => $this->cost,
            'type' => 'refund',
            'description' => "Refund for failed UGC video generation: " . substr($reason, 0, 50),
        ]);
    }
}
