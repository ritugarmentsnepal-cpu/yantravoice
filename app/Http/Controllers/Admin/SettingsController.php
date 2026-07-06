<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'openrouter_api_key'          => ApiSetting::getValue('openrouter_api_key', ''),
            'heygen_api_key'              => ApiSetting::getValue('heygen_api_key', ''),
            'admin_cost_per_generation_usd' => ApiSetting::getValue('admin_cost_per_generation_usd', '0.005'),
            'credit_cost_per_generation'  => ApiSetting::getValue('credit_cost_per_generation', '2'),
            'signup_bonus_credits'        => ApiSetting::getValue('signup_bonus_credits', '50'),
            'video_render_cost'           => ApiSetting::getValue('video_render_cost', '5'),
            't2v_generation_cost'         => ApiSetting::getValue('t2v_generation_cost', '10'),
        ];

        // QR code URL
        $qrPath = ApiSetting::getPaymentQrPath();
        $currentQrUrl = $qrPath ? asset('storage/' . $qrPath) : null;

        // Logo URL
        $currentLogoUrl = ApiSetting::getLogoUrl();

        return view('admin.settings.index', compact('settings', 'currentQrUrl', 'currentLogoUrl'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'openrouter_api_key'            => 'nullable|string|min:10',
            'heygen_api_key'                => 'nullable|string|min:10',
            'admin_cost_per_generation_usd' => 'required|numeric|min:0.0001',
            'credit_cost_per_generation'    => 'required|numeric|min:0.1',
            'signup_bonus_credits'          => 'required|numeric|min:0|max:10000',
            'video_render_cost'             => 'required|numeric|min:1',
            't2v_generation_cost'           => 'required|numeric|min:1',

            // Files
            'payment_qr'  => 'nullable|image|max:5120',
            'app_logo'    => 'nullable|image|max:5120',
        ]);

        // Handle QR upload
        if ($request->hasFile('payment_qr')) {
            $path = $request->file('payment_qr')->store('payment-qr', 'public');
            ApiSetting::setValue('payment_qr_path', $path);
        }

        // Handle Logo upload
        if ($request->hasFile('app_logo')) {
            $path = $request->file('app_logo')->store('app-logo', 'public');
            ApiSetting::setValue('app_logo_path', $path);
        }

        // Save other settings (exclude file fields)
        unset($validated['payment_qr'], $validated['app_logo']);
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                ApiSetting::setValue($key, (string) $value);
            }
        }

        return back()->with('success', 'Settings updated successfully');
    }

    public function syncAvatars()
    {
        $key = ApiSetting::getHeyGenApiKey();
        if (!$key) {
            return back()->with('error', 'Please configure your HeyGen API Key first.');
        }

        $ch = curl_init('https://api.heygen.com/v2/avatars');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $key,
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return back()->with('error', 'Curl Error: ' . $err);
        }

        $data = json_decode($response, true);
        if (!isset($data['data']['avatars'])) {
            return back()->with('error', 'Failed to fetch avatars. Response: ' . substr($response, 0, 100));
        }

        // Delete old/invalid system avatars to prevent them from showing up in the dropdown
        \App\Models\Avatar::where('is_custom', false)->delete();

        $count = 0;
        foreach ($data['data']['avatars'] as $avatarData) {
            $avatarId = $avatarData['avatar_id'] ?? null;
            if (!$avatarId) continue;

            \App\Models\Avatar::updateOrCreate(
                ['heygen_avatar_id' => $avatarId],
                [
                    'name' => $avatarData['avatar_name'] ?? 'HeyGen Avatar',
                    'gender' => ucfirst($avatarData['gender'] ?? 'Unknown'),
                    'preview_image_url' => $avatarData['preview_image_url'] ?? 'https://via.placeholder.com/200?text=Avatar',
                    'is_custom' => false
                ]
            );
            $count++;
        }

        return back()->with('success', "Successfully synced {$count} avatars from your HeyGen account!");
    }
}
