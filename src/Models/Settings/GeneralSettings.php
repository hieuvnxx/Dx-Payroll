<?php

namespace Dx\Payroll\Models\Settings;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralSettings extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'general_settings';

    protected $fillable = [
        'zoho_id',
        'from',
        'to',
        'working_day',
        'haft_working_day',
        'deduction',
        'dependent',
    ];

    protected $hidden = [

    ];

    public function bonusSetting(){
        return $this->hasMany(BonusSettings::class,'zoho_id', 'zoho_id');
    }

    public function levelSetting(){
        return $this->hasMany(LevelSettings::class,'zoho_id', 'zoho_id');
    }

    public function overTimeSetting(){
        return $this->hasMany(OverTimeSettings::class,'zoho_id', 'zoho_id');
    }

    public function taxSetting(){
        return $this->hasMany(TaxSettings::class,'zoho_id', 'zoho_id');
    }

}
