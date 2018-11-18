<?php
namespace KKPA\Exceptions;
/**
* Exception thrown by Netatmo SDK
*/
class KKPASDKException extends \Exception
{
    public function __construct($code, $message)
    {
        parent::__construct($message, $code);
    }
}
?>
