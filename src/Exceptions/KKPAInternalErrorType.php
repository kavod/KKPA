<?php
namespace KKPA\Exceptions;
class KKPAInternalErrorType extends KKPAClientException
{
    function __construct($message)
    {
        parent::__construct(0, $message, KKPA_INTERNAL_ERROR_TYPE);
    }
}
?>
