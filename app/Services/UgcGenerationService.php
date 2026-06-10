<?php

namespace App\Services;

use App\Models\ApiSetting;

class UgcGenerationService
{
    /**
     * Generate a video blueprint JSON script using the LLM.
     *
     * @param string $userInput The prompt or landing page URL.
     * @param string $stylePreset The selected style (aggressive_cuts, minimalist_tech, cinematic).
     * @return array
     * @throws \Exception
     */
    public function generateBlueprint(string $userInput, string $stylePreset = 'aggressive_cuts'): array
    {
        $apiKey = ApiSetting::getApiKey();
        if (!$apiKey) {
            throw new \Exception('OpenRouter API key not configured.');
        }

        $styleDirective = "";
        switch ($stylePreset) {
            case 'aggressive_cuts':
                $styleDirective = "STYLE: Aggressive Cuts (The TikTok/Reels Growth Formula)\n"
                                . "- Write a high-energy, fast-paced script.\n"
                                . "- Force camera zooms (avatar_transform) on almost every individual sentence or dramatic punchline.\n"
                                . "- Keep scene shifts highly localized. Script short, hard-hitting words.\n"
                                . "- B-roll requests should favor intense action or relatable lifestyle movements.\n"
                                . "- Video duration MUST be strictly between 10 and 15 seconds maximum.";
                break;
            case 'minimalist_tech':
                $styleDirective = "STYLE: Minimalist Tech (The MKBHD / Software Product Style)\n"
                                . "- Adopt a clean, professional, authoritative tone.\n"
                                . "- Keep the presenter avatar at a stable, centered scale, using subtle zooms only when introducing a new topic section.\n"
                                . "- B-roll queries must be highly specific to tech UI, clean workspaces, or macro product shots.\n"
                                . "- Focus on sleek, smooth transitions and ample visual breathing room.\n"
                                . "- Video duration MUST be between 15 and 25 seconds.";
                break;
            case 'cinematic':
                $styleDirective = "STYLE: Cinematic (The Visual Storyteller)\n"
                                . "- Craft a deep, atmospheric, problem-driven narrative hook.\n"
                                . "- Use dramatic adjectives for your Pexels query mapping (e.g., 'moody lighting, dramatic shadows, slow motion vertical bokeh, cinematic city rain at night').\n"
                                . "- The pacing should alternate between slow, high-concept visual build-ups and quick narrative shifts to maintain an artistic, premium look.\n"
                                . "- Video duration MUST be between 20 and 30 seconds.";
                break;
        }

        $systemPrompt = <<<PROMPT
You are an elite, hyper-growth TikTok and Reels video editor and director.
Your task is to take the user's prompt or product link and output a highly converting, fast-paced UGC (User Generated Content) short-form video script.

{$styleDirective}

RULES:
1. You MUST script a strong, pattern-interrupting hook in the first 3 seconds of the video.
2. You MUST inject a minimum of 3 avatar camera zooms (via `avatar_transform` scale and position) across different scenes to prevent viewer boredom. (e.g. scale 1.0 to 1.3 or 1.5).
3. Do NOT generate any stock video or image URLs. Instead, output a precise `design_tokens` object for the first scene (and subsequent ones if the design changes). This includes CSS hex colors for gradients and text highlighting.
4. You MUST output precise structural timestamps for scenes and word-by-word captions.
5. The values inside the generated timestamp keys (the sum of all scene durations) MUST add up exactly to your chosen `video_duration` property.
6. Ensure all timings match up perfectly, e.g. a scene with `duration: 3.0` and `start_time: 0.0` means the next scene must have `start_time: 3.0`. Caption timings must fit within the scene duration.

OUTPUT ONLY VALID JSON MATCHING THIS EXACT SCHEMA (No markdown wrappers like ```json, no explanations):
{
  "video_duration": 22.5,
  "resolution": { "width": 1080, "height": 1920 },
  "scenes": [
    {
      "start_time": 0.0,
      "duration": 3.0,
      "dialogue": "Spoken text here...",
      "avatar_transform": {
        "scale": 1.3,
        "position": { "x": "0%", "y": "10%" }
      },
      "design_tokens": {
        "background_type": "linear-gradient",
        "background_colors": ["#FF0055", "#000000"],
        "gradient_angle": "135deg",
        "font_family": "Inter",
        "font_weight": "800",
        "text_color_primary": "#FFFFFF",
        "text_color_highlight": "#FF0055",
        "text_animation_style": "kinetic-bounce",
        "caption_position_y": "80%"
      },
      "captions": [
        { "word": "Spoken", "start": 0.1, "end": 0.5 },
        { "word": "text", "start": 0.5, "end": 1.0 },
        { "word": "here...", "start": 1.0, "end": 1.5 }
      ]
    }
  ]
}
PROMPT;

        $payload = json_encode([
            'model' => 'google/gemini-2.5-flash',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userInput]
            ],
            'temperature' => 0.7,
        ]);

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . url('/'),
                'X-Title: Yantra Studio UGC Director',
            ],
        ]);

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError) {
            throw new \Exception("API request failed: " . $curlError);
        }

        $result = json_decode($responseBody, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            throw new \Exception($result['error']['message'] ?? 'AI did not return a response.');
        }

        $rawContent = trim($result['choices'][0]['message']['content']);
        $rawContent = preg_replace('/^```(?:json)?\s*/i', '', $rawContent);
        $rawContent = preg_replace('/\s*```$/', '', $rawContent);

        $blueprint = json_decode($rawContent, true);
        if (!is_array($blueprint) || !isset($blueprint['video_duration']) || !isset($blueprint['scenes'])) {
            throw new \Exception("AI returned invalid JSON schema. Please try again.");
        }

        return $blueprint;
    }
}
