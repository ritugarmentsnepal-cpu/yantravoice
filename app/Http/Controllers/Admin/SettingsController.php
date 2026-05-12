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
            'admin_cost_per_generation_usd' => ApiSetting::getValue('admin_cost_per_generation_usd', '0.005'),
            'credit_cost_per_generation'  => ApiSetting::getValue('credit_cost_per_generation', '2'),
            'signup_bonus_credits'        => ApiSetting::getValue('signup_bonus_credits', '50'),
            'video_render_cost'           => ApiSetting::getValue('video_render_cost', '5'),
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
            'admin_cost_per_generation_usd' => 'required|numeric|min:0.0001',
            'credit_cost_per_generation'    => 'required|numeric|min:0.1',
            'signup_bonus_credits'          => 'required|numeric|min:0|max:10000',
            'video_render_cost'             => 'required|numeric|min:1',

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
}
