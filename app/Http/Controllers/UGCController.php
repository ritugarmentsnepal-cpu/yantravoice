<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateUgcVideoJob;
use App\Models\ApiSetting;
use App\Models\Avatar;
use App\Models\CreditTransaction;
use App\Models\UgcVideoJob;
use Illuminate\Http\Request;

class UGCController extends Controller
{
    /**
     * Show the UGC dashboard / generation form.
     */
    public function index()
    {
        if (Avatar::count() === 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'AvatarSeeder', '--force' => true]);
        }

        $avatars = Avatar::all();
        $jobs = auth()->user()->ugcVideoJobs()->latest()->get();
        return view('ugc.index', compact('avatars', 'jobs'));
    }

    /**
     * Handle the generation request. Deduct credits and dispatch job.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'avatar_id' => 'required|exists:avatars,id',
            'style_preset' => 'required|string|in:aggressive_cuts,minimalist_tech,cinematic'
        ]);

        $user = auth()->user();
        
        // Define UGC cost (we can reuse the video render cost or set a specific one)
        // For now, let's use ApiSetting::getVideoRenderCost() or a hardcoded value like 50.
        // The user suggested ApiSetting::getCreditCost('ugc_short'), but ApiSetting might not have it yet.
        // Using existing method for safety, or we just pull from config if it existed.
        // Let's assume ApiSetting::getVideoRenderCost() is appropriate for UGC generation.
        $cost = ApiSetting::getVideoRenderCost(); 

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        if ($user->credits < $cost) {
            return response()->json([
                'success' => false, 
                'message' => 'Insufficient credits to generate a UGC video. Cost: ' . $cost
            ], 402);
        }

        // 1. Lock credits immediately
        $user->decrement('credits', $cost);
        
        // Log credit transaction for auditing
        CreditTransaction::create([
            'user_id' => $user->id,
            'amount' => -$cost,
            'type' => 'generation_debit',
            'description' => 'UGC Video Generation Initiated'
        ]);

        // 2. Create the Job record first
        $jobRecord = UgcVideoJob::create([
            'user_id' => $user->id,
            'avatar_id' => $request->avatar_id,
            'prompt' => $request->prompt,
            'style_preset' => $request->style_preset,
            'status' => 'generating',
            'credits_charged' => $cost
        ]);

        // 3. Offload the heavy lifting to the queue
        GenerateUgcVideoJob::dispatch($user, $request->prompt, $cost, $jobRecord->id);

        return response()->json([
            'success' => true,
            'job_id' => $jobRecord->id,
            'message' => 'Your UGC video generation has started.'
        ]);
    }

    /**
     * Poll the status of a job.
     */
    public function status(UgcVideoJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'status' => $job->status,
            'output_video_path' => $job->status === 'completed' ? asset('storage/' . $job->output_video_path) : null,
            'error' => $job->error_message
        ]);
    }

    /**
     * The dynamic editor playback view.
     */
    public function editor(UgcVideoJob $job)
    {
        // Allow access if blueprint is generated (rendering avatar, compiling, or completed)
        if (!in_array($job->status, ['rendering_avatar', 'compiling_hyperframes', 'completed'])) {
            abort(404, 'Video blueprint not ready yet.');
        }

        $blueprintData = $job->video_blueprint;

        return view('ugc.editor', compact('blueprintData', 'job'));
    }

    /**
     * Show the final rendered video to the user.
     */
    public function show(UgcVideoJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access.');
        }

        if ($job->status !== 'completed' || !$job->output_video_path) {
            abort(404, 'Video is not fully rendered yet.');
        }

        return view('ugc.show', compact('job'));
    }
}
