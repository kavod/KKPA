<?php
/**
 * KKPA Api Client by Kavod
 *
 */
  namespace KKPA\Clients;

  use KKPA\Exceptions\KKPASDKException;
  use KKPA\Exceptions\KKPAClientException;
  use KKPA\Exceptions\KKPADeviceException;
  use KKPA\Exceptions\KKPAApiErrorType;
  use KKPA\Exceptions\KKPACurlErrorType;
  use KKPA\Exceptions\KKPAJsonErrorType;
  use KKPA\Exceptions\KKPAInternalErrorType;
  use KKPA\Exceptions\KKPANotLoggedErrorType;
  use KKPA\Common\KKPARestErrorCode;

  define('TPLINK_BASE_URI', "https://wap.tplinkcloud.com/");
  define('KKPA_VERSION',"2.0");
  define('KKPA_LOCAL_TIMEOUT',2);
  define('KKPA_BROADCAST_IP','255.255.255.255');
  define('KKPA_DEFAULT_PORT',9999);
  define('KKPA_MAX_ATTEMPTS',3);

  class KKPAApiClient
  {
    public $conf = array();
    protected $token;
    protected static $last_request = "";
    protected static $last_result = "";
    protected static $last_errno = 0;
    protected static $last_deviceId = "";
    protected $uuid = "";
    protected $cloud = false;
    protected $local_ip = '';

    const REQ_SYSINFO = array(
      "system"=>array(
        "get_sysinfo"=>NULL
      )
    );

    const REQ_DEVICELIST = array("method" => "getDeviceList");

    public static function getVersion() {
      return KKPA_VERSION;
    }

    /**
    * Default options for cURL.
    */
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_HEADER         => TRUE,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'Kasa_Android',
        CURLOPT_SSL_VERIFYPEER => TRUE,
        CURLOPT_HTTPHEADER     => array(
            "Content-Type: application/json"
          )
    );

    /**
    * Initialize a KKPA Client.
    *
    * @param $config
    *   An associative array as below:
    *   - code: (optional) The authorization code.
    *   - username: (optional) The username.
    *   - password: (optional) The password.
    *   - token: (optional) Stored token
    *   - uuid: (optional) Stored uuid
    *   - cloud: (optional) Is cloud used?
    */
    public function __construct($conf = array())
    {
        $config = array_merge(array(),$conf);
        if(array_key_exists('cloud',$config))
        {
          $this->cloud = boolval($config["cloud"]);
        } else {
            $this->cloud = true;
        }
        if ($this->cloud)
        {
          // If tokens are provided let's store it
          if(isset($config["token"]))
          {
              $this->token = $config["token"];
              unset($token);
          }
          if (isset($config["uuid"]))
          {
              $this->uuid = $config["uuid"];
          } else {
              $this->uuid = self::guidv4();
          }
          $this->setVariable('base_uri',TPLINK_BASE_URI);

          if($this->getVariable("code") == null && isset($_GET["code"]))
          {
              $this->setVariable("code", $_GET["code"]);
          }
        } else {
          if (array_key_exists('local_ip',$config))
          {
              $this->local_ip = $config["local_ip"];
          }
          if (array_key_exists('local_port',$config))
          {
              $this->local_port = $config["local_port"];
          }
        }
        // Other else configurations.
        foreach ($config as $name => $value)
        {
            $this->setVariable($name, $value);
        }
    }
    // Getters
    /**
     * Returns a persistent variable.
     *
     * To avoid problems, always use lower case for persistent variable names.
     *
     * @param $name
     *   The name of the variable to return.
     * @param $default
     *   The default value to use if this variable has never been set.
     *
     * @return
     *   The value of the variable.
     */
    public function getVariable($name, $default = NULL)
    {
        return array_key_exists($name,$this->conf) ? $this->conf[$name] : $default;
    }

    public function getClass() {
        $path = explode('\\', get_class($this));
        return array_pop($path);
    }

    public function getDeviceByIp($ip,$port=9999)
    {
      if ($this->getVariable('cloud',1))
        throw new KKPAClientException(994,"getDeviceByIp cannot be used in Cloud mode","Error");
      $conf = array(
        "cloud" => false,
        "local_ip" => $ip,
        "local_port" => $port,
        "username" => $this->getVariable('username',''),
        "password" => $this->getVariable('password','')
      );
      $device = new KKPADeviceApiClient($conf);
      switch($device->getType())
      {
        case 'IOT.SMARTPLUGSWITCH':
          if ($device->getModel()=='HS300')
            return new KKPAMultiPlugApiClient($conf);
          return new KKPAPlugApiClient($conf);
          break;
        case 'IOT.SMARTBULB':
          return new KKPABulbApiClient($conf);
          break;
      }
    }

    public function getDeviceById($deviceId)
    {
      if ($this->getVariable('cloud',1)==1)
      {
        $conf = array_merge(array(),$this->conf);
        $conf['deviceId'] = $deviceId;
        $device = new KKPADeviceApiClient($conf);
        switch($device->getType())
        {
          case 'IOT.SMARTPLUGSWITCH':
            if ($device->getModel()=='HS300')
              return new KKPAMultiPlugApiClient($conf);
            return new KKPAPlugApiClient($conf);
            break;
          case 'IOT.SMARTBULB':
            return new KKPABulbApiClient($conf);
            break;
        }
      } else
      {
        for($attempt=0;$attempt<KKPA_MAX_ATTEMPTS;$attempt++)
        {
          $deviceList = $this->getDeviceList();
          foreach($deviceList as $device)
          {
            if ($device->getVariable('deviceId','')==$deviceId)
              return $device;
          }
          sleep(0.5);
        }
        throw new KKPAClientException(
          KKPA_NOT_FOUND,
          "Device $deviceId not found on network (".KKPA_MAX_ATTEMPTS." attempts)",
          "Error"
        );
      }
    }

    static protected function getAltAttr($json,$keys,$default=null)
    {
      foreach($keys as $key)
      {
        if (array_key_exists($key,$json))
          return $json[$key];
      }
      if(is_null($default))
      {
        throw new KKPAClientException(
          997,
          "Cannot determine ".$keys[0]."\n".print_r($json,true),
          "Error"
        );
      } else
      {
        return $default;
      }
    }

    static protected function readType($sysinfo)
    {
      return self::getAltAttr($sysinfo,array('deviceType','mic_type','type'));
    }

    static protected function readModel($sysinfo)
    {
      return self::getAltAttr($sysinfo,array('model','deviceModel'));
    }

    static protected function getErrorCode($response)
    {
      return self::getAltAttr($response,array('error_code','err_code'));
    }

    // Setters

    /**
    * Sets a persistent variable.
    *
    * To avoid problems, always use lower case for persistent variable names.
    *
    * @param $name
    *   The name of the variable to set.
    * @param $value
    *   The value to set.
    */
    public function setVariable($name, $value)
    {
        $this->conf[$name] = $value;
        return $this;
    }

    // Debug functions
    protected function setLastRequest($request_arr)
    {
      self::$last_deviceId = $this->getVariable('deviceId','Unknown');
      self::$last_request = $this->anonymizeUserPass($request_arr);
      self::$last_result = "";
      self::$last_errno = 0;
    }

    protected function anonymizeUserPass($str)
    {
      return str_replace(
        $this->getVariable('password'),
        '*****',
        str_replace(
          $this->getVariable('username'),
          '*****',
          $str
        )
      );
    }

    protected function setLastResponse($result,$errno)
    {
      self::$last_result = preg_replace(
        '/((?:\\\\)?)\"(latitude|longitude)(_i)?((?:\\\\)?)\":(?:(?:\\\\)?\")?(?:-?\d+(?:\.\d+)?)(?:(?:\\\\)?\")?(,|\})/',
        '${1}"${2}${3}${4}":0${5}',
        str_replace(
          $this->getVariable('username'),
          '*****',
          print_r($result,true)
        )
      );
      self::$last_errno = $errno;
    }

    public static function debug_last_request() {
      return array(
        "deviceId" => self::$last_deviceId,
        "request" => self::$last_request,
        "result" => self::$last_result,
        "errno" => self::$last_errno
      );
    }

    public function toArray() {
      return array(
        'conf' => array(
          'base_uri' => TPLINK_BASE_URI,
          'username' => ($this->getVariable('username','')!='') ? '*****' : '',
          'password' => ($this->getVariable('password','')!='') ? '*****' : '',
          'deviceId' => $this->getVariable('deviceId',''),
          'local_ip' => $this->getVariable('local_ip',''),
          'local_port' => $this->getVariable('local_port','')
        ),
        'token' => $this->token,
        'uuid' => $this->uuid
        );
    }

    public function toString() {
      return print_r($this->toArray(),true);
    }

    // Network functions (Cloud & Local)
    protected function send($request_arr)
    {
      $cloud = $this->getVariable('cloud');
      $deviceId = $this->getVariable('deviceId',null);
      $requestData = $this->makeRequestData($request_arr);
      if ($cloud)
      {
        try
        {
            $token = $this->getAccessToken();
        }
        catch(KKPAApiErrorType $ex)
        {
            throw new KKPANotLoggedErrorType($ex->getCode(), $ex->getMessage());
        }
        $result = $this->makeRequest($requestData);
        $result = self::extractCloudResponse($result);
      } else
      {
        $result = $this->makeLocalRequest($requestData);
      }
      $result = self::extractResponse($result,$request_arr);
      self::checkErrorCode($result);
      return $result;
    }

    public function getDeviceList()
    {
      if ($this->cloud)
      {
        $json_request = json_encode(self::REQ_DEVICELIST);
        $this->setLastRequest($json_request);
        $result = $this->makeOAuth2Request($json_request);
        self::checkErrorCode($result);
        if(!array_key_exists('result',$result))
        {
          throw new KKPAClientException(999,"No response content: ".print_r($result,true),"Error");
        }
        $result = $result['result'];
      } else
      {
        $result = self::makeBroadcastRequest();
      }
      $devices = array();
      $conf = $this->conf;
      foreach($result['deviceList'] as $device)
      {
        switch(self::readType($device))
        {
          case "IOT.SMARTPLUGSWITCH":
            $conf["deviceId"] = $device['deviceId'];
            if ($this->cloud)
            {
            } else
            {
              $conf['local_ip'] = $device['local_ip'];
              $conf['local_port'] = $device['local_port'];
              $conf['cloud'] = false;
            }
            // HS300 ?
            if (substr(self::readModel($device),0,5)=='HS300')
            {
              //break;
              $devices[] = new KKPAMultiPlugApiClient($conf);
              break;
            } else
            {
              $devices[] = new KKPAPlugApiClient($conf);
              break;
            }
            break;

          case "IOT.SMARTBULB":
            $conf["deviceId"] = $device['deviceId'];
            if ($this->cloud)
            {
            } else
            {
              $conf['local_ip'] = $device['local_ip'];
              $conf['local_port'] = $device['local_port'];
              $conf['cloud'] = false;
            }
            $devices[] = new KKPABulbApiClient($conf);
            break;
        }
      }
      return $devices;
    }

    public static function extractResponse($response,$request)
    {
      if (
        is_array($request)
        && count($request)>0
        && (
          is_array($request[array_keys($request)[0]])
          || is_null($request[array_keys($request)[0]])
          )
      )
      {
        $key = array_keys($request)[0];
        // TODO: proper exception
        if (!isset($response[$key]))
          throw new \Exception('Exception:'.$key."\n".print_r($request,true)."\n".print_r($response,true));
        return self::extractResponse($response[$key],$request[$key]);
      } else {
        return $response;
      }
    }

    protected static function checkErrorCode($response)
    {
      $error_code = self::getErrorCode($response);
      $msg = self::getAltAttr($response,array('msg'),'');
      if(!isset($error_code))
      {
          throw new KKPAClientException($error_code,"Error ".$error_code,"Error");
      }
      if($error_code == KKPA_DEVICE_OFFLINE) // KKPA_DEVICE_OFFLINE -20571
      {
          throw new KKPADeviceException($error_code,"Device is offline","Error");
      }
      if($error_code == KKPA_TIMEOUT) // KKPA_TIMEOUT -20002
      {
          throw new KKPADeviceException($error_code,"Request timeout","Error");
      }
      if($error_code == KKPA_NOT_BINDED) // KKPA_NOT_BINDED -20580
      {
          throw new KKPADeviceException($error_code,"Account is not binded to the device","Error");
      }
      if($error_code !=0)
      {
          throw new KKPAClientException($error_code,"Error ($error_code)\n$msg\n".print_r(self::debug_last_request()),"Error");
      }
    }

    protected function makeRequestData($request_arr)
    {
      $json = json_encode($request_arr);
      if ($this->getVariable("cloud"))
      {
        $deviceId = $this->getVariable('deviceId',null);
        if (is_null($deviceId))
          throw new KKPAApiErrorType(996,"Missing or incorrect format for deviceId","Error");
        $request_json = json_encode(
          array(
            "method"=>"passthrough",
            "params"=>array(
              "deviceId"=>$deviceId,
              "requestData"=>$json
            )
          )
        );
        $this->setLastRequest($request_json);
        return $request_json;
      } else
      {
        $this->setLastRequest($json);
        return self::tp_encrypt($json);
      }
    }

    // Cloud methods
    /**
    * Make an OAuth2.0 Request.
    *
    * Automatically append "access_token" in query parameters
    *
    * @param $params
    *   (optional The POST parameters.
    *
    * @return
    *   The JSON decoded response object.
    *
    * @throws OAuth2Exception
    */
    protected function makeOAuth2Request($params = array(), $reget_token = true)
    {
        try
        {
            $token = $this->getAccessToken();
        }
        catch(KKPAApiErrorType $ex)
        {
            throw new KKPANotLoggedErrorType($ex->getCode(), $ex->getMessage());
        }
        try
        {
            $res = $this->makeRequest($params);
            return $res;
        }
        catch(KKPAApiErrorType $ex)
        {
            if($reget_token == true)
            {
                switch($ex->getCode())
                {
                    case KKPARestErrorCode::INVALID_ACCESS_TOKEN:
                    case KKPARestErrorCode::ACCESS_TOKEN_EXPIRED:
                        throw $ex;
                        return $this->makeOAuth2Request($params, false);
                    break;
                    default:
                        throw $ex;
                }
            }
            else throw $ex;
        }
        return $res;
    }

    /**
    * Makes an HTTP request.
    *
    * This method can be overriden by subclasses if developers want to do
    * fancier things or use something other than cURL to make the request.
    *
    * @param $params
    *   (optional The GET/POST parameters.
    *
    * @return
    *   The json_decoded result or KKPAClientException if pb happend
    */
    public function makeRequest($params = array())
    {
        $ch = curl_init();
        $opts = self::$CURL_OPTS;
        if ($params)
        {
            $opts[CURLOPT_POSTFIELDS] = $params;
        }
        $opts[CURLOPT_URL] = TPLINK_BASE_URI . (
          (isset($this->token)) ? '?token=' . $this->token : ''
        );
        // Disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
        // for 2 seconds if the server does not support this header.
        if (isset($opts[CURLOPT_HTTPHEADER]))
        {
            $existing_headers = $opts[CURLOPT_HTTPHEADER];
            $existing_headers[] = 'Expect:';
            $ip = $this->getVariable("ip");
            if($ip)
                $existing_headers[] = 'CLIENT_IP: '.$ip;
            $opts[CURLOPT_HTTPHEADER] = $existing_headers;
        }
        else
        {
            $opts[CURLOPT_HTTPHEADER] = array('Expect:');
        }
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $this->setLastResponse($result,$errno);
        // CURLE_SSL_CACERT || CURLE_SSL_CACERT_BADFILE
        if ($errno == 60 || $errno == 77)
        {
            echo "WARNING ! SSL_VERIFICATION has been disabled since ssl error retrieved. Please check your certificate http://curl.haxx.se/docs/sslcerts.html\n";
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $result = curl_exec($ch);
        }
        if ($result === FALSE)
        {
            $e = new KKPACurlErrorType(curl_errno($ch), curl_error($ch));
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);
        // Split the HTTP response into header and body.
        list($headers, $body) = explode("\r\n\r\n", $result);
        $headers = explode("\r\n", $headers);
        //Only 2XX response are considered as a success
        if(strpos($headers[0], 'HTTP/1.1 2') !== FALSE)
        {
            $decode = json_decode($body, TRUE);
            if(!$decode)
            {
                if (preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches))
                {
                    throw new KKPAJsonErrorType($matches[1], $matches[2]);
                }
                else throw new KKPAJsonErrorType(200, "OK");
            }
            return $decode;
        }
        else
        {
            if (!preg_match('/^HTTP\/1.1 ([0-9]{3,3}) (.*)$/', $headers[0], $matches))
            {
                $matches = array("", 400, "bad request");
            }
            $decode = json_decode($body, TRUE);
            if(!$decode)
            {
                throw new KKPAApiErrorType($matches[1], $matches[2], null);
            }
            throw new KKPAApiErrorType($matches[1], $matches[2], $decode);
        }
    }

    // Cloud - Auth
    private function setTokens($value)
    {
        if(!isset($value['error_code']) || $value['error_code'] != 0)
        {
          throw new KKPAClientException($value['error_code'],"Error retrieving token","Error");
        }
        if(isset($value["result"]) && isset($value['result']['token']))
        {
            $this->token = $value['result']['token'];
            $this->accountId = $value['result']['accountId'];
        }
    }

    /**
    * Retrieve an access token following the best grant to recover it (order id : code, refresh_token, password)
    *
    * @return
    * A valid array containing at least an access_token as an index
    *  @throw
    * A KKPAClientException if unable to retrieve an access_token
    */
    public function getAccessToken()
    {
        //find best way to retrieve access_token
        if($this->token)
        {
            return array("token" => $this->token);
        }
        if($this->getVariable('username') && $this->getVariable('password'))  //grant_type == password
        {
            return $this->getAccessTokenFromPassword($this->getVariable('username'), $this->getVariable('password'));
        }
        else throw new KKPAInternalErrorType("No access token");
    }

  /**
   * Get access token from OAuth2.0 token endpoint with basic user
   * credentials.
   *
   * This function will only be activated if both username and password
   * are setup correctly.
   *
   * @param $username
   *   Username to be check with.
   * @param $password
   *   Password to be check with.
   *
   * @return
   *   A valid OAuth2.0 JSON decoded access token in associative array
   * @thrown
   *  A KKPAClientException if unable to retrieve an access_token
   */
    private function getAccessTokenFromPassword($username, $password)
    {
        if ($username && $password)
        {
          $request_arr = array(
            "method" => "login",
            "params" => array(
              "appType" => "Kasa_Android",
              "cloudPassword" => $password,
              "cloudUserName" => $username,
              "terminalUUID" => $this->uuid
            )
          );
          $params = json_encode($request_arr);
          $ret = $this->makeRequest(
              $params
          );

          $this->setTokens($ret);
          return $this->token;
        }
        else
            throw new KKPAInternalErrorType("missing args for getting password grant");
    }

    public static function extractCloudResponse($response)
    {
      self::checkErrorCode($response);
      if(!array_key_exists('result',$response))
      {
        throw new KKPAClientException(999,"No response content: ".print_r($response,true),"Error");
      }
      if(!array_key_exists('responseData',$response['result']))
      {
        throw new KKPAClientException(998,"No response content: ".print_r($response,true),"Error");
      }
      return json_decode($response['result']['responseData'],true);
    }

    protected static function guidv4()
    {
      if (version_compare(PHP_VERSION,'7','<'))
      {
        $data = openssl_random_pseudo_bytes(16);
      }
      else {
        $data = random_bytes(16);
      }

      $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
      $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

      return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // Local methods
    // Inspired by https://github.com/RobertShippey/hs100-php-api/blob/master/utils.php
    protected static function tp_encrypt($clear_text, $tcp=true, $first_key = 0xAB) {
        $buf = unpack('c*', $clear_text);
        $key = $first_key;
        for ($i = 1; $i < count($buf) + 1; $i++) {
            $buf[$i] = $buf[$i] ^ $key;
            $key = $buf[$i];
        }
        $array_map = array_map('chr', $buf);
        $clear_text = implode('', $array_map);
        $length = strlen($clear_text);
        $header = ($tcp) ? pack('N*', $length) : '';
        return $header . $clear_text;
    }

    protected static function tp_decrypt($cypher_text, $tcp=true, $first_key = 0xAB) {
      if ($tcp)
      {
        $header = substr($cypher_text, 0, 4);
        $header_length = unpack('N*', $header)[1];
        $cypher_text = substr($cypher_text, 4);
      } else {
        $header_length = strlen($cypher_text);
      }
        $buf = unpack('c*', $cypher_text);
        $key = $first_key;
        $nextKey;
        for ($i = 1; $i < count($buf) + 1; $i++) {
            $nextKey = $buf[$i];
            $buf[$i] = $buf[$i] ^ $key;
            $key = $nextKey;
        }
        $array_map = array_map('chr', $buf);
        $clear_text = implode('', $array_map);
        $cypher_length = strlen($clear_text);
        if ($header_length !== $cypher_length) {
            trigger_error("Length in header ({$header_length}) doesn't match actual message length ({$cypher_length}).");
        }
        return $clear_text;
    }

    protected function makeLocalRequest($requestData)
    {
      $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      $data = '';
      $out = '';
      $success = @socket_connect($sock, $this->local_ip, $this->local_port);
      $err = socket_last_error($sock);
      if (!$success || $err > 0)
      {
        socket_close($sock);
        throw new KKPAClientException(
          KKPA_NO_ROUTE_TO_HOST,
          "Error $err during connection to $this->local_ip",
          "Error"
        );
      }
      socket_write($sock, $requestData, strlen($requestData));
      $out = socket_read($sock, 2048);
      while ($out && $out!='') {
          $data .= $out;
          $decrypt = self::tp_decrypt($data,true);
          if (strlen($decrypt)>0 && (substr_count($decrypt,'{')==substr_count($decrypt,'}')))
            break;
          else
            $out = socket_read($sock, 2048);
      }
      socket_close($sock);
      $decrypt = self::tp_decrypt($data,true);
      $this->setLastResponse($decrypt,socket_last_error());
      $result = json_decode($decrypt,true);
      if (is_null($result))
      {
        throw new KKPAClientException(
          KKPA_EMPTY_ANSWER,
          "Empty answer",
          "Error"
        );
      }
      return $result;
    }

    protected function makeBroadcastRequest()
    {
      $request_arr = self::REQ_SYSINFO;
      $request_json = json_encode($request_arr);

      $this->setLastRequest($request_json);
      $requestData = self::tp_encrypt($request_json,false);

      $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
      socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
      socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>KKPA_LOCAL_TIMEOUT, "usec"=>0));
      $result = array();
      $found_ip = array();
      for ($i=0;$i<3;$i++)
      {
        socket_sendto($sock, $requestData, strlen($requestData), 0, KKPA_BROADCAST_IP, KKPA_DEFAULT_PORT);
        while(true) {
          $ret = @socket_recvfrom($sock, $buf, 128*1024, 0, $local_ip, $local_port);
          if($ret === false) break;
          $response = self::tp_decrypt($buf,false);
          $data = json_decode($response,true);
          $data = self::extractResponse($data,$request_arr);
          $data['local_ip'] = $local_ip;
          $data['local_port'] = $local_port;
          $this->setLastResponse($response,socket_last_error());
          if (!in_array($local_ip,$found_ip))
          {
            $result[] = $data;
            $found_ip[] = $local_ip;
          }
          sleep(0.5);
        }
      }
      socket_close($sock);
      //sleep(KKPA_LOCAL_TIMEOUT);
      return array(
          "deviceList" => $result
      );
    }
}
 ?>
