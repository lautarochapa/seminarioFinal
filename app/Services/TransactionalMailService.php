<?php

namespace App\Services;

use App\FamilyGroupInvitation;
use App\Notifications\FamilyInvitationMail;
use App\Notifications\WelcomeMail;
use App\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifications;

class TransactionalMailService
{
    public static function url(string $path, array $query = []): string
    {
        // Mail links must use the configured origin, never an untrusted Host header.
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/')
            .($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public function welcome(User $user): string
    {
        return $this->send($user, new WelcomeMail, 'welcome');
    }

    public function invitation(FamilyGroupInvitation $invitation): string
    {
        return $this->send(Notifications::route('mail', $invitation->invited_email),
            new FamilyInvitationMail($invitation), 'family_invitation');
    }

    public function send($recipient, Notification $notification, string $kind): string
    {
        if (! config('mail.transactional_enabled')) {
            return 'disabled';
        }
        $mailer = config('mail.default');
        if (in_array($mailer, ['log', 'array'], true) && ! app()->environment('testing')) {
            return 'disabled';
        }
        if ($mailer === 'resend' && ($allowed = config('mail.mailers.resend.test_recipient'))) {
            $email = $recipient->routeNotificationFor('mail', $notification);
            if (! is_string($email) || strtolower($email) !== strtolower(trim($allowed))) {
                return 'restricted';
            }
        }
        try {
            $recipient->notify($notification);
            return 'accepted';
        } catch (\Throwable $exception) {
            // Provider exceptions can contain credentials or password-reset tokens.
            Log::warning('transactional_mail_failed', ['kind' => $kind]);
            return 'failed';
        }
    }
}
