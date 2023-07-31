<?php

namespace Dx\Payroll\Models;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'options';

    protected $fillable = [
        'key', 'value', 'expire_time'
    ];

    /**
     * return value
     * @param string $key
     * @param string $value
     */
    public function setValue(string $key = '', string $value = '', string $expireTime = null)
    {
        return static::updateOrCreate([
            'key' => $key
        ], [
            'value' => $value,
            'expire_time' => $expireTime
        ]);
    }

    /**
     * return value
     * @param string $key
     */
    public function getByKey(string $key = '')
    {
        return static::where('key', trim($key))->pluck('value')->first();
    }

    /**
     * return with full column value
     * @param string $key
     */
    public function getValueProperties(string $key = '')
    {
        return static::where('key', trim($key))->first();
    }
}
