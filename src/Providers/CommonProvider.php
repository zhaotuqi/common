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
        $content = file_get_contents(base_path('.env'));
        if (false === strrpos($content, 'WARNING_EMAIL_URL')) {
            dd('不存在');
        }
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
