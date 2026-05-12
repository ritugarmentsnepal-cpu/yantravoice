<?php

namespace App\Console\Commands;

use App\Models\AdVideoJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupAdVideos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad-video:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes ad videos and media older than 3 days to free up disk space.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Ad Video cleanup...");

        $threshold = Carbon::now()->subDays(3);

        $oldJobs = AdVideoJob::where('created_at', '<', $threshold)->get();

        $deletedCount = 0;

        foreach ($oldJobs as $job) {
            // Delete input media
            if ($job->media_path && Storage::disk('public')->exists($job->media_path)) {
                Storage::disk('public')->delete($job->media_path);
            }

            // Delete TTS audio
            if ($job->tts_audio_path && Storage::disk('public')->exists($job->tts_audio_path)) {
                Storage::disk('public')->delete($job->tts_audio_path);
            }

            // Delete final output video
            if ($job->output_video_path && Storage::disk('public')->exists($job->output_video_path)) {
                Storage::disk('public')->delete($job->output_video_path);
            }

            // Update status to indicate files were purged
            $job->update([
                'status' => 'purged_storage',
                'media_path' => null,
                'tts_audio_path' => null,
                'output_video_path' => null,
            ]);

            $deletedCount++;
        }

        $this->info("Cleanup complete. Purged files for {$deletedCount} old jobs.");
    }
}
