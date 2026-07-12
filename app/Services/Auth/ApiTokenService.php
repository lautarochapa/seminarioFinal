<?php

namespace App\Services\Auth;

use App\ApiToken;
use App\Exceptions\Auth\AuthException;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function issue(User $user, string $name = 'api', int $ttlMinutes = 10080): array
    {
        $user->load('roles');
        $permissions = $user->permissions()->pluck('code')->values()->all();
        $roles = $user->roles->pluck('code')->values()->all();
        $now = time();
        $exp = $now + ($ttlMinutes * 60);
        $jti = (string) Str::uuid();

        $payload = [
            'iss' => config('app.url'),
            'sub' => (int) $user->id,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $exp,
            'roles' => $roles,
            'permissions' => $permissions,
        ];

        $token = $this->encode($payload);

        ApiToken::create([
            'user_id' => $user->id,
            'jti' => $jti,
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => date(DATE_ATOM, $exp),
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    public function authenticate(?string $token): ?User
    {
        if (!$token) {
            return null;
        }

        $payload = $this->decode($token);
        $record = ApiToken::where('jti', $payload['jti'])
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();

        if (!$record || ($record->expires_at && $record->expires_at->isPast())) {
            throw new AuthException('AUTH_TOKEN_INVALID', 'Token invalido o expirado.', 401);
        }

        $record->update(['last_used_at' => now()]);
        $user = $record->user;

        if (!$user || $user->status !== 'active') {
            throw new AuthException('AUTH_TOKEN_INVALID', 'Token invalido o expirado.', 401);
        }

        Auth::login($user);

        return $user;
    }

    public function revoke(?string $token): void
    {
        if (!$token) {
            return;
        }

        ApiToken::where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $segments[] = $this->sign(implode('.', $segments));

        return implode('.', $segments);
    }

    private function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthException('AUTH_TOKEN_INVALID', 'Token invalido o expirado.', 401);
        }

        [$header, $payload, $signature] = $parts;
        $expected = $this->sign($header.'.'.$payload);

        if (!hash_equals($expected, $signature)) {
            throw new AuthException('AUTH_TOKEN_INVALID', 'Token invalido o expirado.', 401);
        }

        $data = json_decode($this->base64UrlDecode($payload), true);

        if (!is_array($data) || empty($data['sub']) || empty($data['jti']) || empty($data['exp']) || time() >= $data['exp']) {
            throw new AuthException('AUTH_TOKEN_INVALID', 'Token invalido o expirado.', 401);
        }

        return $data;
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->key(), true));
    }

    private function key(): string
    {
        $key = config('app.key');

        if (Str::startsWith($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
