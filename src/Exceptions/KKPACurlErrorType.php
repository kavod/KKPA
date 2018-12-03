<?php
namespace KKPA\Exceptions;
class KKPACurlErrorType extends KKPAClientException
{
    function __construct($code, $message)
    {
        parent::__construct($code, $message, KKPA_CURL_ERROR_TYPE);
    }
}
?>
