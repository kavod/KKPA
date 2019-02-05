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
  public function __construct($config = array(),$transition_period=150)
  {
    parent::__construct($config);
    $this->setTransitionPeriod($transition_period);
  }

  public function getSysInfo($info=NULL)
  {
    $this->getLightDetails(false);
    return parent::getSysInfo($info);
  }

  public function setRelayState($state)
  {
    $state = boolval($state);
    $transition_period = $this->getTransitionPeriod();
    if ($state) $state = 1; else $state = 0;
    $request_arr = array(
      "smartlife.iot.smartbulb.lightingservice" => array(
        "transition_light_state" => array(
          "ignore_default" => 0,
          "mode" => "normal",
          "on_off" => $state,
          "transition_period" => $transition_period
        )
      )
    );
    $this->send($request_arr);
  }

  public function setBrightness($level)
  {
    if (!$this->getVariable('is_dimmable',false))
      throw KKPA\Exceptions\KKPADeviceException(994,"Device is not dimmable","Error");
    $request_arr = array(
      "smartlife.iot.smartbulb.lightingservice" => array(
        "transition_light_state" => array(
          "brightness" => $level,
          "transition_period" => $this->getTransitionPeriod()
        )
      )
    );
    $this->send($request_arr);
  }

  public function getBrightness()
  {
    $sysinfo = $this->getSysInfo();
    if ($sysinfo['is_dimmable'])
    {
      if($sysinfo['light_state']['on_off'])
        return $sysinfo['light_state']['brightness'];
      return $sysinfo['light_state']['dft_on_state']['brightness'];
    }
    return $sysinfo['light_state']['on_off']*100;

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

  public function setTransitionPeriod($time)
  {
    $this->setVariable('transition_period',$time);
  }

  public function getTransitionPeriod()
  {
    return $this->getVariable('transition_period');
  }

  public function getRealTime()
  {
    $cur_wattage = $this->getVariable("wattage",0);
    if ($cur_wattage==0)
      $cur_wattage = $this->getLightDetails($force=false)['wattage'];
    $power = doubleval($this->getState()*$cur_wattage*$this->getBrightness()/100);
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
