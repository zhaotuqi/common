<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Libraries\Common;

class CommonProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([__DIR__ . '/../common_config.php' => config_path('common_config.php')]);
        file_put_contents(base_path('.env'), 'test');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
        $this->app->bind('Common', function () {
            return new Common();
        });
    }
}
