<?php

namespace Dx\Payroll\Integrations;

use Dx\Payroll\Models\RefreshToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ZohoOauthToken
{
    private $accessToken = null;
    private $expireTime = null;
    private static $singletonObject;

    public static function getInstance(string $url = '')
    {
        if (self::$singletonObject == null) {
            self::$singletonObject = new static();
            self::$singletonObject->setAccessToken();
        }
        return self::$singletonObject;
    }

    /**
     *  set access token zoho people
     *
     * @return void
     */
    private function setAccessToken(): void
    {
        $this->checkExpireTime();
    }

    /**
     *  get access token zoho people
     *
     * @return mixed
     */
    public function getAccessToken(): mixed
    {
        return $this->accessToken;
    }

    /**
     *  get expire time access token zoho people
     *
     * @return mixed
     */
    public function getExpireTime(): mixed
    {
        return $this->expireTime;
    }

    /**
     * Refresh token expired and get all tokens
     * @return void
     */
    public function checkExpireTime(): void
    {
        $tokenConfig = RefreshToken::where('status', 1)->first();
        if (is_null($tokenConfig)) return;

        $diffSecond = 3500;
        $currentTime = date('Y-m-d H:i:s');

        if (isset($tokenConfig->last_time) && !is_null($tokenConfig->last_time)) {
            $diffSecond = strtotime($currentTime) - strtotime($tokenConfig->last_time);
        }

        if ($diffSecond >= 3500) {
            $body['refresh_token']  = $tokenConfig->refresh_token;
            $body['client_id']      = $tokenConfig->client_id;
            $body['client_secret']  = $tokenConfig->client_secret;
            $body['grant_type']     = $tokenConfig->grant_type;
            $response = $this->refreshToken($body);
            if (!empty($response) && isset($response['access_token'])) {
                $token = [
                    'zoho_token' => $response['access_token'],
                    'last_time'  => $currentTime
                ];
                $tokenConfig->update($token);

                $this->accessToken = $response['access_token'];
                $this->expireTime = $diffSecond;
            }
        } else {
            $this->accessToken = $tokenConfig->zoho_token;
            $this->expireTime = $diffSecond;
        }
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
            Log::channel('dx')->error($e->getMessage());
            return [];
        }
    }
}
