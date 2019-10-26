<?php
namespace KKPA\Clients;

class KKPAPlugApiClient extends KKPADeviceApiClient
{
  public function setRelayState($state)
  {
    $state = boolval($state);
    if ($state) $state = 1; else $state = 0;
    $request_arr = array("system" => array("set_relay_state" => array("state" => $state)));
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
    $sysinfo = $this->getSysInfo("relay_state");
    return $sysinfo['relay_state'];
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
    if ($this->is_featured('LED'))
    {
      $sysinfo = $this->getSysInfo("led_off");
      return ($sysinfo['led_off']==0);
    } else {
      return null;
    }

  }

  public function getRealTime()
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

  public function getStats()
  {
    return $this->getGenericStats("emeter");
    $return = array();
    if ($this->is_featured('ENE'))
    {
      $date_from = strtotime('-30 days');
      for ($i=0;strtotime('First day of '.date('F Y',strtotime('+'.$i.' month',$date_from)))<=time();$i++)
      {
        $date = strtotime('First day of '.date('F Y',strtotime('+'.$i.' month',$date_from)));
        $month = intval(date('n',$date));
        $year = intval(date('Y',$date));
        $request_arr = array("emeter" => array("get_daystat" => array("year"=>$year,"month"=>$month)));
        $data = $this->send($request_arr);
        $day_list = $data["day_list"];
        foreach($day_list as $day_data)
        {
            $return[] = self::uniformizeRealTime($day_data,'energy','energy_wh',1);
        }
      }
    }
    return $return;
  }

  public function getMonthStats($i_year,$i_month)
  {
    $year = intval($i_year);
    $month = intval($i_month);
    if ($this->is_featured('ENE'))
    {
      $request_arr = array("emeter" => array("get_monthstat" => array("year"=>$year)));
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

  public function is_featured($feature)
  {
    if ($feature=='LED')
      return true;
    return (strpos($this->getVariable('feature',''),$feature)!==false);
  }
}
?>
