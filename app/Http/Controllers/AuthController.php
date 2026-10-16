<?php

namespace App\Http\Controllers;

use App\Models\SecurityQuestion;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Messaging\SmsVerificationProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request, SmsVerificationProvider $sms): RedirectResponse
    {
        // Phase 27.6 — email OR phone. Purely additive: an email-only
        // registration validates exactly as it did before (email is still
        // required unless a phone number is supplied instead).
        //
        // Phone-ONLY sign-up is gated on a real SMS provider being configured,
        // because an account with no email and no way to receive a verification
        // code could never pass the `verified` middleware. With no provider
        // configured (the current state) a phone number is accepted as an
        // optional extra identifier alongside an email address, and a
        // phone-only attempt is rejected with an honest message rather than
        // creating an unusable account.
        $phoneSignupAvailable = $sms->isConfigured();

        // Normalise before validating so the uniqueness check runs against the
        // same shape that gets stored.
        if ($request->has('phone')) {
            $request->merge(['phone' => $this->normalizePhone($request->input('phone'))]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 .\-()]{6,30}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
            'security_question' => ['required', 'string'],
            'security_answer' => ['required', 'string', 'min:2'],
        ];

        $rules['email'][] = $phoneSignupAvailable ? 'required_without:phone' : 'required';

        $data = $request->validate($rules, [
            'email.required' => $phoneSignupAvailable
                ? 'Provide an email address or a phone number.'
                : 'An email address is required. Phone-only sign-up is unavailable until an SMS provider is configured.',
            'email.required_without' => 'Provide an email address or a phone number.',
            'phone.regex' => 'Enter a valid phone number.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        // Create user preferences
        $locale = $request->session()->get(
            'locale',
            $request->cookie('lutapp_locale', config('app.locale', 'en'))
        );

        UserPreference::create([
            'user_id' => $user->id,
            'language' => array_key_exists($locale, config('app.supported_locales', []))
                ? $locale
                : config('app.fallback_locale', 'en'),
        ]);

        // Save security question (hashed answer)
        SecurityQuestion::create([
            'user_id' => $user->id,
            'question' => $data['security_question'],
            'answer_hash' => Hash::make(strtolower(trim($data['security_answer']))),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // Registered dispatches Laravel's email-verification listener, which is
        // only meaningful for an account that actually has an address.
        if (filled($user->email)) {
            event(new Registered($user));

            return redirect()->route('verification.notice');
        }

        // Phone-only path (reachable only with a configured SMS provider).
        // Reuses the same "issue a code, then verify" shape as email
        // verification rather than inventing a parallel mechanism.
        $this->issuePhoneVerificationCode($request, $user, $sms);

        return redirect()->route('verification.phone.notice');
    }

    /**
     * Phase 27.6 — phone verification, mirroring the email verification flow.
     *
     * NOTHING IS SENT unless a real provider is bound. The only provider that
     * exists today (NullSmsVerificationProvider) writes a log line and returns
     * false; this method reports that honestly rather than implying delivery.
     */
    private function issuePhoneVerificationCode(Request $request, User $user, SmsVerificationProvider $sms): bool
    {
        $code = (string) random_int(100000, 999999);

        $request->session()->put('phone_verification', [
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);

        return $sms->sendVerificationCode((string) $user->phone, $code);
    }

    public function phoneVerifyNotice(Request $request, SmsVerificationProvider $sms): View
    {
        return view('auth.verify-phone', [
            'phone' => $request->user()?->phone,
            'providerConfigured' => $sms->isConfigured(),
        ]);
    }

    public function phoneVerify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $pending = $request->session()->get('phone_verification');
        $user = $request->user();

        if (! $user || ! is_array($pending) || ($pending['user_id'] ?? null) !== $user->id
            || now()->greaterThan($pending['expires_at'])
            || ! Hash::check($data['code'], $pending['code_hash'])) {
            return back()->withErrors(['code' => 'That verification code is not valid or has expired.']);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();
        $request->session()->forget('phone_verification');

        return redirect()->route('dashboard');
    }

    /** Keeps a single storage shape so uniqueness checks are meaningful. */
    private function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        // Only formatting characters are removed. Anything else (letters, for
        // instance) is left in place so validation rejects it rather than the
        // input being silently mangled into something valid.
        $normalized = preg_replace('/[\s.\-()]/', '', $phone);

        return $normalized === '' ? null : $normalized;
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        // Phase 27.6 — the same single field now also accepts a phone number.
        // An email address behaves exactly as before; anything that is not a
        // valid email is tried against the phone column instead. Existing
        // email accounts, and the existing login form, are unchanged.
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $isEmail = filter_var($data['email'], FILTER_VALIDATE_EMAIL) !== false;

        $phone = $isEmail ? null : $this->normalizePhone($data['email']);

        // A null phone would match every account that has no phone number —
        // never attempt with one.
        $credentials = $isEmail
            ? ['email' => $data['email'], 'password' => $data['password']]
            : ['phone' => $phone, 'password' => $data['password']];

        if (blank($phone) && ! $isEmail) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        // Checked before session regeneration — a suspended/deactivated
        // account never gets a valid authenticated session at all, matching
        // EnsureAccountIsActive's enforcement for an already-logged-in user.
        if ($user->isAccountHidden()) {
            Auth::logout();

            return back()->withErrors([
                'email' => $user->account_status === 'suspended'
                    ? 'Your account has been suspended.'
                    : 'Your account has been deactivated.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        // Check if the account's identifier is verified. Email-based accounts
        // follow exactly the pre-existing path.
        if (! $user->hasVerifiedEmail()) {
            if (blank($user->email)) {
                return redirect()->route('verification.phone.notice');
            }

            return redirect()->route('verification.notice')->with('email', $user->email);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // Email Verification Routes
    public function verifyNotice(): View
    {
        return view('auth.verify-email');
    }

    public function verifyEmail(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard').'?verified=1');
        }

        if ($request->fulfill()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard').'?verified=1');
    }

    public function verifyResend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('resent', true);
    }

    // Password Reset Routes
    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetForm(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
