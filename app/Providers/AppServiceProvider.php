<?php

namespace App\Providers;

use App\Mail\Transport\ResendTransport;
use GuzzleHttp\Client;
use App\Services\Ai\AiSuggestionProviderInterface;
use App\Services\Ai\FakeAiSuggestionProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AiSuggestionProviderInterface::class, FakeAiSuggestionProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(UrlGenerator $url)
    {
        Schema::defaultStringLength(191);

        Mail::extend('resend', function (array $config) {
            return new ResendTransport(new Client, (string) ($config['key'] ?? ''), $config['test_recipient'] ?? null);
        });

        if (app()->environment('production')) {
            $url->forceScheme('https');
        }

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $resetUrl = \App\Services\TransactionalMailService::url('/password/reset/'.rawurlencode($token), [
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Restablecer contraseña - CocinaComidaControl')
                ->view(['mail.transactional', 'mail.transactional-text'], [
                    'title' => 'Restablecer tu contraseña',
                    'paragraphs' => [
                        'Recibimos una solicitud para restablecer la contraseña de tu cuenta.',
                        'Este enlace vence en '.config('auth.passwords.users.expire').' minutos y se puede usar una sola vez.',
                        'Si no lo solicitaste, ignorá este correo. Tu contraseña no cambiará.',
                    ],
                    'actionLabel' => 'Restablecer contraseña',
                    'actionUrl' => $resetUrl,
                ]);
        });
    }
}
