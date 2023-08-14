<?php

namespace Dx\Payroll\Models;

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
        'section_id',
        'row_id',
        'value',
    ];

    public function records()
    {
        return $this->belongsTo(ZohoRecordValue::class, 'id', 'record_id');
    }

    public function fields()
    {
        return $this->belongsTo(ZohoRecordField::class, 'id', 'field_id');
    }

    public function sections()
    {
        return $this->belongsTo(ZohoSection::class, 'id', 'section_id');
    }

    public static function createOrUpdateZohoRecordValue($attributes, $zohoRecord, $zohoData, $rowId = 0)
    {
        $arrayKeys = array_keys($zohoData);

        foreach ($arrayKeys as $fieldLabel) {
            if (isset($attributes[$fieldLabel])) {
                if ($attributes[$fieldLabel]->type == "Lookup") {
                    $value = $zohoData[$fieldLabel.'.ID'] ?? $zohoData[$fieldLabel.'.id'] ?? $zohoData[$fieldLabel];
                } else {
                    $value = $zohoData[$fieldLabel];
                }

                ZohoRecordValue::updateOrCreate(
                    [
                        'record_id' => $zohoRecord->id,
                        'field_id' => $attributes[$fieldLabel]->id,
                        'row_id' => $rowId
                    ],[
                        'value' => $value,
                    ]
                );
            }
        }
    }
}
