<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\VoiceoverLog;
use App\Models\CreditTransaction;
use App\Models\AdminExpense;
use App\Models\ApiSetting;

class TTSController extends Controller
{
    /**
     * Generate audio from text using the admin-configured OpenRouter API key.
     * Credits are deducted from the authenticated user.
     */
    public function generate(Request $request)
    {
        $user = auth()->user();

        // 1. Validate incoming request
        $validated = $request->validate([
            'text'     => 'required|string|max:2000',
            'language' => 'required|string|in:English,Nepali',
            'voice'    => 'required|string',
            'emotion'  => 'required|string',
        ]);

        // 2. Get the admin-configured API key
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            return response()->json([
                'error' => 'TTS service is not configured. Please contact the administrator.',
            ], 503);
        }

        // 3. Calculate costs
        $adminCostUsd = ApiSetting::getAdminCostUsd();  // admin's actual cost in USD
        $creditCost   = ApiSetting::getCreditCost();     // user charge in credits
        $nprCost      = ApiSetting::creditsToNpr($creditCost);

        // 4. Check user credits
        if (!$user->hasCredits($creditCost)) {
            $userNpr = ApiSetting::creditsToNpr($user->credits);
            return response()->json([
                'error' => "Insufficient balance. You need {$creditCost} credits (₨{$nprCost}) but have {$user->credits} credits (₨{$userNpr}).",
            ], 402);
        }

        // 5. Build the formatted prompt
        $formattedPrompt = "[{$validated['emotion']}] {$validated['text']}";
        $charCount = mb_strlen($validated['text']);

        // 6. Call the OpenRouter TTS API
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://openrouter.ai/api/v1/audio/speech', [
                    'model'           => 'google/gemini-3.1-flash-tts-preview',
                    'input'           => $formattedPrompt,
                    'voice'           => $validated['voice'],
                    'response_format' => 'pcm',
                ]);

            // 7. Handle error responses
            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['error']['message']
                    ?? $errorBody['message']
                    ?? 'API request failed with status ' . $response->status();

                // Log the failed attempt (no credit deduction)
                VoiceoverLog::create([
                    'user_id'          => $user->id,
                    'language'         => $validated['language'],
                    'voice_model'      => $validated['voice'],
                    'emotion'          => $validated['emotion'],
                    'input_text'       => $validated['text'],
                    'formatted_prompt' => $formattedPrompt,
                    'file_path'        => '',
                    'status'           => 'failed',
                    'credits_charged'  => 0,
                    'api_cost'         => 0,
                    'char_count'       => $charCount,
                ]);

                return response()->json([
                    'error' => $errorMessage,
                ], $response->status() >= 400 && $response->status() < 600 ? $response->status() : 500);
            }

            // 8. Success — convert raw PCM to WAV and save
            $pcmData = $response->body();
            $wavData = $this->pcmToWav($pcmData, 24000, 16, 1);
            $filename = Str::uuid() . '.wav';
            Storage::disk('public')->put('audio/' . $filename, $wavData);

            // 9. Log the successful generation
            $log = VoiceoverLog::create([
                'user_id'          => $user->id,
                'language'         => $validated['language'],
                'voice_model'      => $validated['voice'],
                'emotion'          => $validated['emotion'],
                'input_text'       => $validated['text'],
                'formatted_prompt' => $formattedPrompt,
                'file_path'        => 'audio/' . $filename,
                'status'           => 'success',
                'credits_charged'  => $creditCost,
                'api_cost'         => $adminCostUsd,
                'char_count'       => $charCount,
            ]);

            // 10. Deduct credits from user
            $user->deductCredits($creditCost);

            // 11. Record the credit transaction
            CreditTransaction::create([
                'user_id'          => $user->id,
                'amount'           => -$creditCost,
                'type'             => 'generation_debit',
                'description'      => "TTS generation: {$charCount} chars, {$validated['voice']}",
                'voiceover_log_id' => $log->id,
            ]);

            // 12. Auto-record admin expense (API cost tracking)
            AdminExpense::create([
                'category'         => 'api_cost',
                'amount'           => $adminCostUsd,
                'currency'         => 'USD',
                'description'      => "TTS API: {$charCount} chars, {$validated['voice']}",
                'expense_date'     => now()->toDateString(),
                'voiceover_log_id' => $log->id,
                'is_auto'          => true,
            ]);

            // 13. Return the audio URL + updated balance
            $freshUser = $user->fresh();
            return response()->json([
                'audio_url'         => asset('storage/audio/' . $filename),
                'credits_used'      => $creditCost,
                'credits_remaining' => $freshUser->credits,
                'npr_used'          => $nprCost,
                'npr_remaining'     => ApiSetting::creditsToNpr($freshUser->credits),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'error' => 'Failed to connect to the TTS API. Please try again later.',
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert raw PCM audio data to a WAV file.
     * Gemini TTS outputs raw PCM at 24kHz, 16-bit, mono.
     */
    private function pcmToWav(string $pcmData, int $sampleRate = 24000, int $bitsPerSample = 16, int $channels = 1): string
    {
        $dataSize = strlen($pcmData);
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);
        $chunkSize = 36 + $dataSize;

        // Build the 44-byte WAV header
        $header = pack('A4', 'RIFF');           // ChunkID
        $header .= pack('V', $chunkSize);       // ChunkSize
        $header .= pack('A4', 'WAVE');          // Format
        $header .= pack('A4', 'fmt ');          // Subchunk1ID
        $header .= pack('V', 16);              // Subchunk1Size (PCM = 16)
        $header .= pack('v', 1);               // AudioFormat (PCM = 1)
        $header .= pack('v', $channels);       // NumChannels
        $header .= pack('V', $sampleRate);     // SampleRate
        $header .= pack('V', $byteRate);       // ByteRate
        $header .= pack('v', $blockAlign);     // BlockAlign
        $header .= pack('v', $bitsPerSample);  // BitsPerSample
        $header .= pack('A4', 'data');          // Subchunk2ID
        $header .= pack('V', $dataSize);       // Subchunk2Size

        return $header . $pcmData;
    }
}
