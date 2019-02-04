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

class KKPABulbApiClient extends KKPADeviceApiClient
{
  public function setRelayState($state)
  {
    $state = boolval($state);
    if ($state) $state = 1; else $state = 0;
    $request_arr = array(
      "smartlife.iot.smartbulb.lightingservice" => array(
        "transition_light_state" => array(
          "ignore_default" => 0,
          "mode" => "normal",
          "on_off" => $state,
          "transition_period" => 0
        )
      )
    );
    $this->send($request_arr);
  }

  public function switchOn()
  {
    $this->setRelayState(1);
  }

  public function switchOff()
  {
    $this->setRelayState(0);
  }

  public function getState()
  {
    if ($this->is_featured('TIM'))
    {
      $sysinfo = $this->getSysInfo("light_state");
      return $sysinfo['light_state']['on_off'];
    } else {
      return null;
    }
  }

  public function getRealTime()
  {
    $cur_wattage = $this->getVariable("wattage",0);
    if ($cur_wattage==0)
      $cur_wattage = $this->getLightDetails($force=false)['wattage'];
    $power = doubleval($this->getState()*$cur_wattage);
    return array("power"=>$power);
  }

  public function getLightDetails($force=false)
  {
    $cur_wattage = $this->getVariable("wattage",0);
    if($cur_wattage==0)
    {
      $request_arr = array("smartlife.iot.smartbulb.lightingservice" => array("get_light_details" => NULL));
      $realtime = $this->send($request_arr);

      $this->setVariable("wattage",0);

      return $realtime;
    }
    return array("wattage"=>$cur_wattage);
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
    if ($feature=='TIM')
      return true;
    if ($feature=='ENE')
      return true;
    return false;
  }
}
?>
