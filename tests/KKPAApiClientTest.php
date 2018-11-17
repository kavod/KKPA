<?php

use PHPUnit\Framework\TestCase;

define('DELAY_BEFORE_STATE',1);

require_once (__ROOT__.'/src/autoload.php');

final class KKPAApiClientTest extends TestCase
{
    protected static $conf;

    public static function setUpBeforeClass()
    {
      require(__ROOT__.'/Examples/Config.php');

      self::$conf = array(
        "username" => $username,
        "password" => $password
      );
    }

    public function testInstance(): void
    {

        $client = $this::instance();
        $this->assertInstanceOf(
            KKPA\Clients\KKPAApiClient::class,
            $client
        );
    }

    public function testMakeRequest(): void
    {
      $client = $this::instance();
      $this->assertEquals(
          $client->makeRequest('https://jsonplaceholder.typicode.com/todos/1'),
          array(
            "userId"    => 1,
            "id"        => 1,
            "title"     => "delectus aut autem",
            "completed" => NULL
          )
        );
    }

    public function testAuth1(): void
    {
      $client = $this::instance();
      $conf = array(
        "method" => "login",
        "params" => array(
          "appType" => "Kasa_Android",
          "cloudPassword" => self::$conf["password"],
          "cloudUserName" => self::$conf["username"],
          "terminalUUID" => $client->guidv4()
        )
      );
      $this->assertEquals(
          $client->makeRequest('https://wap.tplinkcloud.com','POST',json_encode($conf))['error_code'],
          0
        );
    }

    public function testAuth2(): void
    {
      $client = $this::instance(self::$conf);

      $this->assertRegExp(
          '/[\d\w]{8}-[\d\w]{23}/',
          $client->getAccessToken()
        );
    }

    public function testApi1(): void
    {
      $client = $this::instance(self::$conf);
      $deviceList = $client->api("",'POST',json_encode(array("method" => "getDeviceList")));
      $this->assertInternalType('array',$deviceList);
    }

    public function testGetDeviceList(): void
    {
      $client = $this::instance(self::$conf);
      $deviceList = $client->getDeviceList();
      $this->assertInternalType('array',$deviceList);
      if (count($deviceList)>0)
      {
        $this->assertInstanceOf(KKPA\Clients\KKPAPlugApiClient::class,$deviceList[0]);
      }
    }

    public function testApi2(): void
    {
      $client = $this::instance(self::$conf);
      $device = self::getDevice($client);
      $sysInfo = $device->getSysInfo();
      $this->assertInternalType("string",$sysInfo['alias']);
    }

    public function testSwitchOff(): void
    {
      $client = $this::instance(self::$conf);
      $device = self::getDevice($client);
      $device->switchOff();
      sleep(DELAY_BEFORE_STATE);
      $this->assertEquals(
        $device->getState(),
        0
      );
    }

    public function testSwitchOn(): void
    {
      $client = $this::instance(self::$conf);
      $device = self::getDevice($client);
      $device->switchOn();
      sleep(DELAY_BEFORE_STATE);
      $this->assertEquals(
        $device->getState(),
        1
      );
    }

    public function testGetRealTime():void
    {
      $client = $this::instance(self::$conf);
      $device = self::getDevice($client);
      $realTime = $device->getRealTime();
      $this->assertInternalType("int",$realTime['power_mw']);
    }

    public function instance($config = array()): KKPA\Clients\KKPAApiClient
    {
      $client = new KKPA\Clients\KKPAApiClient($config);
      return $client;
    }

    public function getDevice($client):KKPA\Clients\KKPAPlugApiClient
    {
      $deviceList = $client->getDeviceList();
      return $deviceList[0];
    }
}
?>
