<?php

namespace App\Providers;

use App\Libraries\JavaConf;
use App\Libraries\RabbitMq;
use App\Libraries\WenBaRedis;
use Illuminate\Support\ServiceProvider;
use App\Libraries\Common;
use App\Libraries\SettlementPlatformIdGernerator;

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
            if (App()->environment() == 'production' || App()->environment() == 'pro') {
                file_put_contents(base_path('.env'), PHP_EOL . 'WARNING_EMAIL_URL=http://10.10.146.223/mail/sendWarning' . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents(base_path('.env'), PHP_EOL . 'WARNING_EMAIL_URL=http://10.2.1.100:8080/mail/sendWarning' . PHP_EOL, FILE_APPEND);
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
        //注册wredis 作为redis的替换方式，在每个应用中将 app('wredis') 替换 app('redis')即可
        $this->app->bind('wredis', function () {
            return new WenBaRedis();
        });

        /**
         * 注册消息队列服务
         */
        $this->app->singleton('amq',function(){
           return new RabbitMq();
        });

        /**
         * 结算平台ID生成器
         */
        $this->app->bind('SettlementPlatformIdGernerator', SettlementPlatformIdGernerator::class);

        /**
         * Java配置中心
         */
        $this->app->singleton('JavaConf',function(){
            return new JavaConf();
        });
    }


}
