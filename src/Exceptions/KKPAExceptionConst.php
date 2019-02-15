<?php
namespace KKPA\Exceptions;
define('KKPA_CURL_ERROR_TYPE', 0);
define('KKPA_API_ERROR_TYPE',1);//error return from api
define('KKPA_INTERNAL_ERROR_TYPE', 2); //error because internal state is not consistent
define('KKPA_JSON_ERROR_TYPE',3);
define('KKPA_NOT_LOGGED_ERROR_TYPE', 4); //unable to get access token
define('KKPA_TIMEOUT', -20002); // {"error_code":-20002,"msg": "Request timeout"}
define('KKPA_PARAM_NOT_EXIST', -20104); //{"error_code": -20104,"msg": "Parameter doesn't exist"}
define('KKPA_DEVICE_OFFLINE', -20571); // {"error_code":-20571,"msg":"Device is offline"}
define('KKPA_NOT_BINDED', -20580); // {"error_code": -20580,"msg": "Account is not binded to the device"}

define('KKPA_NO_ROUTE_TO_HOST', 992); // {"error_code": -20580,"msg": "Account is not binded to the device"}
 ?>
