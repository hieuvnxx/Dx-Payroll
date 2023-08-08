<?php

use Illuminate\Support\Facades\Cache;

class Option
{
    private static $cacheOption;
    private static $redisOption;
    
    public function __construct()
    {
        static::$cacheOption = env('DX_CACHE_OPTION', false);
        static::$redisOption = env('DX_REDIS_OPTION', false);
    }

    public static function setValue($key, $value = '', $expireTime = null)
    {
        try {
            \Dx\Payroll\Models\Option::setValue($key, $value, $expireTime);
            if (static::$cacheOption) {
                Cache::add($key, $value, 100); // need to be refactor expiretime
            }
        } catch (Exception $e) {
            \Dx\Payroll\Models\Option::setValue($key, $value, $expireTime);
        }
    }

    public static function getByKey($key)
    {
        if (static::$cacheOption) {
            Cache::get($key);
        }
        \Dx\Payroll\Models\Option::getByKey($key);
    }
}