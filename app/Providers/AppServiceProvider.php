<?php

namespace App\Providers;

use App\Services\Ai\AiSuggestionProviderInterface;
use App\Services\Ai\FakeAiSuggestionProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

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
    public function boot()
    {
        Schema::defaultStringLength(191);

        // La app mobile consume el reset por deep link (scheme "cccontrol"),
        // no por la vista web legacy de Laravel UI.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $scheme = config('app.mobile_reset_password_scheme', 'cccontrol://reset-password');

            return $scheme.'?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
