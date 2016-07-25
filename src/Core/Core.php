<?php

namespace OpenOauth\Core;

use OpenOauth\Core\CacheDriver\FileDriver as CacheFileDriver;
use OpenOauth\Core\DatabaseDriver\FileDriver as DatabaseFileDriver;
use OpenOauth\Core\Config;
use OpenOauth\Core\Exceptions\ConfigMistakeException;
use OpenOauth\Core\Http\Http;
use stdClass;

class Core
{
    /** @var  object */
    public           $configs;
    protected static $error;
    protected static $cacheDriver;
    protected static $databaseDriver;

    const GET_COMPONENT_ACCESS_TOKEN  = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token'; //获取第三方token
    const GET_COMPONENT_PRE_AUTH_CODE = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode'; //获取第三方auth_code

    /**
     * Core constructor.
     *
     */
    function __construct()
    {
        self::$cacheDriver    = new CacheFileDriver(dirname(dirname(dirname(__FILE__))) . '/cache');
        self::$databaseDriver = new DatabaseFileDriver(dirname(dirname(dirname(__FILE__))) . '/database');

        $configs = Config::$configs;
        try {
            if (!isset($configs['component_app_id']) || !isset($configs['component_app_secret']) || !isset($configs['component_app_token']) || !isset($configs['component_app_key'])) {
                throw new ConfigMistakeException();
            }
        } catch (ConfigMistakeException $e) {
            echo $e->getMessage();
        }

        if (!$this->configs) {
            $this->configs                       = new stdClass();
            $this->configs->component_app_id     = $configs['component_app_id'];
            $this->configs->component_app_secret = $configs['component_app_secret'];
            $this->configs->component_app_token  = $configs['component_app_token'];
            $this->configs->component_app_key    = $configs['component_app_key'];
        }
    }

    /**
     * 获取开放平台 ComponentAccessToken
     *
     * @return bool|null|string|void
     */
    public function getComponentAccessToken()
    {
        $component_access_token = self::$cacheDriver->_get('component_access_token:' . $this->configs->component_app_id);

        if (false == $component_access_token) {

            $request_data = [
                'component_appid'         => $this->configs->component_app_id,
                'component_appsecret'     => $this->configs->component_app_secret,
                'component_verify_ticket' => self::$cacheDriver->_get('component_verify_ticket:' . $this->configs->component_app_id),
            ];

            $response_data = Http::_post(self::GET_COMPONENT_ACCESS_TOKEN, $request_data);

            if (!$response_data || !is_array($response_data) || empty($response_data)) {
                $this->setError(Http::$error);

                return false;
            }

            self::$cacheDriver->_set('component_access_token:' . $this->configs->component_app_id, $response_data['component_access_token'], 5000);

            $component_access_token = $response_data['component_access_token'];
        }

        return $component_access_token;
    }

    /**
     * 获取第三方auth_code
     *
     * @return bool|null|string|void
     */
    public function getComponentPreAuthCode()
    {
        $component_pre_auth_code = self::$cacheDriver->_get('component_pre_auth_code:' . $this->configs->component_app_id);

        if (false == $component_pre_auth_code) {
            $component_access_token = $this->getComponentAccessToken();

            $query_data   = http_build_query(['component_access_token' => $component_access_token]);
            $request_data = [
                'component_appid' => $this->configs->component_app_id,
            ];

            $response_data = Http::_post(self::GET_COMPONENT_PRE_AUTH_CODE . '?' . $query_data, $request_data);
            if (!$response_data || !is_array($response_data) || empty($response_data)) {
                $this->setError(Http::$error);

                return false;
            }

            self::$cacheDriver->_set('component_pre_auth_code:' . $this->configs->component_app_id, $response_data['pre_auth_code'], 5000);

            $component_pre_auth_code = $response_data['pre_auth_code'];
        }

        return $component_pre_auth_code;
    }

    /**
     * @return mixed
     */
    public static function getError()
    {
        return self::$error;
    }

    /**
     * @param string $error
     */
    public static function setError($error = '')
    {
        self::$error = $error;
    }
}