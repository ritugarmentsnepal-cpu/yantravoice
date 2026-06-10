<?php

namespace App\Traits;

/**
 * Shared FFmpeg/FFprobe binary detection for controllers and jobs.
 *
 * Auto-detects the correct path across macOS (Homebrew) and Linux (home dir, system).
 * Falls back to bare command name (relies on $PATH).
 */
trait DetectsMediaBinaries
{
    private static function ffmpegPath(): string
    {
        $home = getenv('HOME') ?: '/home/yantrauser';
        foreach (["{$home}/bin/ffmpeg", '/usr/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg'] as $p) {
            if (file_exists($p)) return $p;
        }
        return 'ffmpeg'; // fallback to PATH
    }

    private static function ffprobePath(): string
    {
        $home = getenv('HOME') ?: '/home/yantrauser';
        foreach (["{$home}/bin/ffprobe", '/usr/bin/ffprobe', '/opt/homebrew/bin/ffprobe', '/usr/local/bin/ffprobe'] as $p) {
            if (file_exists($p)) return $p;
        }
        return 'ffprobe'; // fallback to PATH
    }
}
