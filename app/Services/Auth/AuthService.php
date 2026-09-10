<?php

namespace App\Services\Auth;

use App\AuditLog;
use App\Exceptions\Auth\AuthException;
use App\LoginLog;
use App\Role;
use App\SocialAccount;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthService
{
    public function register(array $data, Request $request)
    {
        $email = strtolower(trim($data['email']));

        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new AuthException('AUTH_EMAIL_ALREADY_EXISTS', 'El email ya está registrado.', 409);
        }

        $username = isset($data['username']) ? $data['username'] : $this->generateUsername($email);

        $user = DB::transaction(function () use ($data, $email, $username, $request) {
            $user = User::create([
                'name'     => $data['name'],
                'lastname' => isset($data['lastname']) ? $data['lastname'] : '',
                'username' => $username,
                'email'    => $email,
                'password' => Hash::make($data['password']),
                'status'   => 'active',
            ]);

            $this->assignDefaultRole($user);
            $this->writeAudit($user->id, 'register', 'users', $user->id, null, [
                'name'   => $user->name,
                'email'  => $user->email,
                'status' => $user->status,
            ], $request);

            return $user;
        });

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->update(['last_login_at' => now()]);
        $this->writeLoginLog($user->id, $user->email, true, null, $request);

        return $user->load('roles');
    }

    public function login(array $data, Request $request)
    {
        $email    = strtolower(trim($data['email']));
        $remember = (bool) (isset($data['remember']) ? $data['remember'] : false);

        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            $userId = $user ? $user->id : null;
            $this->writeLoginLog($userId, $email, false, 'AUTH_INVALID_CREDENTIALS', $request);
            throw new AuthException('AUTH_INVALID_CREDENTIALS', 'Credenciales incorrectas.', 401);
        }

        if ($user->trashed()) {
            $this->writeLoginLog($user->id, $email, false, 'AUTH_INVALID_CREDENTIALS', $request);
            throw new AuthException('AUTH_INVALID_CREDENTIALS', 'Credenciales incorrectas.', 401);
        }

        if ($user->status !== 'active') {
            $this->writeLoginLog($user->id, $email, false, 'AUTH_USER_INACTIVE', $request);
            throw new AuthException('AUTH_USER_INACTIVE', 'La cuenta no está activa.', 403);
        }

        Auth::login($user, $remember);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->update(['last_login_at' => now()]);
        $this->writeLoginLog($user->id, $email, true, null, $request);

        return $user->load('roles');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    public function me(User $user)
    {
        return $user->load('roles');
    }

    public function updateProfile(User $user, array $data, Request $request)
    {
        $allowed = ['name', 'lastname', 'username', 'phone', 'avatar_url'];
        $update  = array_intersect_key($data, array_flip($allowed));

        if (empty($update)) {
            return $user->load('roles');
        }

        $before = $user->only(array_keys($update));

        $user->update($update);
        $user->refresh();

        $this->writeAudit($user->id, 'update', 'users', $user->id, $before, $user->only(array_keys($update)), $request);

        return $user->load('roles');
    }

    public function googleAuth(string $token, Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($token);
        } catch (\Exception $e) {
            throw new AuthException('AUTH_GOOGLE_FAILED', 'No se pudo verificar la cuenta de Google.', 401);
        }

        if (!$googleUser->getEmail()) {
            throw new AuthException('AUTH_GOOGLE_FAILED', 'La cuenta de Google no tiene email asociado.', 422);
        }

        $socialAccount = SocialAccount::where('provider', 'google')
            ->where('provider_user_id', (string) $googleUser->getId())
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;
        } else {
            $existing = User::withTrashed()->where('email', $googleUser->getEmail())->first();

            if ($existing && $existing->trashed()) {
                throw new AuthException('AUTH_GOOGLE_ACCOUNT_CONFLICT', 'La cuenta asociada a ese email ha sido eliminada.', 409);
            }

            $user = DB::transaction(function () use ($existing, $googleUser, $request) {
                if (!$existing) {
                    $existing = User::create([
                        'name'       => $googleUser->getName() ?: explode('@', $googleUser->getEmail())[0],
                        'lastname'   => '',
                        'username'   => $this->generateUsername($googleUser->getEmail()),
                        'email'      => $googleUser->getEmail(),
                        'password'   => Hash::make(Str::random(32)),
                        'avatar_url' => $googleUser->getAvatar(),
                        'status'     => 'active',
                    ]);
                    $this->assignDefaultRole($existing);
                    $this->writeAudit($existing->id, 'register', 'users', $existing->id, null, [
                        'name'     => $existing->name,
                        'email'    => $existing->email,
                        'provider' => 'google',
                        'status'   => $existing->status,
                    ], $request);
                }

                SocialAccount::create([
                    'user_id'          => $existing->id,
                    'provider'         => 'google',
                    'provider_user_id' => (string) $googleUser->getId(),
                    'provider_email'   => $googleUser->getEmail(),
                    'avatar_url'       => $googleUser->getAvatar(),
                ]);

                return $existing;
            });
        }

        if ($user->status !== 'active') {
            throw new AuthException('AUTH_USER_INACTIVE', 'La cuenta no está activa.', 403);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->update(['last_login_at' => now()]);
        $this->writeLoginLog($user->id, $user->email, true, null, $request);

        return $user->load('roles');
    }

    private function generateUsername(string $email)
    {
        $base     = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0]));
        $base     = $base ?: 'user';
        $username = $base;
        $counter  = 1;

        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $base . $counter++;
        }

        return $username;
    }

    private function assignDefaultRole(User $user)
    {
        // Fuente de verdad unica: App\User::assignDefaultRole().
        $user->assignDefaultRole();
    }

    private function writeLoginLog($userId, $email, $success, $failureReason, Request $request)
    {
        LoginLog::create([
            'user_id'        => $userId,
            'email'          => $email,
            'success'        => $success,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'failure_reason' => $failureReason,
        ]);
    }

    private function writeAudit($actorId, $action, $entityName, $entityId, $before, $after, $request)
    {
        AuditLog::create([
            'user_id'     => $actorId,
            'action'      => $action,
            'entity_name' => $entityName,
            'entity_id'   => $entityId,
            'old_values'  => $before,
            'new_values'  => $after,
            'ip_address'  => $request ? $request->ip() : null,
            'user_agent'  => $request ? $request->userAgent() : null,
        ]);
    }
}
