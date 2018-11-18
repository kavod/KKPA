<?php
namespace KKPA\Exceptions;
class KKPAInternalErrorType extends KKPAClientException
{
    function __construct($message)
    {
        parent::__construct(0, $message, INTERNAL_ERROR_TYPE);
    }
}
?>
