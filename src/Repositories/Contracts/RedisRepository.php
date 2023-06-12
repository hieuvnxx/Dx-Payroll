<?php

namespace Dx\Payroll\Repositories\Contracts;


use Illuminate\Support\Facades\Redis;

/**
 * @mixin \Redis
 */
abstract class RedisRepository
{
    public $redisConnection;

    public function __construct()
    {
        $this->makeRedisConnection();
    }

    public function makeRedisConnection()
    {
        return $this->redisConnection = Redis::connection();
    }
}
