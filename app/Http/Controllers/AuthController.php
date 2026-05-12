<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ApiSetting;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (Auth::user()->isAdmin()) {
                return redirect()->intended('/admin');
            }
            return redirect()->intended('/studio');
        }

        return back()->withErrors([
            'email' => 'Invalid email or password.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $bonusCredits = (float) ApiSetting::getValue('signup_bonus_credits', 50);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'credits'  => $bonusCredits,
        ]);

        // Log the signup bonus
        if ($bonusCredits > 0) {
            $bonusNpr = ApiSetting::creditsToNpr($bonusCredits);
            CreditTransaction::create([
                'user_id'     => $user->id,
                'amount'      => $bonusCredits,
                'type'        => 'signup_bonus',
                'description' => "Signup bonus: \u20a8{$bonusNpr} ({$bonusCredits} credits)",
            ]);
        }

        Auth::login($user);

        return redirect('/studio');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
