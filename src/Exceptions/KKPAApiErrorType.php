<?php
namespace KKPA\Exceptions;
class KKPAApiErrorType extends KKPAClientException
{
    public $http_code;
    public $http_message;
    public $result;
    function __construct($code, $message, $result)
    {
        $this->http_code = $code;
        $this->http_message = $message;
        $this->result = $result;
        if(isset($result["error_code"]))
        {
            parent::__construct($result["error_code"], $result["msg"], API_ERROR_TYPE);
        }
        else
        {
            parent::__construct($code, $message, API_ERROR_TYPE);
        }
    }
}
?>
