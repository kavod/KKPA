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

  private $children;

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

  public function getChildren()
  {
    $client = (is_null($this->client)) ? $this : $this->client;
    if ($this->has_children())
    {
      if (is_null($this->children))
      {
        $conf = array(
          "cloud" => $client->cloud,
          "local_ip" => $this->local_ip,
          "local_port" => $this->local_port,
          "token" => $client->token,
          "uuid" => $client->uuid,
          "username" => $client->getVariable('username',''),
          "password" => $client->getVariable('password',''),
          "base_uri" => $client->getVariable('base_uri',TPLINK_BASE_URI),
          "deviceId" => $this->getVariable('deviceId','')
        );
        $children = array();
        foreach($this->getVariable('children',array()) as $child)
        {
          $children[] = new KKPASlotPlugApiClient($conf,$child['id'],$client=$client);
        }
        $this->children = $children;
      }
      return $this->children;
    }
    return array();
  }

  public function getRealTime()
  {
    if ($this->is_featured('ENE'))
    {
      if (is_null($this->child_id))
      {
        $realtime = array('voltage'=>0.0,'current'=>0.0,'power'=>0.0,'total'=>0.0);
        foreach($this->getChildren() as $child)
        {
          $child_realtime = $child->getRealTime();
          $realtime['voltage'] = $child_realtime['voltage'];
          $realtime['current'] += $child_realtime['current'];
          $realtime['power'] += $child_realtime['power'];
          $realtime['total'] += $child_realtime['total'];
        }
        return $realtime;
      } else {
        return parent::getRealTime();
      }
    }
    return array();
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
