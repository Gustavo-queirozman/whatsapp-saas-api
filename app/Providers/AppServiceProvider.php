<?php

namespace App\Providers;

use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\FakeAiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Support\CurrentCompany;
use InvalidArgumentException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class, fn (): CurrentCompany => new CurrentCompany());

        $this->app->bind(AiProviderInterface::class, function ($app): AiProviderInterface {
            return match ((string) config('ai.default', 'openai')) {
                'fake' => $app->make(FakeAiProvider::class),
                'openai' => $app->make(OpenAiProvider::class),
                default => throw new InvalidArgumentException('Provider de IA nao suportado.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
