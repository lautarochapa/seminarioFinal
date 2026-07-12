<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Auth\AuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        // Intentionally same response regardless of whether email exists (no enumeration)
        Password::sendResetLink(['email' => $request->input('email')]);

        $traceId = $request->attributes->get('trace_id');

        return response()->json([
            'data'     => ['message' => 'Si el email está registrado, recibirás un enlace de recuperación.'],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $traceId = $request->attributes->get('trace_id');

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new AuthException(
                $status === Password::INVALID_TOKEN ? 'AUTH_RESET_TOKEN_INVALID' : 'AUTH_PASSWORD_RESET_FAILED',
                __($status),
                422
            );
        }

        return response()->json([
            'data'     => ['message' => 'Contraseña restablecida correctamente.'],
            'trace_id' => $traceId,
        ])->header('X-Trace-Id', $traceId);
    }
}
