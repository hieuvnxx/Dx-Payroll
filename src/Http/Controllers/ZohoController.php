<?php
namespace Dx\Payroll\Http\Controllers;

use Dx\Payroll\Http\Controllers\BaseController;
use Dx\Payroll\Repositories\Eloquent\RedisConfigFormRepository;
use Dx\Payroll\Repositories\RefreshTokenInterface;
use Dx\Payroll\Integrations\ZohoIntegration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;


class ZohoController extends BaseController
{
    public $peopleUrl, $redisControl, $repoRefreshToken;
    public function __construct(RedisConfigFormRepository $redisControl, RefreshTokenInterface $repoRefreshToken)
    {
        $this->redisControl = $redisControl;
        $this->repoRefreshToken = $repoRefreshToken;
        $this->peopleUrl = ZohoIntegration::getInstance();
        $this->peopleUrl->setPeopleUrl('https://people.zoho.com/api/');
    }

    public function callZoho(string $action = '', array $parameter = [], bool $convert = true, string $method = 'POST')
    {
        /**
         *  call api to zoho people
         *
         * @return array
         */
        $listToken = $this->getToken();
        if (empty($listToken)) {
            return $this->sendError('Token empty');
        }else{
            $token = $listToken[0]['zoho_token'];
        }
        $body['headers'] = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        if (strtolower($method) == 'get') {
            $typeParam = 'query';
        } else {
            $typeParam = 'form_params';
        }
        $arrParam = [];
        if (!empty($parameter)) {
            foreach ($parameter as $key => $value) {
                $arrParam[$key] = $value;
            }
        }
        $body[$typeParam] = $arrParam;
        $url = $this->peopleUrl->getPeopleUrl() . $action;
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
     * Refresh token expired and get all tokens
     * @return array
     */
    public function getToken()
    {
        $result = [];
        $items = $this->repoRefreshToken->findByField('status', 1);
        if (!empty($items)) {
            foreach ($items as $item) {
                $diffSecond = 3500;
                $currentTime = date('Y-m-d H:i:s');
                if (isset($item->last_time) && !is_null($item->last_time)) {
                    $diffSecond = strtotime($currentTime) - strtotime($item->last_time);
                }
                if ($diffSecond >= 3500) {
                    $body['refresh_token']  = $item->refresh_token;
                    $body['client_id']      = $item->client_id;
                    $body['client_secret']  = $item->client_secret;
                    $body['grant_type']     = $item->grant_type;
                    $response = $this->refreshToken($body);
                    if (!empty($response) && isset($response['access_token'])) {
                        $token = [
                            'zoho_token' => $response['access_token'],
                            'last_time'  => $currentTime
                        ];
                        $item->update($token);
                        $result[] = ['zoho_token' => $response['access_token'], 'last_time' => $currentTime];
                    }
                } else {
                    $result[] = ['zoho_token' => $item->zoho_token, 'last_time' => $item->last_time];
                }
            }
        }
        return $result;
    }

    /**
     * Get new access token
     */
    public function refreshToken($data = [])
    {
        try {
            $url  = 'https://accounts.zoho.com/oauth/v2/token';
            $url .= '?refresh_token=' . $data['refresh_token'] . '&client_id=' . $data['client_id'] . '&client_secret=' . $data['client_secret'] . '&grant_type=' . $data['grant_type'];
            $response = (new \GuzzleHttp\Client())->request("POST", $url, $data);
            return json_decode($response->getBody(), true);
        } catch (\Exception | GuzzleException $e) {
            Log::channel('dx')->info($e->getMessage());
            return [];
        }
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
    public function getRecordByID($id = '', $form = '')
    {
        $body = [];
        if($id){
            $body['recordId'] = $id;
        }
        return $this->callZoho($form, $body, true);
    }

    public function getRecords($form = '', $body = [], $convert = true)
    {
        $response = $this->callZoho($form, $body, $convert);
        return $response;
    }

    public function deleteRecords($form = '', $body = [], $convert = true)
    {
        $response = $this->callZoho($form, $body, $convert);
        return $response;
    }
    /**
     *  Lấy danh sách zoho form
     * @return array
     */
    public function getSectionForm(string $form = '', $version = 2, bool $convert = true): array
    {
        $body = [];
//        if ($version) {
//            $body['version'] = 2;
//        }
        return $this->callZoho($form,  $body, $convert);
    }

    /**
     * Lấy attendance trong kỳ lương
     * @return array
     */
    public function getAttendanceByEmployee($form = '', $empCode = '', $startDate = '', $endDate = '')
    {
        $bodyAttendance = [
            'sdate' => date('d-m-Y', strtotime($startDate)),
            'edate' => date('d-m-Y', strtotime($endDate)),
            'empId' => $empCode,
            'dateFormat' => 'dd-MM-yyyy'
        ];
        return $this->callZoho($form, $bodyAttendance, false);
    }

    /*
     * Get Shift configuration details of an employee
     */
    public function getShiftConfigurationByEmployee($form = '', $empCode = '', $startDate = '', $endDate = '')
    {
        $response = ['data' => []];
        //Thông tin các ngày cuối tuần, lễ ...
        $bodyShift = [
            'sdate' => $startDate,
            'edate' => $endDate,
            'empId' => $empCode,
        ];
        $result = $this->callZoho($form, $bodyShift, true);
        $standardWorkingDay = 0;
        if(!empty($result['userShiftDetails']['shiftList'])){
            foreach ($result['userShiftDetails']['shiftList'] as $item){
                if(!isset($item['isWeekend'])){
                    $dateItem = date('Y-m-d', strtotime($item['date']));
                    $response['data'][$dateItem] = $item;
                    if(date('w', strtotime($dateItem)) != 6 && date('w', strtotime($dateItem)) != 0){
                        $standardWorkingDay ++;
                    }
                }
            }
        }
        $response['standard_working_day'] = $standardWorkingDay;
        return $response;
    }

    /*
     * Get leave details of an employee
     */
    public function searchLeaveWorking($form, $employeeId = '', $fromDate = '', $toDate = '', $convert = true)
    {
        $body       = [];
        $fromDate   = date('Y-m-d', strtotime($fromDate));
        $toDate     = date('Y-m-d', strtotime($toDate));

        $body['searchParams'] = "{searchField: 'From', searchOperator: 'Before', searchCriteria: 'AND', searchText : " . "'" . $toDate . "'" . "} | {searchField: 'To', searchOperator: 'After', searchCriteria: 'AND', searchText : " . "'" . $fromDate . "'" . "} | {searchField: 'Employee_ID', searchOperator: 'Like', searchText : " . "'" . $employeeId . "'" . "} ";
        return $this->callZoho($form, $body, $convert);
    }

    public function searchMonthlyWorking($form, $empCode = '', $monthly = '')
    {
        $body['searchParams'] = "{searchField: 'employee', searchOperator: 'Contains', searchText : " . "'" . $empCode . "'" . "} | {searchField: 'month', searchOperator: 'Is', searchText : " . "'" . $monthly . "'" . "}";
        return $this->callZoho($form, $body, true);
    }

    public function getOvertimeByEmployee($form, $empCode = '', $startDate = '', $endDate = '')
    {
        $body["searchParams"] = "{searchField: 'Emp_info', searchOperator: 'Is', searchText : '" . $empCode . "'} | {searchField: 'Date', searchOperator: 'Between', searchText : '" . $startDate . ";" . $endDate . "'}";
        return $this->callZoho($form, $body);
    }
}
