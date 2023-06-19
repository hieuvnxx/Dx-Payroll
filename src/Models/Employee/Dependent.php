<?php

namespace Dx\Payroll\Models\Employee;

use Dx\Payroll\DxServiceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dependent extends Model
{
    use HasFactory;

    protected $table = DxServiceProvider::DX_PREFIX_TABLE.'dependent_family';

    protected $fillable = [
        'code',
        'zoho_id',
        'name',
        'relation',
        'is_dependent',
        'do_birth',
        'from_date',
        'to_date',
    ];

    protected $hidden = [

    ];


}
