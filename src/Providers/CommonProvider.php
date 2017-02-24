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
        if (false == strrpos($content, 'WARNING_EMAIL_URL')) {
            if (App()->environment() == 'production') {
                file_put_contents(base_path('.env'), 'WARNING_EMAIL_URL=http://10.10.146.223/mail/sendWarning', FILE_APPEND);
            } else {
                file_put_contents(base_path('.env'), 'WARNING_EMAIL_URL=http://10.1.1.100:8299/mail/sendWarning', FILE_APPEND);
            }
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
