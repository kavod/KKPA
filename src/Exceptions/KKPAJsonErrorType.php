<?php
namespace KKPA\Exceptions;
class KKPAJsonErrorType extends KKPAClientException
{
    function __construct($code, $message)
    {
        parent::__construct($code, $message, JSON_ERROR_TYPE);
    }
}
?>
