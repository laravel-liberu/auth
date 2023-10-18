<?php

namespace LaravelLiberu\Auth;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use LaravelLiberu\Core\Commands\AnnounceAppUpdate;
use LaravelLiberu\Core\Commands\ClearPreferences;
use LaravelLiberu\Core\Commands\ResetStorage;
use LaravelLiberu\Core\Commands\UpdateGlobalPreferences;
use LaravelLiberu\Core\Commands\Version;
use LaravelLiberu\Core\Services\Websockets;


class AppServiceProvider extends ServiceProvider
{
    public $singletons = [
        'websockets' => Websockets::class,
    ];

    public function boot()
    {
        JsonResource::withoutWrapping();

        $this->loadDependencies()
            ->publishDependencies()
            ->publishResources()
            ->commands(
                AnnounceAppUpdate::class,
                ClearPreferences::class,
                ResetStorage::class,
                UpdateGlobalPreferences::class,
                Version::class,
            );
    }

    private function loadDependencies()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inspiring.php', 'liberu.inspiring');

        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'liberu.config');

        $this->mergeConfigFrom(__DIR__.'/../config/auth.php', 'liberu.auth');

        $this->mergeConfigFrom(__DIR__.'/../config/state.php', 'liberu.state');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');


        return $this;
    }

    private function publishDependencies()
    {
        $this->publishes([
            __DIR__.'/../config' => config_path('liberu'),
        ], ['core-config', 'liberu-config']);

        $this->publishes([
            __DIR__.'/../resources/preferences.json' => resource_path('preferences.json'),
        ], ['core-preferences', 'liberu-preferences']);

        $this->publishes([
            __DIR__.'/../database/seeders' => database_path('seeders'),
        ], ['core-seeders', 'liberu-seeders']);

        return $this;
    }

    private function publishResources()
    {
        $this->publishes([
            __DIR__.'/../resources/images' => resource_path('images'),
        ], ['core-assets', 'liberu-assets']);

        $this->publishes([
            __DIR__.'/../resources/views/mail' => resource_path('views/vendor/mail'),
        ], ['core-email', 'liberu-email']);

        return $this;
    }

    
}
