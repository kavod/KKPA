<?php

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
  define('KKPA_VERSION',"1.0");

  class KKPAApiClient
  {
    protected $conf = array();
    protected $token;
    protected $expires_at;
    protected $last_request = "";
    protected $last_result = "";
    protected $last_errno = 0;
    protected $uuid = "";

    public static function getVersion() {
      return KKPA_VERSION;
    }

    public function debug_last_request() {
      return array(
        "request" => $this->last_request,
        "result" => $this->last_result,
        "errno" => $this->last_errno
      );
    }

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
        return isset($this->conf[$name]) ? $this->conf[$name] : $default;
    }

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

    public function toArray() {
      return array(
        'conf' => array(
          'base_uri' => $this->getVariable('base_uri',''),
          'username' => ($this->getVariable('username','')!='') ? '*****' : '',
          'password' => ($this->getVariable('password','')!='') ? '*****' : '',
          'deviceId' => $this->getVariable('deviceId','')
        ),
        'token' => $this->token,
        'uuid' => $this->uuid
        );
    }

    public function toString() {
      return print_r($this->toArray(),true);
    }

    private function updateSession()
    {
        $cb = $this->getVariable("func_cb");
        $object = $this->getVariable("object_cb");
        if($object && $cb)
        {
            if(method_exists($object, $cb))
            {
            call_user_func_array(array($object, $cb), array(array("token"=> $this->token)));
            }
        }
        else if($cb && is_callable($cb))
        {
        call_user_func_array($cb, array(array("token" => $this->token)));
        }
    }

    private function setTokens($value)
    {
        if(!isset($value['error_code']) || $value['error_code'] != 0)
        {
          throw new KKPAClientException($value['error_code'],"Error retrieving token");
        }
        if(isset($value["result"]) && isset($value['result']['token']))
        {
            $this->token = $value['result']['token'];
            $this->accountId = $value['result']['accountId'];
            $update = true;
        }
        if(isset($value["expires_in"]))
        {
            $this->expires_at = time() + $value["expires_in"] - 30;
        }
        if(isset($update)) $this->updateSession();
    }
    /**
     * Set token stored by application (in session generally) into this object
    **/
    public function setTokensFromStore($value)
    {
        if(isset($value["token"]))
            $this->token = $value["token"];
    }
    public function unsetTokens()
    {
        $this->token = null;
        $this->expires_at = null;
    }

    /**
    * Initialize a KKPA OAuth2.0 Client.
    *
    * @param $config
    *   An associative array as below:
    *   - code: (optional) The authorization code.
    *   - username: (optional) The username.
    *   - password: (optional) The password.
    *   - client_id: (optional) The application ID.
    *   - client_secret: (optional) The application secret.
    *   - refresh_token: (optional) A stored refresh_token to use
    *   - access_token: (optional) A stored access_token to use
    *   - object_cb : (optionale) An object for which func_cb method will be applied if object_cb exists
    *   - func_cb : (optional) A method called back to store tokens in its context (session for instance)
    */
    public function __construct($config = array())
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
        // We must set uri first.
        $uri = array("base_uri" => TPLINK_BASE_URI);
        foreach($uri as $key => $val)
        {
            if(isset($config[$key]))
            {
                $this->setVariable($key, $config[$key]);
                unset($config[$key]);
            }
            else
            {
                $this->setVariable($key, $val);
            }
        }
        if(isset($config['scope']) && is_array($config['scope']))
        {
            foreach($config['scope'] as $scope)
            {
                trim($scope);
            }
            $scope = implode(' ', $config['scope']);
            $this->setVariable('scope', $scope);
            unset($config['scope']);
        }
        // Other else configurations.
        foreach ($config as $name => $value)
        {
            $this->setVariable($name, $value);
        }
        if($this->getVariable("code") == null && isset($_GET["code"]))
        {
            $this->setVariable("code", $_GET["code"]);
        }
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
    * Makes an HTTP request.
    *
    * This method can be overriden by subclasses if developers want to do
    * fancier things or use something other than cURL to make the request.
    *
    * @param $path
    *   The target path, relative to base_path/service_uri or an absolute URI.
    * @param $method
    *   (optional) The HTTP method (default 'GET').
    * @param $params
    *   (optional The GET/POST parameters.
    * @param $ch
    *   (optional) An initialized curl handle
    *
    * @return
    *   The json_decoded result or KKPAClientException if pb happend
    */
    public function makeRequest($path, $method = 'POST', $params = array())
    {
        $ch = curl_init();
        $opts = self::$CURL_OPTS;
        if ($params)
        {
            if(isset($this->token))
            {
                $path .= '?token=' . $this->token;
            }
            switch ($method)
            {
                case 'GET':
                    $path .= '?' . http_build_query($params, NULL, '&');
                break;
                // Method override as we always do a POST.
                default:
                    $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            }
        }
        $opts[CURLOPT_URL] = $path;
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
        $this->last_request = str_replace(
          $this->getVariable('password'),
          '*****',
          str_replace(
            $this->getVariable('username'),
            '*****',
            print_r($opts,true)
          )
        );
        $this->last_result = "";
        $this->last_errno = 0;
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $this->last_result = preg_replace(
          '/\\\"(latitude(_i)?)\\\":(-?\d+(\.\d+)?)(,|\})/',
          '\\"$1\\":0${5}',
          preg_replace(
            '/\\\"(longitude(_i)?)\\\":(-?\d+(\.\d+)?)(,|\})/',
            '\\"$1\\":0${5}',
            str_replace(
              $this->getVariable('username'),
              '*****',
              $result
            )
          )
        );
        $this->last_errno = $errno;
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
            if(!is_null($this->expires_at) && $this->expires_at < time()) // access_token expired.
            {
                throw new KKPAInternalErrorType("Access token expired");
            }
            return array("token" => $this->token);
        }
        if($this->getVariable('code'))// grant_type == authorization_code.
        {
            return $this->getAccessTokenFromAuthorizationCode($this->getVariable('code'));
        }
        else if($this->getVariable('username') && $this->getVariable('password'))  //grant_type == password
        {
            return $this->getAccessTokenFromPassword($this->getVariable('username'), $this->getVariable('password'));
        }
        else throw new KKPAInternalErrorType("No access token stored");
    }

    /**
    * Get access token from OAuth2.0 token endpoint with authorization code.
    *
    * This function will only be activated if both access token URI, client
    * identifier and client secret are setup correctly.
    *
    * @param $code
    *   Authorization code issued by authorization server's authorization
    *   endpoint.
    *
    * @return
    *   A valid OAuth2.0 JSON decoded access token in associative array
    * @thrown
    *  A KKPAClientException if unable to retrieve an access_token
    */
    private function getAccessTokenFromAuthorizationCode($code)
    {
        $scope = $this->getVariable('scope');
        if($this->getVariable('base_uri') && ($client_id = $this->getVariable('client_id')) != NULL && ($client_secret = $this->getVariable('client_secret')) != NULL)
        {
            $ret = $this->makeRequest($this->getVariable('base_uri'),
                'POST',
                array(
                    'grant_type' => 'authorization_code',
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'code' => $code,
                    'scope' => $scope,
                )
            );
            $this->setTokens($ret);
            return $ret;
        }
        else
            throw new KKPAInternalErrorType("missing args for getting authorization code grant");
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
        $scope = $this->getVariable('scope');
        if ($this->getVariable('base_uri'))
        {
          $params = json_encode(
                    array(
                      "method" => "login",
                      "params" => array(
                        "appType" => "Kasa_Android",
                        "cloudPassword" => $password,
                        "cloudUserName" => $username,
                        "terminalUUID" => $this->uuid
                      )
                    )
                  );
            $ret = $this->makeRequest(
                $this->getVariable('base_uri'),
                'POST',
                $params
            );
            $this->setTokens($ret);
            return $this->token;
        }
        else
            throw new KKPAInternalErrorType("missing args for getting password grant");
    }

    /**
    * Make an OAuth2.0 Request.
    *
    * Automatically append "access_token" in query parameters
    *
    * @param $path
    *   The target path, relative to base_path/service_uri
    * @param $method
    *   (optional) The HTTP method (default 'GET').
    * @param $params
    *   (optional The GET/POST parameters.
    *
    * @return
    *   The JSON decoded response object.
    *
    * @throws OAuth2Exception
    */
    protected function makeOAuth2Request($path, $method = 'POST', $params = array(), $reget_token = true)
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
            $res = $this->makeRequest($path, $method, $params);
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
                        return $this->makeOAuth2Request($path, $method, $params, false);
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
     * Make an API call.
     *
     * Support both OAuth2.0 or normal GET/POST API call, with relative
     * or absolute URI.
     *
     * If no valid OAuth2.0 access token found in session object, this function
     * will automatically switch as normal remote API call without "access_token"
     * parameter.
     *
     * Assume server reply in JSON object and always decode during return. If
     * you hope to issue a raw query, please use makeRequest().
     *
     * @param $path
     *   The target path, relative to base_path/service_uri or an absolute URI.
     * @param $method
     *   (optional) The HTTP method (default 'GET').
     * @param $params
     *   (optional The GET/POST parameters.
     *
     * @return
     *   The JSON decoded body response object.
     *
     * @throws KKPAClientException
    */
    public function api($path, $method = 'POST', $params = array(), $secure = false)
    {
      $res = $this->makeOAuth2Request($this->getUri($path, array(), $secure), $method, $params);
      if(!isset($res['error_code']))
      {
          throw new KKPAClientException($res['error_code'],"Error ".$res['error_code'],"Error");
      }
      if($res['error_code'] == KKPA_DEVICE_OFFLINE) // KKPA_DEVICE_OFFLINE -20571
      {
          throw new KKPADeviceException($res['error_code'],"Device is offline","Error");
      }
      if($res['error_code'] == KKPA_TIMEOUT) // KKPA_TIMEOUT -20002
      {
          throw new KKPADeviceException($res['error_code'],"Request timeout","Error");
      }
      if($res['error_code'] !=0)
      {
          throw new KKPAClientException($res['error_code'],"Error ".$res['error_code'],"Error");
      }
      if(isset($res["result"])) return $res["result"];
      else return $res;
    }

    static public function str_replace_once($str_pattern, $str_replacement, $string)
    {
        if (strpos($string, $str_pattern) !== false)
        {
            $occurrence = strpos($string, $str_pattern);
            return substr_replace($string, $str_replacement, strpos($string, $str_pattern), strlen($str_pattern));
        }
        return $string;
    }
    /**
    * Since $_SERVER['REQUEST_URI'] is only available on Apache, we
    * generate an equivalent using other environment variables.
    */
    function getRequestUri()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        }
        else {
            if (isset($_SERVER['argv'])) {
                $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['argv'][0];
            }
            elseif (isset($_SERVER['QUERY_STRING'])) {
                $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
            }
            else {
                $uri = $_SERVER['SCRIPT_NAME'];
            }
        }
        // Prevent multiple slashes to avoid cross site requests via the Form API.
        $uri = '/' . ltrim($uri, '/');
        return $uri;
    }
  /**
   * Returns the Current URL.
   *
   * @return
   *   The current URL.
   */
    protected function getCurrentUri()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on'
          ? 'https://'
          : 'http://';
        $current_uri = $protocol . $_SERVER['HTTP_HOST'] . $this->getRequestUri();
        $parts = parse_url($current_uri);
        $query = '';
        if (!empty($parts['query'])) {
          $params = array();
          parse_str($parts['query'], $params);
          $params = array_filter($params);
          if (!empty($params)) {
            $query = '?' . http_build_query($params, NULL, '&');
          }
        }
        // Use port if non default.
        $port = isset($parts['port']) &&
          (($protocol === 'http://' && $parts['port'] !== 80) || ($protocol === 'https://' && $parts['port'] !== 443))
          ? ':' . $parts['port'] : '';
        // Rebuild.
        return $protocol . $parts['host'] . $port . $parts['path'] . $query;
    }
    /**
    * Build the URL for given path and parameters.
    *
    * @param $path
    *   (optional) The path.
    * @param $params
    *   (optional) The query parameters in associative array.
    *
    * @return
    *   The URL for the given parameters.
    */
    protected function getUri($path = '', $params = array(), $secure = false)
    {
        $url = $this->getVariable('base_uri');
        if($secure == true)
        {
            $url = self::str_replace_once("http", "https", $url);
        }
        if(!empty($path))
            if (substr($path, 0, 4) == "http")
                $url = $path;
            else if(substr($path, 0, 5) == "https")
                $url = $path;
            else
                $url = rtrim($url, '/') . '/' . ltrim($path, '/');
        if (!empty($params))
            $url .= '?' . http_build_query($params, NULL, '&');
        return $url;
    }
    public function getPartnerDevices()
    {
        return $this->api("partnerdevices", "POST");
    }

    public function getDeviceList()
    {
      $result = $this->api("",'POST',json_encode(array("method" => "getDeviceList")))['deviceList'];
      $devices = array();
      $conf = $this->conf;
      foreach($result as $device)
      {
        switch($device['deviceType'])
        {
          case "IOT.SMARTPLUGSWITCH":
            $conf["deviceId"] = $device['deviceId'];
            $devices[] = new KKPAPlugApiClient($conf);
            break;
        }
      }
      return $devices;
    }

    public static function guidv4()
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
}
 ?>
