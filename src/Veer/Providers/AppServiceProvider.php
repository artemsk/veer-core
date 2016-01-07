<?php

namespace Veer\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../../resources/' => base_path('resources/views'),
        ], 'veer');

        $this->publishes([
            __DIR__.'/../../../assets/' => public_path('assets'),
        ], 'veer-assets');

        $this->publishes([
            __DIR__.'/../../../config/veer.php' => config_path('veer.php')
        ], 'veer-config');

        $this->publishes([
            __DIR__.'/../../../database/' => database_path('migrations')
        ], 'veer-db');

        $this->publishes([
            __DIR__.'/../../../lang/' => base_path('resources/lang'),
        ], 'veer-lang');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/veer.php', 'veer'
        );
        
        \Blade::setRawTags('{{', '}}');
        \Blade::setContentTags('{{{', '}}}');
        \Blade::setEscapedContentTags('{{{', '}}}');
    }
}
