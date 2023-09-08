<?php

namespace Dx\Payroll\Models;

use Carbon\Carbon;
use DateTime;
use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoRecordValue extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'zoho_record_values';

    protected $fillable = [
        'record_id',
        'field_id',
        'row_id',
        'value',
        'date',
        'date_time',
    ];

    public function records()
    {
        return $this->belongsTo(ZohoRecordValue::class, 'id', 'record_id');
    }

    public function fields()
    {
        return $this->belongsTo(ZohoRecordField::class, 'id', 'field_id');
    }

    protected $casts = [
        'row_id' => 'string',
    ];

    public static function createOrUpdateZohoRecordValue($attributes, $zohoRecord, $zohoData, $rowId = 0)
    {
        $arrayKeys = array_keys($zohoData);

        foreach ($arrayKeys as $fieldLabel) {
            if (isset($attributes[$fieldLabel])) {
                $dateFormat = null;
                $dateTimeFormat = null;
                if(($attributes[$fieldLabel]['comp_type'] == 'Datetime' || $attributes[$fieldLabel]['comp_type'] == 'Date') && !empty($zohoData[$fieldLabel])) {
                    if (is_numeric($zohoData[$fieldLabel])) {
                        $dateTime = Carbon::createFromTimestamp(intval($zohoData[$fieldLabel] / 1000));
                    } else {
                        $dateTime = new DateTime($zohoData[$fieldLabel]);
                    }
                    $dateFormat = Carbon::parse($dateTime)->format('Y-m-d');
                    $dateTimeFormat = Carbon::parse($dateTime)->format('Y-m-d H:i:s');
                }


                ZohoRecordValue::updateOrCreate(
                    [
                        'record_id' => $zohoRecord->id,
                        'field_id' => $attributes[$fieldLabel]->id,
                        'row_id' => $rowId
                    ], [
                        'value' => $zohoData[$fieldLabel],
                        'date' => $dateFormat,
                        'date_time' => $dateTimeFormat,
                    ]
                );
            }
        }
    }
}
