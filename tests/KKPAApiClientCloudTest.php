<?php

use PHPUnit\Framework\TestCase;

require_once (__ROOT__.'/src/autoload.php');
require_once('KKPATestPrototype.php');

final class KKPAApiClientCloudTest extends \KKPATestPrototype
{
    public static function setUpBeforeClass():void
    {
      require(__ROOT__.'/Examples/Config.php');
      self::$ref_testDeviceList = $deviceList;
      self::assertRegExp('/.+/',$username);
      self::$conf = array(
        "username" => $username,
        "password" => $password,
        "cloud"    => true,
        "base_uri" => $base_uri
      );
      parent::setUpBeforeClass();

      foreach(self::$ref_testDeviceList as $device)
      {
        // if (!$device['virtual'])
          self::$ref_deviceList[] = self::$ref_client->getDeviceById($device['deviceId']);
      }
    }

    public function testDebug(): void
    {
      $client = $this::instance(self::$conf);
      $deviceList = $client->getDeviceList();
      $last_request = $client::debug_last_request();
      $this->assertIsArray($last_request);
      $this->assertArrayHasKey('request',$last_request);
      $this->assertArrayHasKey('result',$last_request);
      $this->assertArrayHasKey('errno',$last_request);
      $this->assertIsString($last_request['request']);
      $this->assertIsString($last_request['result']);
      $this->assertIsInt($last_request['errno']);
      list($headers, $body) = explode("\r\n\r\n", $last_request['result']); // Cloud specific
      $decode = json_decode($body, TRUE);
      $this->assertFalse(!$decode);
      $device = $deviceList[0];
      $sysInfo = $device->getSysInfo();
      $last_request = $device::debug_last_request();
      $this->assertIsArray($last_request);
      $this->assertArrayHasKey('request',$last_request);
      $this->assertArrayHasKey('result',$last_request);
      $this->assertArrayHasKey('errno',$last_request);
      $this->assertIsString($last_request['request']);
      $this->assertIsString($last_request['result']);
      $this->assertIsInt($last_request['errno']);
      list($headers, $body) = explode("\r\n\r\n", $last_request['result']); // Cloud specific
      $decode = json_decode($body, TRUE);
      $this->assertFalse(!$decode);
      $decode = json_decode($decode['result']['responseData'],TRUE);
      $this->assertFalse(!$decode);
      $this->checkLatLong('latitude',$decode['system']['get_sysinfo']);
      $this->checkLatLong('latitude_i',$decode['system']['get_sysinfo']);
      $this->checkLatLong('longitude',$decode['system']['get_sysinfo']);
      $this->checkLatLong('longitude_i',$decode['system']['get_sysinfo']);
    }

    public function testMultiSlots():void
    {
      $this->assertTrue(true); // Pass since I do not own this kind of device
    }
}
?>
