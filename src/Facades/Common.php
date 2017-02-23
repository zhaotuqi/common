<?php
/**
 * Created by PhpStorm.
 * User: jesse
 * Date: 16/5/20
 * Time: 00:21
 */

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Common extends Facade
{
    protected static function getFacadeAccessor() { return 'Common'; }
}