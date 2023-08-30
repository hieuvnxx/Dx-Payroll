<?php

namespace Dx\Payroll\Console\Commands;

use Dx\Payroll\Jobs\MasterData\ProcessSyncFormSectionFieldToZoho;
use Dx\Payroll\Repositories\ZohoFormInterface;
use Illuminate\Console\Command;

class MigrateMasterDataFormSectionFieldZoho extends Command
{
    protected $signature = 'dxpayroll:migrateMasterDataFormSectionFieldZoho';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'push local master data forms, sections, fields to master data zoho service';

    protected $zohoLib;
    protected $zohoForm;
    protected $zohoRecord;

    public function __construct(ZohoFormInterface $zohoForm)
    {
        parent::__construct();
        
        $this->zohoForm = $zohoForm;
    }
    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info(now()->toDateTimeString() . " Start: dxpayroll:migrateMasterDataFormSectionFieldZoho");

        $zohoForms = $this->zohoForm->with(['sections', 'attributes', 'sections.attributes'])->get();
        if ($zohoForms->isEmpty()) {
            throw new \ErrorException('Empty Zoho Form in database');
        }

        $i = 0;
        //delay for IP block API from zoho
        foreach ($zohoForms as $form) {
            $this->info(now()->toDateTimeString() . " Form in queue: " . $form->form_link_name);
            ProcessSyncFormSectionFieldToZoho::dispatch($form)
                                                ->delay(now()->addSeconds($i));
            
            $i += 5; //queue plus 5s delay
        }

        $this->info(now()->toDateTimeString() . " Complete: dxpayroll:migrateMasterDataFormSectionFieldZoho");
    }
}
