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

class KKPASlotPlugApiClient extends KKPAMultiPlugApiClient
{
  public function __construct($config = array(),$child_id=null)
  {
    if (is_null($child_id))
      throw new KKPAClientException(KKPA_CHILD_ID_MANDATORY,"Child id mandatory","error");
    parent::__construct($config,null);
    $this->child_id = $child_id;
  }

  public function has_children()
  {
    return false;
  }
/*
  public function setRelayState($state)
  {
    parent::setSlotRelayState($state,array($this->child_id));
  }

  public function getState()
  {
    return parent::getState(array($this->child_id));
  }

  public function getRealTime()
  {
    if ($this->is_featured('ENE'))
    {
      return parent::getSlotRealTime(array($this->child_id));
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
  }*/

  public function is_featured($feature)
  {
    if ($feature=='TIM')
      return true;
    if ($feature=='ENE')
      return true;
    if ($feature=='LED')
      return false;
    if ($feature=='MUL')
      return false;
    return (strpos($this->getVariable('feature',''),$feature)!==false);
  }
}
?>
