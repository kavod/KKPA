<?php
namespace KKPA\Exceptions;
class KKPANotLoggedErrorType extends KKPAClientException
{
    function __construct($code, $message)
    {
        parent::__construct($code, $message, NOT_LOGGED_ERROR_TYPE);
    }
}
?>
