<?php

namespace Dx\Payroll\Helpers;

use Dx\Payroll\Models\RefreshToken;
use Dx\Payroll\Repositories\RefreshTokenInterface;
use Dx\Payroll\Repositories\ZohoFormInterface;
use GuzzleHttp\Exception\GuzzleException;
use Dx\Payroll\Http\Controllers\BaseController;

class ZohoToken
{
    static public function callZoho(string $action = '', array $parameter = [], bool $convert = true, string $method = 'POST'): array
    {
        /**
         *  call api to zoho people
         *
         * @return array
         */
        try {
            $token = ZohoToken::randomToken();
            if (empty($token)) {
                Log::channel('dx')->info("Token empty");
                return [];
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
            $url = 'https://people.zoho.com/api/'.$action;
            $response = (new \GuzzleHttp\Client())->request($method, $url, $body);
            $data = json_decode($response->getBody(), true);
            if ($convert) {
                $result = ZohoToken::convertZohoBody($data, $action);
            } else {
                $result = $data;
            }
            return $result;
        } catch (\Exception | GuzzleException $e) {
            $arrEmployee = app(BaseController::class)->sendError("Call zoho error",  $e->getMessage(), 404);
            return [];
        }
    }

    /**
     *  Get zoho token from cache file or create a new one and random one
     *
     * @return array
     */
    static public function randomToken(){
        $result = '';
        $path_dir = storage_path('framework/cache/zoho_token.config');
        if (!file_exists($path_dir)) {
            file_put_contents($path_dir, serialize([]));
            chmod($path_dir, 0777);
        }
        $contents = file_get_contents($path_dir, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $item = unserialize($contents);
        if(!empty($item)){
            $updateIndex = array_rand($item);
            $randomToken = $item[$updateIndex];
            if(isset($randomToken['last_time']) && $randomToken['last_time'] != '' &&
                strtotime(date("d-m-Y H:i:s")) - strtotime($randomToken['last_time']) >= 1){
                    $response = ZohoToken::getListRefreshToken($randomToken['refresh_token']);
                    $item[$updateIndex] = $response[0];
            }
            $result = $item[$updateIndex];
        }else {
            $item = ZohoToken::getListRefreshToken();
            if(sizeof($item) > 0){
                $result = $item[array_rand($item)];
            }
        }
        file_put_contents($path_dir, serialize($item));
        if(isset($result['zoho_token'])){
            $result = $result['zoho_token'];
        }
        return $result;
    }

    /**
     * Refresh token expired and get all tokens
     * @return array
     */
    static public function getListRefreshToken($refreshToken = ''){
        $result = [];
        if(!empty($refreshToken)){
            $items = app(RefreshTokenInterface::class)->findByField('refresh_token', $refreshToken);
        }else{
            $items = app(RefreshTokenInterface::class)->all();
        }
        if($items->count() > 0) {
            $i = 0;
            foreach ($items as $item) {
                $diffSecond = 3500;
                if (isset($item->last_time) && !is_null($item->last_time)) {
                    $diffSecond = strtotime(date("d-m-Y H:i:s")) - strtotime($item->last_time);
                }
                if($diffSecond >= 3500) {
                    $body['refresh_token']  = $item->refresh_token;
                    $body['client_id']      = $item->client_id;
                    $body['client_secret']  = $item->client_secret;
                    $body['grant_type']     = $item->grant_type;
                    $response = ZohoToken::refreshToken($body);
                    if (!empty($response) && isset($response['access_token'])) {
                        $token = [
                            'zoho_token' => $response['access_token'],
                            'last_time'  => date('Y-m-d H:i:s')
                        ];
                        $item->update($token);
                        $res = ['refresh_token' => $body['refresh_token'],
                            'zoho_token' => $response['access_token'],
                            'last_time' => $response['last_time']];
                    }
                }else{
                    $res = ['refresh_token' => $item->refresh_token,
                                'zoho_token' => $item->zoho_token,
                                'last_time' => $item->last_time];
                }
                $result[$i] = $res;
                $i++;
            }
        }
        return $result;
    }

    /**
     * Get new access token
     */
    static public function refreshToken($data = [])
    {
        try {
            $url  = 'https://accounts.zoho.com/oauth/v2/token';
            $url .= '?refresh_token=' . $data['refresh_token'] . '&client_id=' . $data['client_id'] . '&client_secret=' . $data['client_secret'] . '&grant_type=' . $data['grant_type'];
            $response = (new \GuzzleHttp\Client())->request("POST", $url, $data);
            return json_decode($response->getBody(), true);
        } catch (\Exception | GuzzleException $e) {
            Log::channel('dx')->info($e);
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
    static protected function convertZohoBody(array $body, string $type = ''): mixed
    {
        $response = [];
        if (empty($body)) {
            return [];
        }
        if (isset($body['response']['status']) && $body['response']['status'] == 0) {
            if (strpos($type, 'getRecordByID')) {
                $result = $body['response']['result'][0] ?? [];
                if (!empty($result)) {
                    foreach ($result as $r => $item) {
                        if ($r === 'tabularSections') {
                            $response['tabularSections'] = $item;
                        } else {
                            if (is_array($item)) {
                                if (!empty($item)) {
                                    foreach ($item as $field => $val) {
                                        $response[$field] = $val;
                                    }
                                }
                            } else {
                                $response[$r] = $item;
                            }
                        }
                    }
                }
            } elseif (strpos($type, 'getDataByID')) {
                $response = $body['response']['result'][0] ?? [];
            } elseif (strpos($type, 'components')) {
                foreach ($body['response']['result'] as $value) {
                    if (isset($value['tabularSections'])) {
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
                }
            } else {
                if (!empty($body['response']['result'])) {
                    if (isset($body['response']['result']['pkId'])) {
                        return $body['response'];
                    } else {
                        foreach ($body['response']['result'] as $data) {
                            if ((!empty($data))) {
                                foreach ($data as $key => $item) {
                                    if (isset($item[0])) {
                                        $response[] = $item[0];
                                    } else {
                                        $response[] = $item;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } elseif (isset($body['response']['status']) && $body['response']['status'] == 1) {
            $response = $body['response'];
        } else {
            if (strpos($type, 'getRegularizationRecords')) {
                $response = $body['result'];
            } else {
                $response = $body;
            }
        }
        return $response;
    }

}
