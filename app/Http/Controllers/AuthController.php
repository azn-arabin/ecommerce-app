<?php

namespace App\Http\Controllers;

use App\Models\SsoToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Partner app configuration (Foodpanda)
    |--------------------------------------------------------------------------
    */
    private function partnerUrl(): ?string
    {
        return env('FOODPANDA_APP_URL', 'http://localhost:8001');
    }

    private function appUrl(): string
    {
        return env('APP_URL', 'http://localhost:8000');
    }

    private function ssoSecret(): string
    {
        return env('SSO_SECRET_KEY', 'your-shared-secret-key-change-this');
    }

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    */
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $partnerUrl = $this->partnerUrl();

        // Generate a fresh SSO token so the cross-link works
        $ssoToken = $this->createSsoToken($user);
        $ssoLink = $partnerUrl . '/sso/callback?' . http_build_query([
            'token' => $ssoToken,
            'issuer' => $this->appUrl(),
            'return_to' => $partnerUrl . '/dashboard',
        ]);

        return view('dashboard', compact('user', 'partnerUrl', 'ssoLink'));
    }

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Sync user to Foodpanda so credentials exist there too
        $this->syncUserToPartner($user, $request->password);

        Auth::login($user);

        // Redirect through Foodpanda to establish a session there, then come back
        return $this->redirectThroughPartner($user, route('dashboard'));
    }

    /*
    |--------------------------------------------------------------------------
    | Login  –  the KEY SSO change
    |--------------------------------------------------------------------------
    | After authenticating locally we redirect the browser through the partner
    | app's /sso/callback endpoint.  That endpoint validates our one-time token
    | via our API, creates a local session for the user, and sends the browser
    | back to our dashboard.  The user now has sessions on BOTH apps.
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Redirect through Foodpanda to auto-login the user there
            return $this->redirectThroughPartner($user, route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /*
    |--------------------------------------------------------------------------
    | Logout  –  logs out here, then redirects to partner to log out there too
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $partnerUrl = $this->partnerUrl();
        if ($partnerUrl) {
            return redirect($partnerUrl . '/sso/logout?' . http_build_query([
                'return_to' => route('login'),
            ]));
        }

        return redirect()->route('login')->with('success', 'Logged out successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | SSO Callback  –  receives redirect FROM the partner app
    |--------------------------------------------------------------------------
    | The partner app sent the user here with a one-time token.
    | We validate it by calling the partner's API, then log the user in locally.
    |--------------------------------------------------------------------------
    */
    public function ssoCallback(Request $request)
    {
        $token = $request->query('token');
        $issuer = $request->query('issuer');
        $returnTo = $request->query('return_to', route('dashboard'));

        // Already logged in → just go where we need to go
        if (Auth::check()) {
            return redirect($returnTo);
        }

        if ($token && $issuer) {
            try {
                // Ask the issuing app to validate & consume the token
                $response = Http::timeout(5)->post($issuer . '/api/sso/validate', [
                    'token' => $token,
                    'secret' => $this->ssoSecret(),
                ]);

                if ($response->successful() && $response->json('success')) {
                    $userData = $response->json('user');

                    $user = User::firstOrCreate(
                        ['email' => $userData['email']],
                        ['name' => $userData['name'], 'password' => Hash::make(Str::random(32))]
                    );

                    Auth::login($user);
                    $request->session()->regenerate();
                }
            } catch (\Exception $e) {
                \Log::error('SSO callback failed: ' . $e->getMessage());
            }
        }

        return redirect($returnTo);
    }

    /*
    |--------------------------------------------------------------------------
    | SSO Logout  –  partner app redirects here so we log out locally too
    |--------------------------------------------------------------------------
    */
    public function ssoLogout(Request $request)
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $returnTo = $request->query('return_to', route('login'));
        return redirect($returnTo)->with('success', 'You have been logged out from both systems.');
    }

    /*
    |==========================================================================
    | API ENDPOINTS  (called server-to-server by the partner app)
    |==========================================================================
    */

    /**
     * Validate & consume a one-time SSO token.
     * Called by the partner app's ssoCallback method.
     */
    public function apiSsoValidate(Request $request)
    {
        if ($request->input('secret') !== $this->ssoSecret()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $ssoToken = SsoToken::where('token', $request->input('token'))
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$ssoToken) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $ssoToken->update(['is_used' => true]);

        return response()->json([
            'success' => true,
            'user' => [
                'email' => $ssoToken->user_email,
                'name' => $ssoToken->user_name,
            ],
        ]);
    }

    /**
     * Receive user-sync from partner app registration.
     */
    public function apiSyncUser(Request $request)
    {
        if ($request->input('secret') !== $this->ssoSecret()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        User::firstOrCreate(
            ['email' => $request->input('email')],
            [
                'name' => $request->input('name'),
                'password' => Hash::make($request->input('password')),
            ]
        );

        return response()->json(['success' => true]);
    }

    /*
    |==========================================================================
    | PRIVATE HELPERS
    |==========================================================================
    */

    /**
     * Create a one-time SSO token stored in the database.
     */
    private function createSsoToken($user): string
    {
        // Clean up old expired tokens periodically
        SsoToken::where('expires_at', '<', now())->delete();

        $token = Str::random(64);

        SsoToken::create([
            'token' => $token,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'expires_at' => now()->addMinutes(5),
        ]);

        return $token;
    }

    /**
     * After local login, redirect the user's browser through the partner app
     * so it can create a session there too, then bounce back to $finalDestination.
     */
    private function redirectThroughPartner($user, string $finalDestination)
    {
        $partnerUrl = $this->partnerUrl();

        if (!$partnerUrl) {
            return redirect($finalDestination)->with('success', 'Login successful!');
        }

        $ssoToken = $this->createSsoToken($user);

        return redirect($partnerUrl . '/sso/callback?' . http_build_query([
            'token' => $ssoToken,
            'issuer' => $this->appUrl(),
            'return_to' => $finalDestination,
        ]));
    }

    /**
     * Sync user credentials to partner app on registration.
     */
    private function syncUserToPartner($user, string $plainPassword): void
    {
        try {
            $partnerUrl = $this->partnerUrl();
            if (!$partnerUrl)
                return;

            Http::timeout(5)->post($partnerUrl . '/api/sso/sync-user', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $plainPassword,
                'secret' => $this->ssoSecret(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to sync user to partner: ' . $e->getMessage());
        }
    }
}
