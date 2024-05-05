<?php

namespace Lanser\LaravelApiGenerator;

use Illuminate\Support\ServiceProvider;
use Lanser\LaravelApiGenerator\OpenApi\Commands\GenerateOpenApiCommand;

class OpenApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/OpenApi/config/openapi-generator.php' => config_path('openapi-generator.php'),
        ], 'openapi-generator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOpenApiCommand::class,
            ]);
        }

        $this->loadRoutesFrom(__DIR__ . '/OpenApi/routes/routes.php');
        $this->loadViewsFrom(__DIR__ . '/OpenApi/resources/views', 'openapi-generator');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/OpenApi/config/openapi-generator.php',
            'openapi-generator'
        );
    }
}
