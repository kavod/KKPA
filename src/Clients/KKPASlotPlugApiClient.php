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
    $conf = parent::getSysInfo();
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
      throw new KKPAClientException(KKPA_CHILD_ID_NOT_FOUND,"Child id $child_id not found for ".$conf['deviceId'],"error");
    $this->setVariable('child_id',$child_id);
    $this->child_id = $child_id;
    //$this->child_id = (strpos($child_id,$conf['id'])===0) ? $child_id : $conf['id'].$child_id;

    //$this->child_id = $child_id;
  }

  public function has_children()
  {
    return false;
  }

  public function getSysInfo($info=NULL)
  {
    if (is_array($info))
      $info[] = 'children';
    elseif(is_string($info))
      $info = array($info,'children');

    $sysinfo = parent::getSysInfo($info);
    if (array_key_exists('children',$sysinfo))
    {
      foreach($sysinfo['children'] as $child)
      {
        if ($child['id']==$this->child_id)
        {
          $sysinfo['alias'] = $child['alias'];
          $this->setVariable('alias',$sysinfo['alias']);
          break;
        }
      }
    }
    return $sysinfo;
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
