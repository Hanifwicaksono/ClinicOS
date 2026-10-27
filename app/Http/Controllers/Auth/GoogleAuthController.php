<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\UserAccountType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $request->boolean('register')) {
            $request->session()->forget('auth.google.terms_accepted_at');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $termsAcceptedAt = $request->session()->pull('auth.google.terms_accepted_at');
            $user = $this->resolveUser(
                Socialite::driver('google')->user(),
                is_string($termsAcceptedAt) ? $termsAcceptedAt : null,
            );
        } catch (ValidationException $exception) {
            $route = array_key_exists('accept_terms', $exception->errors()) ? 'register' : 'login';

            return redirect()->route($route)->withErrors($exception->errors());
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'google' => 'Tidak dapat masuk dengan Google. Silakan coba lagi.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function resolveUser(GoogleUser $googleUser, ?string $termsAcceptedAt): User
    {
        $googleId = trim((string) $googleUser->getId());
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $rawUser = $googleUser->getRaw();
        $emailVerified = filter_var(
            $rawUser['verified_email'] ?? $rawUser['email_verified'] ?? false,
            FILTER_VALIDATE_BOOL,
        ) === true;

        if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $emailVerified) {
            throw ValidationException::withMessages([
                'google' => 'Akun Google harus memiliki email yang terverifikasi.',
            ]);
        }

        return DB::transaction(function () use ($googleUser, $googleId, $email, $termsAcceptedAt): User {
            $googleAccount = User::query()->where('google_id', $googleId)->lockForUpdate()->first();
            $emailAccount = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($googleAccount !== null && $emailAccount !== null && ! $googleAccount->is($emailAccount)) {
                throw ValidationException::withMessages([
                    'google' => 'Akun Google tidak dapat ditautkan ke akun pasien ini.',
                ]);
            }

            $user = $googleAccount ?? $emailAccount;

            if ($user !== null) {
                if (! $user->is_active
                    || $user->account_type !== UserAccountType::Patient
                    || ! $user->hasRole('Patient')
                    || ($user->google_id !== null && $user->google_id !== $googleId)) {
                    throw ValidationException::withMessages([
                        'google' => 'Email ini tidak dapat digunakan untuk login pasien.',
                    ]);
                }

                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return $user;
            }

            if ($termsAcceptedAt === null) {
                throw ValidationException::withMessages([
                    'accept_terms' => 'Anda harus menyetujui syarat dan ketentuan untuk membuat akun.',
                ]);
            }

            $user = User::query()->create([
                'account_type' => UserAccountType::Patient,
                'name' => trim((string) $googleUser->getName()) ?: Str::before($email, '@'),
                'email' => $email,
                'email_verified_at' => now(),
                'google_id' => $googleId,
                'password' => Str::random(64),
                'terms_accepted_at' => $termsAcceptedAt,
            ]);
            $user->assignRole(Role::firstOrCreate(['name' => 'Patient', 'guard_name' => 'web']));

            return $user;
        });
    }
}
