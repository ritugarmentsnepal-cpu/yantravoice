<?php

namespace App\Services;

use App\Models\ApiSetting;
use App\Models\TextToVideoJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VeoVideoService
{
    private const OPENROUTER_VIDEOS_URL = 'https://openrouter.ai/api/v1/videos';

    /**
     * Submit a video generation request to OpenRouter's Veo 3.1 endpoint.
     *
     * @param TextToVideoJob $job
     * @return string The OpenRouter job ID
     * @throws \Exception
     */
    public function submitGeneration(TextToVideoJob $job): string
    {
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            throw new \Exception('OpenRouter API key is not configured in admin settings.');
        }

        // Build the request payload
        $payload = [
            'model'        => $job->getOpenRouterModelId(),
            'prompt'       => $job->prompt,
            'aspect_ratio' => $job->aspect_ratio,
            'duration'     => $job->duration,
            'resolution'   => $job->resolution,
        ];

        // Add negative prompt if provided
        if (!empty($job->negative_prompt)) {
            $payload['negative_prompt'] = $job->negative_prompt;
        }

        // Image-to-Video: attach first/last frame images
        if ($job->isImageToVideo()) {
            $frameImages = [];

            if ($job->first_frame_path && Storage::disk('public')->exists($job->first_frame_path)) {
                $frameImages[] = [
                    'frame_type' => 'first_frame',
                    'url'        => asset('storage/' . $job->first_frame_path),
                ];
            }

            if ($job->last_frame_path && Storage::disk('public')->exists($job->last_frame_path)) {
                $frameImages[] = [
                    'frame_type' => 'last_frame',
                    'url'        => asset('storage/' . $job->last_frame_path),
                ];
            }

            if (!empty($frameImages)) {
                $payload['frame_images'] = $frameImages;
            }
        }

        $ch = curl_init(self::OPENROUTER_VIDEOS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . url('/'),
                'X-Title: Yantra Studio Text-to-Video',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError) {
            throw new \Exception('OpenRouter API request failed: ' . $curlError);
        }

        $result = json_decode($responseBody, true);

        Log::info('Veo Submit Response', ['http_code' => $httpCode, 'body' => $result]);

        // OpenRouter returns 202 Accepted with a job ID
        if ($httpCode >= 200 && $httpCode < 300 && isset($result['id'])) {
            return $result['id'];
        }

        // Handle error responses
        $errorMsg = $result['error']['message']
            ?? $result['error']
            ?? $result['message']
            ?? 'Unknown OpenRouter error (HTTP ' . $httpCode . ')';

        throw new \Exception('OpenRouter Video API Error: ' . $errorMsg);
    }

    /**
     * Poll the status of a video generation job on OpenRouter.
     *
     * @param string $openrouterJobId
     * @return array ['status' => string, 'video_urls' => array|null, 'usage' => array|null, 'error' => string|null]
     * @throws \Exception
     */
    public function pollStatus(string $openrouterJobId): array
    {
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            throw new \Exception('OpenRouter API key is not configured.');
        }

        $url = self::OPENROUTER_VIDEOS_URL . '/' . $openrouterJobId;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . url('/'),
                'X-Title: Yantra Studio Text-to-Video',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError) {
            throw new \Exception('OpenRouter poll request failed: ' . $curlError);
        }

        $result = json_decode($responseBody, true);

        if (!is_array($result)) {
            throw new \Exception('Invalid response from OpenRouter poll endpoint.');
        }

        $status = $result['status'] ?? 'unknown';

        return [
            'status'     => $status,
            'video_urls' => $result['unsigned_urls'] ?? $result['video_urls'] ?? null,
            'usage'      => $result['usage'] ?? null,
            'error'      => $result['error']['message'] ?? $result['error'] ?? null,
            'raw'        => $result,
        ];
    }

    /**
     * Download a video from a URL and save it locally.
     *
     * @param string $url      The video URL from OpenRouter
     * @param string $savePath Relative path within the 'public' disk (e.g., 't2v-videos/abc.mp4')
     * @return bool
     * @throws \Exception
     */
    public function downloadVideo(string $url, string $savePath, int $maxRetries = 3): bool
    {
        $attempt = 0;
        $apiKey = ApiSetting::getApiKey();
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 300, // 5 min max for large videos
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $apiKey,
                    'Accept: video/mp4, */*',
                ],
            ]);

            $videoData = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($videoData !== false && !$curlError && $httpCode === 200 && strlen($videoData) > 1000) {
                // Ensure directory exists and save
                Storage::disk('public')->put($savePath, $videoData);
                return true;
            }

            // If this was the last attempt, throw an exception
            if ($attempt >= $maxRetries) {
                if ($curlError) {
                    throw new \Exception('Failed to download video after ' . $maxRetries . ' attempts: ' . $curlError);
                }
                if ($httpCode !== 200) {
                    throw new \Exception('Video download returned HTTP ' . $httpCode . ' after ' . $maxRetries . ' attempts.');
                }
                throw new \Exception('Downloaded video is too small, likely an error response (Attempts: ' . $maxRetries . ').');
            }

            // Wait before retrying (exponential backoff: 2s, 4s...)
            sleep(2 * $attempt);
        }

        return false;
    }
}
