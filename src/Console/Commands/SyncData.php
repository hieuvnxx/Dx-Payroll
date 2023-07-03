<?php

namespace Dx\Payroll\Console\Commands;

use Dx\Payroll\Http\Controllers\SyncDataController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SyncData extends Command
{
    protected $syncData;
    protected $signature = 'dxpayroll:syncData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command Sync Data to DB';

    public function __construct(SyncDataController $syncData)
    {
        $this->syncData = $syncData;
        parent::__construct();
    }
    //dependency injecttion
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $formName = [
            'employee',
            'ot_request',
            'setting',
            'factor_master_data',
            'fomular',
            'form_master_data',
        ];

        $arrInput = new Request;
        foreach ($formName as $form) {
            $arrInput['form_name'] = $form;
            $this->syncData->processSyncData($arrInput);
        }
        return 0;
    }
}
