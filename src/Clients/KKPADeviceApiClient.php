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
  public function __construct($config = array())
  {
    if($this->cloud && !array_key_exists('deviceId',$config))
    {
      throw new KKPADeviceException("DeviceId required");
    }
    parent::__construct($config);
    if (array_key_exists('deviceId',$config))
      $this->deviceId = $config['deviceId'];
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
}
?>
