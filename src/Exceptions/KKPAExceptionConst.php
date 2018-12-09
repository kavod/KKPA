<?php
namespace KKPA\Exceptions;
define('KKPA_CURL_ERROR_TYPE', 0);
define('KKPA_API_ERROR_TYPE',1);//error return from api
define('KKPA_INTERNAL_ERROR_TYPE', 2); //error because internal state is not consistent
define('KKPA_JSON_ERROR_TYPE',3);
define('KKPA_NOT_LOGGED_ERROR_TYPE', 4); //unable to get access token
define('KKPA_DEVICE_OFFLINE', -20571); // {"error_code":-20571,"msg":"Device is offline"}
define('KKPA_TIMEOUT', -20002); // {"error_code":-20002,"msg": "Request timeout"}
 ?>
