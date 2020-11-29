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

class KKPADeviceApiClient extends KKPAApiClient
{
  protected $deviceId;
  protected $child_id;
  public function __construct($conf = array(),$child_id=null)
  {
    $config = array_merge(array(),$conf);
    if($this->cloud && !array_key_exists('deviceId',$config))
    {
      throw new KKPADeviceException("DeviceId required");
    }
    parent::__construct($config);
    if (array_key_exists('deviceId',$config))
      $this->deviceId = $config['deviceId'];
    $this->child_id = $child_id;
    $this->getSysInfo();
  }

  public function getType()
  {
    return self::readType($this->conf);
  }

  public function getModel()
  {
    return substr($this->getVariable('model'),0,5);
  }

  public function is_model($model)
  {
    return (strpos($this->getModel(),$model)!==false);
  }

  public function getSysInfo($info=NULL)
  {
    if (isset($info))
    {
      if(is_string($info))
        $info = array($info);
      if(is_array($info))
      {
        foreach($info as $element)
        {
          if (!is_string($element))
            throw new Exception("Info must be string or array of strings");
        }
      } else
      {
        throw new Exception("Info must be string or array of strings");
      }
    }
    $requestData_arr = self::REQ_SYSINFO;
    $system = $this->send($requestData_arr);

    $sys_to_conf = array(
      "sw_ver",
      "dev_name",
      "alias",
      "type",
      "model",
      "mac",
      "deviceId",
      "hwId",
      "fwId",
      "oemId",
      "hw_ver",
      "rssi",
      "led_off",
      "feature",
      "children",
      "is_dimmable",
      "is_color",
      "is_variable_color_temp"
    );
    $system = self::uniformizeSysinfo($system);

    foreach($system as $key => $value)
    {
      if(in_array($key,$sys_to_conf))
      {
        $this->setVariable($key,$value);
      }
    }

    if (!isset($info))
    {
      return $system;
    } else
    {
      $result = array();
      foreach($system as $key => $value)
      {
        if(in_array($key,$info))
        {
          $result[$key] = $value;
        }
      }
      return $result;
    }
  }

  public function getGenericStats($key)
  {
    $return = array();
    if ($this->is_featured('ENE'))
    {
      $date_from = strtotime('-30 days');
      for ($i=0;strtotime('First day of '.date('F Y',strtotime('+'.$i.' month',$date_from)))<=time();$i++)
      {
        $date = strtotime('First day of '.date('F Y',strtotime('+'.$i.' month',$date_from)));
        $month = intval(date('n',$date));
        $year = intval(date('Y',$date));
        $request_arr = array($key => array("get_daystat" => array("year"=>$year,"month"=>$month)));
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

  public function getDayStats($i_year,$i_month,$i_day)
  {
    $year = intval($i_year);
    $month = intval($i_month);
    $day = intval($i_day);
    if ($this->is_featured('ENE'))
    {
      $result = $this->getStats();
      foreach($result as $day_data)
      {
        if (intval($day_data['year']) == $year && intval($day_data['month']) == $month && intval($day_data['day']) == $day)
          return self::uniformizeRealTime($day_data,'energy','energy_wh',1);
      }
    }
    return array('year'=>$year, 'month' =>$month, 'day'=>$day,"energy"=>floatval(0));
  }

  public function getTodayStats()
  {
    $year = intval(date('Y'));
    $month = intval(date('n'));
    $day = intval(date('j'));
    if ($this->getType('IOT.SMARTPLUGSWITCH'))
    {
      if ($this->is_featured('ENE'))
      {
        $result = $this->getStats();
        foreach($result as $day_data)
        {
          if (intval($day_data['year']) == $year && intval($day_data['month']) == $month && intval($day_data['day']) == $day)
            return $day_data;
        }
      }
    }
    return array('year'=>$year, 'month' =>$month, 'day'=>$day,"energy"=>floatval(0));
  }

  public function getXDaysStats($nb_days)
  {
    if ($this->is_featured('ENE'))
    {
      $result = $this->getStats();
      $energy = floatval(0);
      $date_from = strtotime('-'.$nb_days.' days');
      foreach($result as $day_data)
      {
        $str_date = $day_data['month']."-".$day_data['day']."-".$day_data['year'];
        if (\DateTime::createFromFormat('m-d-Y', $str_date)->getTimestamp() > $date_from)
          $energy += $day_data['energy'];
      }
      return array("energy"=>floatval($energy));
    }
    return array("energy"=>floatval(0));
  }

  public function get7DaysStats()
  {
    return $this->getXDaysStats(7);
  }

  public function get30DaysStats()
  {
    return $this->getXDaysStats(30);
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

  static protected function uniformizeSysinfo($sysinfo)
  {
    $sysinfo['type'] = self::readType($sysinfo);
    unset($sysinfo['deviceType']);
    unset($sysinfo['mic_type']);

    $sysinfo['dev_name'] = self::getAltAttr(
      $sysinfo,
      array('dev_name','description'),
      ''
    );
    unset($sysinfo['description']);

    $sysinfo['mac'] = self::getAltAttr(
      $sysinfo,
      array('mac','mic_mac'),
      ''
    );
    unset($sysinfo['mic_mac']);

    $sysinfo['fwId'] = self::getAltAttr(
      $sysinfo,
      array('fwId'),
      ''
    );

    $sysinfo['longitude'] = self::getAltAttr(
      $sysinfo,
      array('longitude','longitude_i'),
      0
    );
    unset($sysinfo['longitude_i']);

    $sysinfo['latitude'] = self::getAltAttr(
      $sysinfo,
      array('latitude','latitude_i'),
      0
    );
    unset($sysinfo['latitude_i']);

    return $sysinfo;
  }

  public function toString()
  {
    $array = parent::toArray();
    $array['deviceId'] = $this->deviceId;
    return print_r($array,true);
  }

  public function getLedState()
  {

  }
  
  public function getAllIds()
  {
    $result = array();
    $children = $this->getVariable('children',array());
    foreach($children as $child)
    {
      $result[] = $child['id'];
    }
    if (count($result)>1)
      return $result;
    else {
      return null;
    }
  }

  // Network functions (Cloud & Local)
  protected function send($request_arr,$child_ids=null)
  {
    $child_ids = (is_null($child_ids)) ? $this->child_id : $child_ids;
    if (!is_null($child_ids))
    {
      $context_arr = array("child_ids" => self::translate_single_id($child_ids), "source" => "Kasa_Android");
      $request_arr['context'] = $context_arr;
      //$context_json = json_encode($request_arr);
    }
    return parent::send($request_arr);
  }

  public function has_children()
  {
    return is_array($this->getVariable('children',0));
  }
}
?>
