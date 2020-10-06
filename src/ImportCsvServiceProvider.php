<?php

namespace Dipenparmar12\ImportCsv;

use Dipenparmar12\ImportCsv\Commands\LargeCsvLoad;
use Illuminate\Support\ServiceProvider;

class ImportCsvServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'dipenparmar12');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'dipenparmar12');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

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
        $this->mergeConfigFrom(__DIR__.'/../config/importcsv.php', 'importcsv');

        // Register the service the package provides.
        $this->app->singleton('importcsv', function ($app) {
            return new ImportCsv;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['importcsv'];
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
            __DIR__.'/../config/importcsv.php' => config_path('importcsv.php'),
        ], 'importcsv.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/dipenparmar12'),
        ], 'importcsv.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/dipenparmar12'),
        ], 'importcsv.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/dipenparmar12'),
        ], 'importcsv.views');*/

        // Registering package commands.
         $this->commands([
             LargeCsvLoad::class,
         ]);
    }
}
