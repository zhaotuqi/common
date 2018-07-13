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

    //报警的项目
    'warning_app_name' => [
        "fudao_callcenter"
    ],

    //报警的时长
    'warning_time'     => 3,
];