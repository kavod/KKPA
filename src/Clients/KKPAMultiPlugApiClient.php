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

class KKPAMultiPlugApiClient extends KKPADeviceApiClient
{
  protected $deviceId;
  public $children_obj = array();
  /*public function __construct($config = array())
  {
    parent::__construct($config);
  }*/

  protected function sendSlots($request,$id)
  {
    $context_arr = array("child_ids" => array($id), "source" => "Kasa_Android");
    $request['context'] = $context_arr;
    $context_json = json_encode($request);
    return $this->send($request);
  }

  public function setSlotRelayState($state,$ids)
  {
    $state = boolval($state);
    if ($state) $state = 1; else $state = 0;
    $request_arr = array("system" => array("set_relay_state" => array("state" => $state)));
    foreach($ids as $id)
    {
      $this->sendSlots($request_arr,$id);
    }
  }

  public function switchSlotOn($ids)
  {
    $this->setSlotRelayState(1,$ids);
  }

  public function switchSlotOff($ids)
  {
    $this->setSlotRelayState(0,$ids);
  }

  public function setRelayState($state)
  {
    $ids = $this->getAllIds();
    $this->setSlotRelayState($ids);
  }

  public function switchOn()
  {
    $this->setSlotRelayState(1,$this->getAllIds());
  }

  public function switchOff()
  {
    $this->setSlotRelayState(0,$this->getAllIds());
  }

  public function getSlotState($id)
  {
    if ($this->is_featured('TIM'))
    {
      $sysinfo = $this->getSysInfo("children");
      foreach($sysinfo['children'] as $child)
      {
        if ($child['id'] == $id)
          return $child['state'];
      }
    }
    throw new KKPAClientException(994,"No child with ID ".$id,"Error");
  }

  public function getState()
  {
    if ($this->is_featured('TIM'))
    {
      $on = 0;
      $off = 0;
      $sysinfo = $this->getSysInfo("children");
      foreach($sysinfo['children'] as $child)
      {
        if ($child['state'])
          $on++;
        else
          $off++;
      }
      if ($on*$off)
        return -1;
      else {
        return ($on>1) ? 1 : 0;
      }
    }
    throw new KKPAClientException(994,"No child with ID ".$id,"Error");
  }

  protected function getAllIds()
  {
    $result = array();
    $children = $this->getVariable('children');
    foreach($children as $child)
    {
      $result[] = /*$this->deviceId.*/$child['id'];
    }
    return $result;
  }

  public function setLedState($state)
  {
    $state = boolval($state);
    if (!$state) $state = 1; else $state = 0;
    $request_arr = array("system" => array("set_led_off" => array("off" => $state)));
    $this->send($request_arr);
  }

  public function setLedOn()
  {
    $this->setLedState(1);
  }

  public function setLedOff()
  {
    $this->setLedState(0);
  }

  public function getLedState()
  {
    if ($this->is_featured('TIM'))
    {
      $sysinfo = $this->getSysInfo("led_off");
      return ($sysinfo['led_off']==0);
    } else {
      return null;
    }

  }

  public function getRealTime()
  {
    $ids = $this->getAllIds();

    $realtime = array("power"=>0.0,"voltage"=>0.0,"current"=>0.0,"total"=>0.0);

    foreach($ids as $id)
    {
      $slot_realtime = $this->getSlotRealTime($id);
      $realtime['power'] += $slot_realtime['power'];
      $realtime['voltage'] += $slot_realtime['voltage'];
      $realtime['current'] += $slot_realtime['current'];
      $realtime['total'] += $slot_realtime['total'];
      if ($slot_realtime['err_code'] != 0)
        $realtime['err_code'] = $slot_realtime['err_code'];
    }
    return $realtime;
  }

  public function getSlotRealTime($id)
  {
    if ($this->is_featured('ENE'))
    {
      $sysinfo = $this->getVariable("children",array());
      foreach($sysinfo as $child)
      {
        if ($child['id'] == $id)
        {
          $request_arr = array("emeter" => array("get_realtime" => NULL));
          $realtime = $this->sendSlots($request_arr,$id);

          $realtime = self::uniformizeRealTime($realtime,'voltage','voltage_mv',1000);
          $realtime = self::uniformizeRealTime($realtime,'current','current_ma',1000);
          $realtime = self::uniformizeRealTime($realtime,'power','power_mw',1000);
          $realtime = self::uniformizeRealTime($realtime,'total','total_wh',1);

          return $realtime;
        }
      }
      throw new KKPAClientException(994,"No child with ID ".$id,"Error");
    }
  }

  protected static function uniformizeRealTime($realtime,$target,$source,$factor)
  {
    if (array_key_exists($source,$realtime))
    {
      $realtime[$target] = $realtime[$source]/$factor;
      unset($realtime[$source]);
    }
    if (!array_key_exists($target,$realtime))
      throw new KKPAApiErrorType(
        996,
        "Missing value: ".$target." in ".print_r($realtime,true),
        "Error"
      );
    $realtime[$target] = floatval($realtime[$target]);
    return $realtime;
  }

  public function is_featured($feature)
  {
    return (strpos($this->getVariable('feature',''),$feature)!==false);
  }
}
?>
