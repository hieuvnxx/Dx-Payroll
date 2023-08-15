<?php

namespace Dx\Payroll\Integrations;

use Dx\Payroll\Integrations\ZohoOauthToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Env;

class ZohoPeopleIntegration
{
    private $peopleUrl;
    private $oauthLib;
    private static $singletonObject;

    /**
     *  set URL zoho people
     *
     * @param string $url
     * @return ZohoPeopleIntegration
     */
    public static function getInstance(string $url = null): ZohoPeopleIntegration
    {
        $url = $url ?? 'https://people.zoho.com/api/';
        if (self::$singletonObject == null) {
            self::$singletonObject = new static();
            self::$singletonObject->setPeopleUrl($url);
            self::$singletonObject->setOauthLib();
        }
        return self::$singletonObject;
    }

    /**
     *  set URL zoho people
     *
     * @param $peopleUrl
     * @return void
     */
    private function setPeopleUrl($peopleUrl): void
    {
        $this->peopleUrl = $peopleUrl;
    }

    /**
     *  get URL zoho people
     *
     * @return mixed
     */
    public function getPeopleUrl(): mixed
    {
        return $this->peopleUrl;
    }

    /**
     *  set lib to generate token
     */
    private function setOauthLib(): void
    {
        $this->oauthLib = ZohoOauthToken::getInstance();
    }

    /**
     *  get lib to generate token
     *
     * @return mixed
     */
    private function getOauthLib()
    {
        return $this->oauthLib;
    }

