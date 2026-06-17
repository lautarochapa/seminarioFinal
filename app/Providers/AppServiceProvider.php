<?php

namespace App\Providers;

use App\Services\Ai\AiSuggestionProviderInterface;
use App\Services\Ai\FakeAiSuggestionProvider;
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
    }
}
