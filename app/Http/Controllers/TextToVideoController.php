<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTextToVideoJob;
use App\Models\ApiSetting;
use App\Models\CreditTransaction;
use App\Models\TextToVideoJob;
use App\Models\User;
use App\Services\VeoVideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TextToVideoController extends Controller
{
    /**
     * Show the Text-to-Video generation page.
     */
    public function index()
    {
        $jobs = auth()->user()->textToVideoJobs()->latest()->get();
        $cost = ApiSetting::getTextToVideoCost();

        return view('t2v.index', compact('jobs', 'cost'));
    }

    /**
     * Handle the video generation request.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'prompt'          => 'required|string|max:2000',
            'negative_prompt' => 'nullable|string|max:500',
            'model_variant'   => ['required', Rule::in(TextToVideoJob::MODEL_VARIANTS)],
            'aspect_ratio'    => ['required', Rule::in(TextToVideoJob::ASPECT_RATIOS)],
            'resolution'      => ['required', Rule::in(TextToVideoJob::RESOLUTIONS)],
            'duration'        => ['required', Rule::in(TextToVideoJob::DURATIONS)],
            'generation_mode' => ['required', Rule::in(TextToVideoJob::MODES)],
            'first_frame'     => 'nullable|image|max:10240', // 10MB
            'last_frame'      => 'nullable|image|max:10240',
        ]);

        $user = auth()->user();
        $cost = ApiSetting::getTextToVideoCost();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        // Check API key is configured
        if (!ApiSetting::getApiKey()) {
            return response()->json([
                'success' => false,
                'message' => 'OpenRouter API key is not configured. Contact admin.'
            ], 503);
        }

        // Check credits
        if ($user->credits < $cost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits. You need ' . $cost . ' credits for this generation.'
            ], 402);
        }

        // Image-to-video validation: require at least first_frame
        if ($request->generation_mode === 'image_to_video' && !$request->hasFile('first_frame')) {
            return response()->json([
                'success' => false,
                'message' => 'First frame image is required for Image-to-Video mode.'
            ], 422);
        }

        // Handle file uploads
        $firstFramePath = null;
        $lastFramePath  = null;

        if ($request->hasFile('first_frame')) {
            $firstFramePath = $request->file('first_frame')->store('t2v-frames', 'public');
        }
        if ($request->hasFile('last_frame')) {
            $lastFramePath = $request->file('last_frame')->store('t2v-frames', 'public');
        }

        // 1. Deduct credits
        $user->decrement('credits', $cost);

        CreditTransaction::create([
            'user_id'     => $user->id,
            'amount'      => -$cost,
            'type'        => 'generation_debit',
            'description' => 'Text-to-Video Generation (' . $request->model_variant . ', ' . $request->duration . 's)',
        ]);

        // 2. Create the job record
        $jobRecord = TextToVideoJob::create([
            'user_id'          => $user->id,
            'prompt'           => $request->prompt,
            'negative_prompt'  => $request->negative_prompt,
            'model_variant'    => $request->model_variant,
            'aspect_ratio'     => $request->aspect_ratio,
            'resolution'       => $request->resolution,
            'duration'         => $request->duration,
            'generation_mode'  => $request->generation_mode,
            'first_frame_path' => $firstFramePath,
            'last_frame_path'  => $lastFramePath,
            'status'           => 'pending',
            'credits_charged'  => $cost,
        ]);

        // 3. Dispatch the queue job
        ProcessTextToVideoJob::dispatch($user, $jobRecord->id);

        return response()->json([
            'success' => true,
            'job_id'  => $jobRecord->id,
            'message' => 'Your video generation has started!',
        ]);
    }

    /**
     * Poll the status of a generation job (JSON endpoint).
     */
    public function status(TextToVideoJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = [
            'success'    => true,
            'status'     => $job->status,
            'error'      => $job->error_message,
            'video_path' => null,
        ];

        if ($job->status === 'completed' && $job->output_path) {
            $data['video_path'] = asset('storage/' . $job->output_path);
        }

        return response()->json($data);
    }

    /**
     * Show the completed video.
     */
    public function show(TextToVideoJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access.');
        }

        if ($job->status !== 'completed' || !$job->output_path) {
            abort(404, 'Video is not ready yet.');
        }

        return view('t2v.show', compact('job'));
    }

    /**
     * Webhook for OpenRouter video completion.
     */
    public function webhook(Request $request, VeoVideoService $veoService)
    {
        // OpenRouter sends the payload in JSON format
        $payload = $request->all();
        
        Log::info('OpenRouter Webhook Received', ['payload' => $payload]);

        if (!isset($payload['id'])) {
            return response()->json(['error' => 'Missing job ID'], 400);
        }

        $openrouterJobId = $payload['id'];
        $status          = $payload['status'] ?? 'unknown';

        $job = TextToVideoJob::where('openrouter_job_id', $openrouterJobId)->first();
        if (!$job || $job->status === 'completed' || $job->status === 'failed') {
            return response()->json(['message' => 'Job not found or already completed']);
        }

        if ($status === 'completed') {
            try {
                $videoUrls = $payload['unsigned_urls'] ?? $payload['video_urls'] ?? null;
                if (empty($videoUrls) || !is_array($videoUrls)) {
                    throw new \Exception('No video URLs provided in webhook.');
                }

                $videoUrl = $videoUrls[0];
                $filename = 't2v-videos/' . $job->id . '_' . Str::random(8) . '.mp4';

                $veoService->downloadVideo($videoUrl, $filename);

                $metadata = $job->metadata ?? [];
                $metadata['usage'] = $payload['usage'] ?? null;
                $metadata['completed_at'] = now()->toIso8601String();
                $metadata['webhook_received'] = true;

                $job->update([
                    'status'      => 'completed',
                    'video_url'   => $videoUrl,
                    'output_path' => $filename,
                    'metadata'    => $metadata,
                ]);

                Log::info('T2V Webhook: Generation completed', ['job_id' => $job->id]);

            } catch (\Exception $e) {
                Log::error('T2V Webhook: Download failed', ['error' => $e->getMessage()]);
                
                $job->update([
                    'status'        => 'failed',
                    'error_message' => 'Webhook download failed: ' . $e->getMessage(),
                ]);

                // Refund
                $refundAmount = $job->credits_charged;
                if ($refundAmount > 0 && $job->user_id) {
                    $user = User::find($job->user_id);
                    if ($user) {
                        $user->increment('credits', $refundAmount);
                        \App\Models\CreditTransaction::create([
                            'user_id'     => $user->id,
                            'amount'      => $refundAmount,
                            'type'        => 'refund',
                            'description' => 'Refund for failed T2V generation (Webhook).',
                        ]);
                    }
                }
            }
        } elseif ($status === 'failed' || $status === 'error') {
            $errorMsg = $payload['error']['message'] ?? $payload['error'] ?? 'Unknown OpenRouter error';
            
            $job->update([
                'status'        => 'failed',
                'error_message' => 'Webhook reported failure: ' . $errorMsg,
            ]);

            // Refund
            $refundAmount = $job->credits_charged;
            if ($refundAmount > 0 && $job->user_id) {
                $user = User::find($job->user_id);
                if ($user) {
                    $user->increment('credits', $refundAmount);
                    \App\Models\CreditTransaction::create([
                        'user_id'     => $user->id,
                        'amount'      => $refundAmount,
                        'type'        => 'refund',
                        'description' => 'Refund for failed T2V generation (Webhook failure).',
                    ]);
                }
            }
        }

        return response()->json(['success' => true]);
    }
}
