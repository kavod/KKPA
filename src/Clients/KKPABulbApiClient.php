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
    if (!$this->is_featured('DIM'))
      throw new KKPADeviceException(994,"Device is not dimmable","Error");
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
    if ($this->is_featured('DIM'))
    {
      if($sysinfo['light_state']['on_off'])
        return $sysinfo['light_state']['brightness'];
      else
        return $sysinfo['light_state']['dft_on_state']['brightness'];
    }
    return $sysinfo['light_state']['on_off']*100;

  }

  public function setHue($hue)
  {
    if (!$this->getVariable('is_color',false))
      throw new KKPADeviceException(
        KKPA_NO_FEAT_COLOR,
        "Device has not color changing",
        "Error"
      );
    $request_arr = array(
      "smartlife.iot.smartbulb.lightingservice" => array(
        "transition_light_state" => array(
          "hue" => $hue,
          "transition_period" => $this->getTransitionPeriod()
        )
      )
    );
    $this->send($request_arr);
  }

  public function getHue()
  {
    $sysinfo = $this->getSysInfo();
    if ($sysinfo['is_color'])
    {
      if($sysinfo['light_state']['on_off'])
        return $sysinfo['light_state']['hue'];
      else
        return $sysinfo['light_state']['dft_on_state']['hue'];
    }
    return 0;
  }

  public function setLightState($color_temp=null,$hue=null,$saturation=null,$brightness=null)
  {
    $color_change = !(is_null($hue)&&is_null($saturation));
    $temp_change = !is_null($color_temp);
    $brightness_change = !is_null($brightness);

    if ($color_change && !$this->is_featured('COL'))
      throw new KKPADeviceException(
        KKPA_NO_FEAT_COLOR,
        "Device ".$this->getModel()." has not color changing",
        "Error"
      );

    if ($brightness_change && !$this->is_featured('DIM'))
      throw new KKPADeviceException(994,"Device is not dimmable","Error");

    if ($temp_change && !$this->is_featured('TMP'))
      throw new KKPADeviceException(992,"Device has not temperature changing","Error");

    $light_state = array(
      "transition_period" => $this->getTransitionPeriod()
    );
    if (!is_null($hue))
      $light_state['hue'] = $hue;
    if (!is_null($saturation))
      $light_state['saturation'] = $saturation;
    if (!is_null($color_temp))
      $light_state['color_temp'] = $color_temp;
    if (!is_null($brightness))
      $light_state['brightness'] = $brightness;

    $request_arr = array(
      "smartlife.iot.smartbulb.lightingservice" => array(
        "transition_light_state" => $light_state
      )
    );
    $this->send($request_arr);
  }

  public function getLightState()
  {
    $sysinfo = $this->getSysInfo();
    if($sysinfo['light_state']['on_off']==1)
      $state = $sysinfo['light_state'];
    else
      $state = $sysinfo['light_state']['dft_on_state'];

    $result = array();
    if ($this->is_featured('DIM'))
      $result['brightness'] = $state['brightness'];
    if ($this->is_featured('TMP'))
      $result['color_temp'] = $state['color_temp'];
    if ($this->is_featured('COL'))
    {
      $result['hue'] = $state['hue'];
      $result['saturation'] = $state['saturation'];
    }
    return $result;
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
    $sysinfo = $this->getSysInfo("light_state");
    return $sysinfo['light_state']['on_off'];
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
    $request_arr = array("smartlife.iot.common.emeter" => array("get_realtime" => NULL));
    $realtime = $this->send($request_arr);

    $realtime = self::uniformizeRealTime($realtime,'power','power_mw',1000);

    return $realtime;
  }

  public function getStats()
  {
    $return = array();
    if ($this->is_featured('ENE'))
    {
      $date_from = mktime(0,0,0,intval(date('n')),intval(date('j')-30));
      for ($i=0;mktime(0,0,0,date('n',$date_from)+$i,1,date('Y',$date_from))<=mktime();$i++)
      {
        $date = mktime(0,0,0,date('n',$date_from)+$i,1,date('Y',$date_from));
        $month = intval(date('n',$date));
        $year = intval(date('Y',$date));
        $request_arr = array("smartlife.iot.common.emeter" => array("get_daystat" => array("year"=>$year,"month"=>$month)));
        $data = $this->send($request_arr);
        $day_list = $data["day_list"];
        foreach($day_list as $day_data)
        {
            $return[] = self::uniformizeRealTime($day_data,'energy','energy_wh',1);
            //return floatval($day_data['energy_wh']/1000);
        }
      }
    }
    return $return;
  }

  public function getDayStats($i_year,$i_month,$i_day)
  {
    $year = intval($i_year);
    $month = intval($i_month);
    $day = intval($i_day);
    $request_arr = array("smartlife.iot.common.emeter" => array("get_daystat" => array("year"=>$year,"month"=>$month)));
    $data = $this->send($request_arr);
    $day_list = $data["day_list"];
    foreach($day_list as $day_data)
    {
      if (intval($day_data['day']) == $day)
        return self::uniformizeRealTime($day_data,'energy','energy_wh',1);
        //return floatval($day_data['energy_wh']/1000);
    }
    return array('year'=>$year, 'month' =>$month, 'day'=>$day,"energy"=>floatval(0));
  }

  public function getTodayStats()
  {
    $year = intval(date('Y'));
    $month = intval(date('n'));
    $day = intval(date('j'));
    if ($this->is_featured('ENE'))
    {
      $result = $this->getStats();
      foreach($result as $day_data)
      {
        if (intval($day_data['year']) == $year && intval($day_data['month']) == $month && intval($day_data['day']) == $day)
          return self::uniformizeRealTime($day_data,'energy','energy_wh',1);
      }
      return array('year'=>$year, 'month' =>$month, 'day'=>$day,"energy"=>floatval(0));
    }
  }

  public function getXDaysStats($nb_days)
  {
    if ($this->is_featured('ENE'))
    {
      $result = $this->getStats();
      $energy = floatval(0);
      $date_from = mktime(0,0,0,date('n'),date('j')-$nb_days);
      foreach($result as $day_data)
      {
        if (mktime(0,0,0,intval($day_data['month']),intval($day_data['day']),intval($day_data['year'])) >= $date_from)
          $energy += $day_data['energy'];
      }
      return array("energy"=>floatval($energy));
    }
  }

  public function get7DaysStats()
  {
    return $this->getXDaysStats(7);
  }

  public function get30DaysStats()
  {
    return $this->getXDaysStats(30);
  }

  public function getMonthStats($i_year,$i_month)
  {
    $year = intval($i_year);
    $month = intval($i_month);
    if ($this->is_featured('ENE'))
    {
      $request_arr = array("smartlife.iot.common.emeter" => array("get_monthstat" => array("year"=>$year)));
      $data = $this->send($request_arr);
      $month_list = $data["month_list"];
      foreach($month_list as $month_data)
      {
        if (intval($month_data['month']) == $month)
          return self::uniformizeRealTime($month_data,'energy','energy_wh',1);
      }
      return array('year'=>$year, 'month' =>$month,"energy"=>floatval(0));
    }
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
      return false;
    if ($feature=='ENE')
      return ($this->getModel() != 'LB100');
    if ($feature=='COL')
      return ($this->getVariable('is_color',0)==1);
    if ($feature=='DIM')
      return ($this->getVariable('is_dimmable',0)==1);
    if ($feature=='TMP')
      return ($this->getVariable('is_variable_color_temp',0)==1);
    if ($feature=='LED')
      return false;
    return false;
  }
}
?>
