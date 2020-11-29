<?php

use PHPUnit\Framework\TestCase;

require_once (__ROOT__.'/src/autoload.php');
require_once('KKPATestPrototype.php');

final class KKPAApiClientLocalTest extends \KKPATestPrototype
{
    public static function setUpBeforeClass():void
    {
      require(__ROOT__.'/Examples/Config.php');
      self::$ref_testDeviceList = $deviceList;

      self::$conf = array(
        "cloud" => false,
        "username" => "niouf",
        "password" => "niorf"
      );
      parent::setUpBeforeClass();

      foreach(self::$ref_testDeviceList as $device)
      {
        self::$ref_deviceList[] = self::$ref_client->getDeviceByIp($device['ip']);
        if (array_key_exists('children',$device))
        {
          foreach($device['children'] as $child_id)
          {
            self::$ref_deviceList[] = self::$ref_client->getDeviceByIp($device['ip'],$port=9999,$child_id);
          }
        }
      }
    }

    public function testGetDeviceByIp()
    {
      foreach(self::$ref_testDeviceList as $device)
      {
        $dev = self::$ref_client->getDeviceByIp($device['ip']);
        $this->assertEquals(
          $device['model'],
          $dev->getModel()
        );
        $this->assertEquals(
          $device['deviceId'],
          $dev->getVariable('deviceId','')
        );
        if (array_key_exists('children',$device))
        {
          foreach($device['children'] as $child_id)
          {
            $dev = self::$ref_client->getDeviceByIp($device['ip'],$port=9999,$child_id);
            $this->assertEquals(
              $device['model'],
              $dev->getModel()
            );
            $this->assertEquals(
              $device['deviceId'],
              $dev->getVariable('deviceId','')
            );
          }
        }
      }
    }

}
?>
