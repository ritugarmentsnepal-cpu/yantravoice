<?php

namespace App\Http\Controllers;

use App\Models\ApiSetting;
use App\Models\VoiceoverLog;
use App\Models\AdVideoJob;
use App\Models\CreditTransaction;
use Carbon\Carbon;

class StudioController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $creditCost = ApiSetting::getCreditCost();
        $nprCost    = ApiSetting::creditsToNpr($creditCost);
        $hasApiKey  = ApiSetting::getApiKey() !== null;
        $userNpr    = ApiSetting::creditsToNpr($user->credits);

        // ── Dashboard Stats ─────────────────────────────
        $totalVoiceovers = VoiceoverLog::where('user_id', $user->id)->count();
        $totalAdVideos   = AdVideoJob::where('user_id', $user->id)->count();

        $creditsSpentMonth = CreditTransaction::where('user_id', $user->id)
            ->where('type', 'generation_debit')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('amount');
        $nprSpentMonth = ApiSetting::creditsToNpr(abs($creditsSpentMonth));

        $accountAge = Carbon::parse($user->created_at)->diffInDays(now());

        // Recent activity: merge voiceovers + ad videos, sort by date
        $recentVoiceovers = VoiceoverLog::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($v) {
                return [
                    'type'       => 'voiceover',
                    'icon'       => '🎙️',
                    'label'      => mb_strimwidth($v->input_text, 0, 50, '...'),
                    'language'   => $v->language,
                    'voice'      => $v->voice_model,
                    'status'     => $v->status,
                    'cost_npr'   => ApiSetting::creditsToNpr($v->credits_charged ?? 0),
                    'created_at' => $v->created_at,
                ];
            });

        $recentAdVideos = AdVideoJob::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($v) {
                return [
                    'type'       => 'advideo',
                    'icon'       => '🎬',
                    'label'      => ($v->target_duration ?? '?') . 's ' . ($v->language ?? '') . ' Ad',
                    'language'   => $v->language,
                    'voice'      => $v->voice_model,
                    'status'     => $v->status,
                    'cost_npr'   => ApiSetting::creditsToNpr($v->credits_charged ?? 0),
                    'created_at' => $v->created_at,
                ];
            });

        $recentActivity = collect($recentVoiceovers)->concat($recentAdVideos)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        // Credit transactions (last 20)
        $creditHistory = CreditTransaction::where('user_id', $user->id)
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($t) {
                return [
                    'amount_npr'  => ApiSetting::creditsToNpr(abs($t->amount)),
                    'type'        => $t->type,
                    'is_positive' => $t->amount > 0,
                    'description' => $t->description,
                    'created_at'  => $t->created_at,
                ];
            });

        return view('tts-studio', [
            'user'            => $user,
            'creditCost'      => $creditCost,
            'nprCost'         => $nprCost,
            'hasApiKey'       => $hasApiKey,
            'userNpr'         => $userNpr,
            // Dashboard
            'totalVoiceovers' => $totalVoiceovers,
            'totalAdVideos'   => $totalAdVideos,
            'nprSpentMonth'   => $nprSpentMonth,
            'accountAge'      => $accountAge,
            'recentActivity'  => $recentActivity,
            'creditHistory'   => $creditHistory,
            'videoRenderCost' => ApiSetting::getVideoRenderCost(),
        ]);
    }
}
