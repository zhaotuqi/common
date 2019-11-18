<?php
/**
 * Created by PhpStorm.
 * User: jesse
 * Date: 17/2/23
 * Time: 23:06
 */
return [
    'warning_email_url' => env('WARNING_EMAIL_URL'),
    // 完全无日志记录
    'no_log_routes' => [],
    // 有请求记录但无body
    'no_log_body_routes' => [],
    // 注册Java服务接口地址
    'java_api_url' => [
        'testing'       => env('TESTING_REGISTER_DOCKER_URL', 'http://192.168.10.43:8090'),
        'staging'       => env('STAGING_REGISTER_DOCKER_URL', ''),
        'production'    => env('PRODUCTION_REGISTER_DOCKER_URL', ''),
    ]
];