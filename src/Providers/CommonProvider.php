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