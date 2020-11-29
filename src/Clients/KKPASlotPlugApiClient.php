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

class KKPASlotPlugApiClient extends KKPAMultiPlugApiClient
{
  public function __construct($config = array(),$child_id=null)
  {
    if (is_null($child_id))
      throw new KKPAClientException(KKPA_CHILD_ID_MANDATORY,"Child id mandatory","error");
    parent::__construct($config,null);
    $conf = $this->getSysInfo();
    $found = false;
    foreach($conf['children'] as $child)
    {
      if ($child['id']==$child_id)
      {
        $found = true;
        break;
      }
      if ($child['id']==$conf['deviceId'].$child_id)
      {
        $child_id = $conf['deviceId'].$child_id;
        $found = true;
        break;
      }
    }
    if (!$found)
      throw new KKPAClientException(KKPA_CHILD_ID_NOT_FOUND,"Child id $child_id not found","error");
    $this->child_id = $child_id;
    //$this->child_id = (strpos($child_id,$conf['id'])===0) ? $child_id : $conf['id'].$child_id;

    //$this->child_id = $child_id;
  }

  public function has_children()
  {
    return false;
  }

  public function is_featured($feature)
  {
    if ($feature=='TIM')
      return true;
    if ($feature=='ENE')
      return true;
    if ($feature=='LED')
      return false;
    if ($feature=='MUL')
      return false;
    return (strpos($this->getVariable('feature',''),$feature)!==false);
  }
}
?>
