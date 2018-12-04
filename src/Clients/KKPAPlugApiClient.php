<?php
namespace KKPA\Clients;

class KKPAPlugApiClient extends KKPAApiClient
{
  protected $deviceId;
  public function __construct($config = array())
  {
    if(!isset($config["deviceId"]))
    {
      throw new Exception("DeviceId required");
    }
    parent::__construct($config);
    $this->deviceId = $config['deviceId'];
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
    $requestData = json_encode(array("system" => array("get_sysinfo" => array())));
    $param = json_encode(
      array(
        "method"=>"passthrough",
        "params"=>array(
          "deviceId"=>$this->deviceId,
          "requestData"=>$requestData
        )
      )
    );
    $responseData = json_decode($this->api("",'POST',$param)['responseData'],true);
    $system = $responseData['system']['get_sysinfo'];

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
      "hw_ver"
    );

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

  public function setRelayState($state)
  {
    $state = boolval($state);
    if ($state) $state = 1; else $state = 0;
    $requestData = json_encode(array("system" => array("set_relay_state" => array("state" => $state))));
    $param = json_encode(
      array(
        "method"=>"passthrough",
        "params"=>array(
          "deviceId"=>$this->deviceId,
          "requestData"=>$requestData
        )
      )
    );
    $this->api("",'POST',$param);
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

  public function getRealTime()
  {
    $requestData = json_encode(array("emeter" => array("get_realtime" => NULL)));
    $param = json_encode(
      array(
        "method"=>"passthrough",
        "params"=>array(
          "deviceId"=>$this->deviceId,
          "requestData"=>$requestData
        )
      )
    );
    $responseData = json_decode($this->api("",'POST',$param)['responseData'],true);
    if (array_key_exists('get_realtime',$responseData['emeter'])) {
      return $responseData['emeter']['get_realtime'];
    } else {
      return array();
    }
  }

  public function toString()
  {
    $array = parent::toArray();
    $array['deviceId'] = $this->deviceId;
    return print_r($array,true);
  }
}
?>
