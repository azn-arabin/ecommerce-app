<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Show the register form
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle user registration
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

        // Sync user to Foodpanda app
        $this->syncUserToFoodpanda($user, $request->password);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Registration successful! You are now logged in to both E-Commerce and Foodpanda systems.');
    }

    /**
     * Handle user login with SSO
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

            // Generate SSO token for cross-app authentication
            $ssoToken = $this->generateSSOToken($user);

            // Store SSO token in session for automatic login to Foodpanda
            session(['sso_token' => $ssoToken]);

            // Notify Foodpanda app about the login
            $this->notifyFoodpandaLogin($user, $ssoToken);

            return redirect()->route('dashboard')
                ->with('success', 'Login successful! You are now logged in to both systems.');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request)
    {
        // Notify Foodpanda app about logout
        if (session('sso_token')) {
            $this->notifyFoodpandaLogout(session('sso_token'));
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out from both systems.');
    }

    /**
     * Show dashboard
     */
    public function dashboard()
    {
        $user = Auth::user();
        $foodpandaUrl = env('FOODPANDA_APP_URL', 'http://localhost:8001');
        $ssoToken = session('sso_token', '');

        return view('dashboard', compact('user', 'foodpandaUrl', 'ssoToken'));
    }

    /**
     * Generate SSO token for cross-app authentication
     */
    private function generateSSOToken($user)
    {
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'timestamp' => time(),
        ];

        $secretKey = env('SSO_SECRET_KEY', 'your-shared-secret-key');
        $tokenData = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $tokenData, $secretKey);

        return $tokenData . '.' . $signature;
    }

    /**
     * Sync user to Foodpanda app
     */
    private function syncUserToFoodpanda($user, $plainPassword)
    {
        try {
            $foodpandaUrl = env('FOODPANDA_APP_URL');
            if (!$foodpandaUrl) {
                return;
            }

            Http::timeout(5)->post($foodpandaUrl . '/api/sync-user', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $plainPassword,
                'secret' => env('SSO_SECRET_KEY'),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail registration
            \Log::error('Failed to sync user to Foodpanda: ' . $e->getMessage());
        }
    }

    /**
     * Notify Foodpanda app about login
     */
    private function notifyFoodpandaLogin($user, $ssoToken)
    {
        try {
            $foodpandaUrl = env('FOODPANDA_APP_URL');
            if (!$foodpandaUrl) {
                return;
            }

            Http::timeout(5)->post($foodpandaUrl . '/api/sso-login', [
                'sso_token' => $ssoToken,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to notify Foodpanda about login: ' . $e->getMessage());
        }
    }

    /**
     * Notify Foodpanda app about logout
     */
    private function notifyFoodpandaLogout($ssoToken)
    {
        try {
            $foodpandaUrl = env('FOODPANDA_APP_URL');
            if (!$foodpandaUrl) {
                return;
            }

            Http::timeout(5)->post($foodpandaUrl . '/api/sso-logout', [
                'sso_token' => $ssoToken,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to notify Foodpanda about logout: ' . $e->getMessage());
        }
    }

    /**
     * API endpoint to handle SSO login from Foodpanda
     */
    public function apiSSOLogin(Request $request)
    {
        $ssoToken = $request->input('sso_token');

        if (!$ssoToken) {
            return response()->json(['error' => 'SSO token required'], 400);
        }

        $user = $this->validateSSOToken($ssoToken);

        if (!$user) {
            return response()->json(['error' => 'Invalid SSO token'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * Validate SSO token
     */
    private function validateSSOToken($token)
    {
        try {
            list($tokenData, $signature) = explode('.', $token);
            $secretKey = env('SSO_SECRET_KEY', 'your-shared-secret-key');
            $expectedSignature = hash_hmac('sha256', $tokenData, $secretKey);

            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            $payload = json_decode(base64_decode($tokenData), true);

            // Check if token is not too old (1 hour expiry)
            if (time() - $payload['timestamp'] > 3600) {
                return null;
            }

            return User::where('email', $payload['email'])->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * API endpoint to sync user from Foodpanda
     */
    public function apiSyncUser(Request $request)
    {
        $secret = $request->input('secret');

        if ($secret !== env('SSO_SECRET_KEY')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }
}
