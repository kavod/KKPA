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
    if ($this->getType('IOT.SMARTPLUGSWITCH'))
    {
      if ($this->is_featured('ENE'))
      {
        $request_arr = array("emeter" => array("get_realtime" => NULL));
        $realtime = $this->send($request_arr);

        $realtime = self::uniformizeRealTime($realtime,'voltage','voltage_mv',1000);
        $realtime = self::uniformizeRealTime($realtime,'current','current_ma',1000);
        $realtime = self::uniformizeRealTime($realtime,'power','power_mw',1000);
        $realtime = self::uniformizeRealTime($realtime,'total','total_wh',1);

        return $realtime;
      }
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
    if ($feature=='TIM')
      return true;
    return false;
  }
}
?>
