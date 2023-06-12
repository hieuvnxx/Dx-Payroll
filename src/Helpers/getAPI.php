<?php

namespace Dx\Payroll\Helpers;

use Dx\Payroll\Helpers\ZohoToken;

class getAPI
{
    /**
     *  Zoho API
     *
     * @return array
     */
    static public function getRecordByID($id = '', $form = '')
    {
        $body = [];
        if($id){
            $body['recordId'] = $id;
        }
        return ZohoToken::callZoho($form, $body, true);
    }

    /**
     *  Zoho API
     *
     * @return array
     */
    static public function getSectionForm(string $form = '', $version = 2, bool $convert = true): array
    {
        $body = [];
        if ($version) {
            $body['version'] = 2;
        }
        $result = ZohoToken::callZoho($form,  $body, $convert);
        return $result;
    }

}
