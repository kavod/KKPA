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

class KKPAMultiPlugApiClient extends KKPAPlugApiClient
{
  /*public function __construct($config = array())
  {
    parent::__construct($config);
  }*/

  public function getState()
  {
    if ($this->is_featured('TIM'))
    {
      $on = 0;
      $off = 0;
      $sysinfo = $this->getSysInfo("children");
      foreach($sysinfo['children'] as $child)
      {
        if (is_null($this->child_id) || $child['id']==$this->child_id)
        {
          if ($child['state'])
            $on++;
          else
            $off++;
        }
      }
      if ($on*$off)
        return -1;
      else {
        return ($on>0) ? 1 : 0;
      }
    }
    throw new KKPAClientException(994,"No child with ID ".$id,"Error");
  }

  public function is_featured($feature)
  {
    if ($feature=='LED')
      return true;
    if ($feature=='MUL')
      return true;
    return (strpos($this->getVariable('feature',''),$feature)!==false);
  }
}
?>
