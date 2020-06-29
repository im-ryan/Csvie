<?php

namespace Rhuett\Csvie;

use Illuminate\Support\ServiceProvider;

/**
 * Class CsvieServiceProvider.
 *
 * The provider class for Csvie. This class registers the package with Laravel, along with any services, commands, publishable files, etc.
 */
class CsvieServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/csvie.php', 'csvie');

        // Register the service the package provides.
        $this->app->singleton('csvie', function ($app) {
            return new Csvie;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['csvie'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/csvie.php' => config_path('csvie.php'),
        ], 'csvie.config');

        // Registering package commands.
        $this->commands([
            Commands\CleanerMakeCommand::class,
        ]);
    }
}
