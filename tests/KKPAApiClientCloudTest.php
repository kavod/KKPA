<?php

use PHPUnit\Framework\TestCase;

if (!defined("DELAY_BEFORE_STATE"))
  define('DELAY_BEFORE_STATE',1);

require_once (__ROOT__.'/src/autoload.php');
require_once('KKPATestPrototype.php');

final class KKPAApiClientCloudTest extends \KKPATestPrototype
{
    public static function setUpBeforeClass()
    {
      require(__ROOT__.'/Examples/Config.php');
      self::assertRegExp('/.+/',$username);
      self::$conf = array(
        "username" => $username,
        "password" => $password,
        "cloud"    => true
      );
      parent::setUpBeforeClass();
    }

    public function testDebug(): void
    {
      $client = $this::instance(self::$conf);
      $deviceList = $client->getDeviceList();
      $last_request = $client->debug_last_request();
      $this->assertInternalType('array',$last_request);
      $this->assertArrayHasKey('request',$last_request);
      $this->assertArrayHasKey('result',$last_request);
      $this->assertArrayHasKey('errno',$last_request);
      $this->assertInternalType('string',$last_request['request']);
      $this->assertInternalType('string',$last_request['result']);
      $this->assertInternalType('int',$last_request['errno']);
      list($headers, $body) = explode("\r\n\r\n", $last_request['result']); // Cloud specific
      $decode = json_decode($body, TRUE);
      $this->assertFalse(!$decode);
      $device = $deviceList[0];
      $sysInfo = $device->getSysInfo();
      $last_request = $device->debug_last_request();
      $this->assertInternalType('array',$last_request);
      $this->assertArrayHasKey('request',$last_request);
      $this->assertArrayHasKey('result',$last_request);
      $this->assertArrayHasKey('errno',$last_request);
      $this->assertInternalType('string',$last_request['request']);
      $this->assertInternalType('string',$last_request['result']);
      $this->assertInternalType('int',$last_request['errno']);
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
}
?>