    /**
     *  get config headers request
     *
     * @return array
     */
    private function requestHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getOauthLib()->getAccessToken(),
            'Accept' => 'application/json',
        ];
    }

    /**
     *  template http send request
     *
     * @param string $action
     * @param array $parameter
     * @param bool $convert
     * @param string $method
     * @return array
     */
    public function callZoho(string $action = '', array $parameter = [], bool $convert = true, string $method = 'POST'): array
    {
        $url = $this->getPeopleUrl() . $action;

        $typeParam = (strtolower($method) == 'get') ? 'query' : 'form_params';

        $body['headers']  = $this->requestHeaders();
        $body[$typeParam] = $parameter;

        $response = (new Client())->request($method, $url, $body);

        $data = json_decode($response->getBody(), true);
        if ($convert) {
            $result = $this->convertZohoBody($data, $action);
        } else {
            $result = $data;
        }

        return $result;
    }

    /**
     * Reformat zoho response
     *
     * @param array $body
     * @param string $type
     * @return mixed
     */
    protected function convertZohoBody(array $body, string $type = ''): mixed
    {
        if(empty($body)){
            return [];
        }
        $response = [];
        if(isset($body['response']['status']) && $body['response']['status'] == 0){
            if(strpos($type, 'getRecordByID') == true && isset($body['response']['result'][0]) && !empty($body['response']['result'][0])){
                foreach ($body['response']['result'][0] as $r => $item){
                    if($r === 'tabularSections'){
                        $response['tabularSections'] = $item;
                    }else{
                        if(is_array($item)){
                            if(!empty($item)){
                                foreach ($item as $field => $val){
                                    $response[$field] = $val;
                                }
                            }
                        }else{
                            $response[$r] = $item;
                        }
                    }
                }
            }elseif(strpos($type, 'getDataByID') == true){
                $response = isset($body['response']['result'][0])?$body['response']['result'][0]:[];
            }elseif (strpos($type, 'components') == true){
                foreach ($body['response']['result'] as $value) {
                    if (!isset($value['tabularSections'])) continue;
                    foreach ($value['tabularSections'] as $key => $item) {
                        foreach ($item as $keyI => $valueI) {
                            if ($keyI !== "sectionId") {
                                $words = $keyI;
                                foreach ($valueI as $last_value) {
                                    foreach ($last_value as $key_last => $value_last) {
                                        $response[$words]['sectionId'] = $item['sectionId'];
                                        if ($key_last == 'comptype' && $value_last == 'Picklist') {
                                            $response[$words] = $last_value['Options'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }else{
                if(!empty($body['response']['result'])){
                    if(isset($body['response']['result']['pkId'])){
                        return $body['response'];
                    }else{
                        foreach ($body['response']['result'] as $data){
                            if((!empty($data))){
                                foreach ($data as $key => $item){
                                    if(isset($item[0])){
                                        $response[] = $item[0];
                                    }else{
                                        $response[] = $item;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }elseif(isset($body['response']['status']) && $body['response']['status'] == 1){
            $response = $body['response'];
        }else{
            $response = $body;
        }
        return $response;
    }
    /**
     *  Zoho API
     *
     * @return array
     */
    public function getRecordByID($formLinkName, $id)
    {
        $body = [];
        if ($id) $body['recordId'] = $id;

        return $this->callZoho(zoho_people_get_record_by_id_path($formLinkName), $body, true);
    }

    public function getRecords(string $formLinkName, int $index = 0, int $limit = 200, array $searchParams = [])
    {
        $params['sIndex'] = $index;
        $params['limit'] = $limit;
        $searchString = '';

        if (!empty($searchParams)) {
            foreach ($searchParams as $param) {
                $searchCriteria = $param['searchCriteria'] ?? 'AND';
                if (!empty($searchString)) {
                    $searchString .= " | {searchField: " . "'" . $param['searchField'] . "'" . ", searchOperator: " . "'" . $param['searchOperator'] . "'" . ", searchCriteria: " . "'" . $searchCriteria . "'" . ", searchText : " . "'" . $param['searchText'] . "'" . "}";
                } else {
                    $searchString .= "{searchField: " . "'" . $param['searchField'] . "'" . ", searchOperator: " . "'" . $param['searchOperator'] . "'" . ", searchCriteria: " . "'" . $searchCriteria . "'" . ", searchText : " . "'" . $param['searchText'] . "'" . "}";

                }
            }
        }

        if (!empty($searchString)) {
            $params["searchParams"] = $searchString;
        }

        return $this->callZoho(zoho_people_get_records_path($formLinkName), $params);
    }

    public function deleteRecords($form, $ids)
    {
        $body['recordIds'] = $ids;
        $body['formLinkName'] = $form;

        return $this->callZoho(zoho_people_delete_records_path(), $body);
    }

    /**
     *  Lấy danh sách zoho form
     * @return array
     */
    public function getSectionForm(string $formLinkName, $version = 2, bool $convert = true): array
    {
        $body = [];

        if ($version) $body['version'] = 2;

        return $this->callZoho(zoho_people_form_components_path($formLinkName),  $body, $convert);
    }

    /**
     * Lấy attendance trong kỳ lương
     * @return array
     */
    public function getAttendanceByEmployee($empCode = '', $startDate = '', $endDate = '')
    {
        $bodyAttendance = [
            'sdate' => date('d-m-Y', strtotime($startDate)),
            'edate' => date('d-m-Y', strtotime($endDate)),
            'empId' => $empCode,
            'dateFormat' => 'dd-MM-yyyy'
        ];

        return $this->callZoho(zoho_people_get_attendance_by_user_path(), $bodyAttendance, false);
    }

    /*
     * Thông tin các ngày cuối tuần, lễ ...
     */
    public function getShiftConfigurationByEmployee($empCode = '', $startDate = '', $endDate = '', $dataPunch = [])
    {
        $bodyShift = [
            'sdate' => $startDate,
            'edate' => $endDate,
            'empId' => $empCode,
        ];

        $response = [];

        $result = $this->callZoho(zoho_people_get_shift_configuration_path(), $bodyShift, true);
        if(!empty($result['userShiftDetails']['shiftList'])){
            foreach ($result['userShiftDetails']['shiftList'] as $item) {
                if (!empty($dataPunch)) {
                    foreach ($dataPunch as $date => $punch){
                        $dateItem = date('Y-m-d', strtotime($item['date']));
                        if(strtotime($item['date']) == strtotime($date)){
                            $response[$dateItem] = array_merge($punch, $item);
                        }
                    }
                }
            }
        }

        return $response;
    }

    /*
     * Get leave details of an employee
     */
    public function searchLeaveWorking($employeeId = '', $fromDate = '', $toDate = '', $convert = true)
    {
        $body       = [];
        $fromDate   = date('Y-m-d', strtotime($fromDate));
        $toDate     = date('Y-m-d', strtotime($toDate));
        $body['searchParams'] = "{searchField: 'From', searchOperator: 'Before', searchCriteria: 'AND', searchText : " . "'" . $toDate . "'" . "} | {searchField: 'To', searchOperator: 'After', searchCriteria: 'AND', searchText : " . "'" . $fromDate . "'" . "} | {searchField: 'Employee_ID', searchOperator: 'Like', searchText : " . "'" . $employeeId . "'" . "} ";
        
        return $this->callZoho(zoho_people_get_records_path('leave'), $body, $convert);
    }

    public function insertRecord($formLinkName, $inputData = [], $formatDate = '')
    {
        $body = [];

        if (!empty($inputData)) $body['inputData'] = json_encode($inputData, JSON_UNESCAPED_UNICODE);

        if ($formatDate) $body['dateFormat'] = $formatDate;

        return $this->callZoho(zoho_people_insert_record_json_path($formLinkName), $body, true);
    }

    public function updateRecord($formLinkName, $inputData = [], $tabularData = [], $zohoId = '',  $formatDate = '')
    {
        $body = [];
        if ($zohoId) $body['recordId'] = $zohoId;

        if (!empty($inputData)) $body['inputData'] = json_encode($inputData, JSON_UNESCAPED_UNICODE);

        if (!empty($tabularData)) $body['tabularData'] = json_encode($tabularData, JSON_UNESCAPED_UNICODE);

        if ($formatDate) $body['dateFormat'] = $formatDate;

        return $this->callZoho(zoho_people_update_record_json_path($formLinkName), $body, true);
    }
}
