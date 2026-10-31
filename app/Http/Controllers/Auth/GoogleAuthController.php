<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth provider.
     */
    public function redirect(): RedirectResponse
    {
        if (empty(config('services.google.client_id'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Google Client ID belum dikonfigurasi di file .env. Silakan gunakan login email & password atau isi GOOGLE_CLIENT_ID.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if (! $user) {
                // Auto-register organizer from Google
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => null, // Password nullable for Google users
                    'role' => 'organizer',
                    'email_verified_at' => now(),
                ]);
            } else {
                // Update avatar if available
                if ($googleUser->getAvatar() && ! $user->avatar) {
                    $user->update(['avatar' => $googleUser->getAvatar()]);
                }
            }

            Auth::login($user, true);

            return redirect()->intended(route('organizer.dashboard'));
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Autentikasi Google gagal: ' . $e->getMessage(),
            ]);
        }
    }
}

