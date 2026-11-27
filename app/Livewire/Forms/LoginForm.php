<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Resolve user by email, mobile number or User Code.
     */
    public function resolveUserForLogin(string $login): ?\App\Models\User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        // 1. Direct Email check
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return \App\Models\User::withoutGlobalScopes()->where('email', $login)->first();
        }

        // 2. User Code check (e.g. LP00001, LP-00001, case-insensitive)
        if (preg_match('/^[a-zA-Z]+-?\d+$/', $login)) {
            $cleanLogin = str_replace('-', '', $login);
            $userByCode = \App\Models\User::withoutGlobalScopes()
                ->where(function ($q) use ($login, $cleanLogin) {
                    $q->whereRaw('LOWER(user_code) = ?', [strtolower($login)])
                        ->orWhereRaw('REPLACE(LOWER(user_code), "-", "") = ?', [strtolower($cleanLogin)]);
                })
                ->first();
            if ($userByCode) {
                return $userByCode;
            }
        }

        // 3. Mobile number check with normalization
        $digits = preg_replace('/[^0-9]/', '', $login);
        if (!empty($digits) && strlen($digits) >= 7) {
            $query = \App\Models\User::withoutGlobalScopes()->where(function ($q) use ($login, $digits) {
                $q->where('phone', $login)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', '+' . $digits);
                if (strlen($digits) >= 10) {
                    $last10 = substr($digits, -10);
                    $q->orWhere('phone', 'like', "%{$last10}");
                }
            });

            $matches = $query->get();
            if ($matches->count() === 1) {
                return $matches->first();
            } elseif ($matches->count() > 1) {
                throw ValidationException::withMessages([
                    'form.email' => 'Multiple accounts found with this mobile number. Please log in using your Email or User Code.',
                ]);
            }
        }

        // 4. Fallback search (exact match on email, user_code, or phone)
        return \App\Models\User::withoutGlobalScopes()
            ->where('email', $login)
            ->orWhereRaw('LOWER(user_code) = ?', [strtolower($login)])
            ->orWhere('phone', $login)
            ->first();
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = $this->resolveUserForLogin($this->email);
        $credentials = [
            'email' => $user ? $user->email : $this->email,
            'password' => $this->password,
            'status' => 'active',
        ];

        if (!Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}
