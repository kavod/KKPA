<?php

use PHPUnit\Framework\TestCase;

if (!defined("DELAY_BEFORE_STATE"))
  define('DELAY_BEFORE_STATE',1.5);

require_once (__ROOT__.'/src/autoload.php');

class KKPATestPrototype extends TestCase
{
    protected static $conf;
    protected static $ref_client;
    protected static $ref_deviceList;
    protected static $ref_testDeviceList = array();

    public static function setUpBeforeClass():void
    {
      self::$ref_deviceList = array();
      self::$ref_client = new KKPA\Clients\KKPAApiClient(self::$conf);
      //self::$ref_deviceList = self::$ref_client->getDeviceList(self::$conf);
    }

    public function instance($config = array()): KKPA\Clients\KKPAApiClient
    {
      //$client = new KKPA\Clients\KKPAApiClient($config);
      $client = clone self::$ref_client;
      return $client;
    }

    public function getDevice($client):KKPA\Clients\KKPADeviceApiClient
    {
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      if (count($deviceList)<1)
        print_r($client::debug_last_request());
      return $deviceList[0];
    }

    protected function checkLatLong($key,$arr)
    {
      if (array_key_exists($key,$arr))
      {
        $this->assertEquals(
          0,
          $arr[$key]
        );
      }
    }

    public function testInstance(): void
    {
        $client = $this::instance(self::$conf);
        $this->assertInstanceOf(
            KKPA\Clients\KKPAApiClient::class,
            $client
        );
        $device = $this::getDevice($client);
        $this->assertInstanceOf(
            KKPA\Clients\KKPADeviceApiClient::class,
            $device
        );
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      $found = array();
      $this->assertIsArray($deviceList);
      foreach($deviceList as $device)
      {
        //$this->assertFalse(in_array($device->getVariable('deviceId',''),$found)); //since MultiSlot
        $found[] = $device->getVariable('deviceId','');
        switch($device->getType())
        {
          case 'IOT.SMARTBULB':
            $this->assertInstanceOf(KKPA\Clients\KKPABulbApiClient::class,$device);
            break;

          case 'IOT.SMARTPLUGSWITCH':
            if (substr($device->getModel(),0,5)=="HS300")
            {
              $this->assertInstanceOf(KKPA\Clients\KKPAMultiPlugApiClient::class,$device);
            } else
            {
              $this->assertInstanceOf(KKPA\Clients\KKPAPlugApiClient::class,$device);
            }
            break;
          default:
            throw new \Exception("Unknown type");
        }
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
      $decode = json_decode($last_request['result'], TRUE);
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
      $decode = json_decode($last_request['result'], TRUE);
      $this->assertFalse(!$decode);
      $this->checkLatLong('latitude',$decode['system']['get_sysinfo']);
      $this->checkLatLong('latitude_i',$decode['system']['get_sysinfo']);
      $this->checkLatLong('longitude',$decode['system']['get_sysinfo']);
      $this->checkLatLong('longitude_i',$decode['system']['get_sysinfo']);
    }

    public function testToString(): void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        $string = $device->toString();
        $this->assertEquals(
          1,
          preg_match('/\[username\] => \*\*\*\*\*/',$string)
        );
        $this->assertEquals(
          1,
          preg_match('/\[password\] => \*\*\*\*\*/',$string)
        );
      }
    }

    public function testSysInfo(): void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        $sysInfo = $device->getSysInfo();
        $this->assertIsString($sysInfo['alias']);
        $this->assertIsString($sysInfo['dev_name']);
        if ($device->getModel()!='HS300')
          $this->assertGreaterThan(0,strlen($sysInfo['dev_name']));
        $this->assertIsInt($sysInfo['rssi']);
      }
      //print_r($device::debug_last_request());
    }

    public function testSwitchOnOff(): void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('TIM') || $device->is_featured('DIM'))
        {
          $device->switchOff();
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getState(),
            0
          );
          $device->switchOn();
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getState(),
            1
          );
          $device->switchOff();
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getState(),
            0
          );
        } else {
          $this->assertNull($device->getState());
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testBrightness(): void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->getType()=='IOT.SMARTBULB')
        {
          $device->switchOn();
          $device->setBrightness(100);
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getBrightness(),
            100
          );
          $device->setBrightness(50);
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getBrightness(),
            50
          );
          $device->setBrightness(100);
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getBrightness(),
            100
          );
          $device->switchOff();
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testHue(): void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->getType()=='IOT.SMARTBULB')
        {
          if(!$device->is_featured('COL'))
            continue;
          $device->switchOn();
          $device->setHue(0);
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getHue(),
            0
          );
          $device->setHue(180);
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getHue(),
            180
          );
          $device->setHue(270);
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getHue(),
            270
          );
          $device->switchOff();
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testLedOnOff(): void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      $this->assertIsArray($deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('LED'))
        {
          $device->setLedOff();
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getLedState(),
            0
          );
          $device->setLedOn();
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getLedState(),
            1
          );
          $device->setLedOff();
          sleep(DELAY_BEFORE_STATE);
          $this->assertEquals(
            $device->getLedState(),
            0
          );
        } else {
          $this->assertNull($device->getLedState());
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testGetRealTime():void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('ENE'))
        {
          $realTime = $device->getRealTime();
          $this->assertIsFloat($realTime['power']);//double?
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testGetDayStats():void
    {
      $client = $this::instance(self::$conf);
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('ENE'))
        {
          $return = $device->getDayStats(date("y"),date("n"),date("j"));
          $this->assertIsFloat($return['energy']);//double
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testGetTodayStats():void
    {
      $client = $this::instance(self::$conf);
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('ENE'))
        {
          $return = $device->getTodayStats();
          $this->assertIsFloat($return['energy']);//double
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testGet7DaysStats():void
    {
      $client = $this::instance(self::$conf);
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('ENE'))
        {
          $return = $device->get7DaysStats();
          $this->assertIsFloat($return['energy']);//double
        }
      }
    }

    public function testGet30DaysStats():void
    {
      $client = $this::instance(self::$conf);
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('ENE'))
        {
          $return = $device->get30DaysStats();
          $this->assertIsFloat($return['energy']);//double
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testGetMonthStats():void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->is_featured('ENE'))
        {
          $return = $device->getMonthStats(date("y"),date("n"));
          $this->assertIsFloat($return['energy']); //double
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testIsFeatured():void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        // if ($device->getType()=='IOT.SMARTPLUGSWITCH')
        // {
          switch(substr($device->getVariable('model',''),0,5))
          {
            case 'HS100':
            case 'HS105':
            case 'HS200':
            case 'HS220':
              $this->assertTrue($device->is_featured('TIM'));
              $this->assertFalse($device->is_featured('ENE'));
              $this->assertFalse($device->is_featured('DIM'));
              $this->assertFalse($device->is_featured('COL'));
              $this->assertFalse($device->is_featured('TMP'));
              break;
            case 'HS110':
              $this->assertTrue($device->is_featured('TIM'));
              $this->assertTrue($device->is_featured('ENE'));
              $this->assertFalse($device->is_featured('DIM'));
              $this->assertFalse($device->is_featured('COL'));
              $this->assertFalse($device->is_featured('TMP'));
              $this->assertFalse($device->is_featured('MUL'));
              break;
            case 'HS300':
              $this->assertTrue($device->is_featured('TIM'));
              $this->assertTrue($device->is_featured('ENE'));
              $this->assertFalse($device->is_featured('DIM'));
              $this->assertFalse($device->is_featured('COL'));
              $this->assertFalse($device->is_featured('TMP'));
              //$this->assertTrue($device->is_featured('MUL'));
              break;
            case 'LB130':
              $this->assertTrue($device->is_featured('COL'));
              $this->assertTrue($device->is_featured('TMP'));
              $this->assertFalse($device->is_featured('TIM'));
              $this->assertTrue($device->is_featured('DIM'));
              $this->assertTrue($device->is_featured('ENE'));
              break;
            case 'LB120':
              $this->assertTrue($device->is_featured('TMP'));
              $this->assertFalse($device->is_featured('COL'));
              $this->assertFalse($device->is_featured('TIM'));
              $this->assertTrue($device->is_featured('DIM'));
              $this->assertTrue($device->is_featured('ENE'));
              break;
            case 'LB100':
              $this->assertFalse($device->is_featured('COL'));
              $this->assertFalse($device->is_featured('TMP'));
              $this->assertFalse($device->is_featured('TIM'));
              $this->assertTrue($device->is_featured('DIM'));
              $this->assertFalse($device->is_featured('ENE'));
              break;
            default:
              break;
          }
        // }
      }
      //print_r($device::debug_last_request());
    }

    public function testGetLightDetails():void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->getType()=='IOT.SMARTBULB')
        {
          $lightDetails = $device->getLightDetails();
          $this->assertIsInt($lightDetails['wattage']);
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testSetTransitionPeriod():void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->getType()=='IOT.SMARTBULB')
        {
          $device->setTransitionPeriod(150);
          $device->switchOn();
          $last_request = $device::debug_last_request();
          $decode = json_decode($last_request['request'], TRUE);
          if ($device->getVariable('cloud'))
          {
            $decode = json_decode($decode['params']['requestData'],TRUE);
          }
          $decode = $decode['smartlife.iot.smartbulb.lightingservice']['transition_light_state']['transition_period'];
          $this->assertEquals($decode,150);

          sleep(1);

          $device->setTransitionPeriod(200);
          $device->switchOff();
          $last_request = $device::debug_last_request();
          $decode = json_decode($last_request['request'], TRUE);
          if ($device->getVariable('cloud'))
          {
            $decode = json_decode($decode['params']['requestData'],TRUE);
          }
          $decode = $decode['smartlife.iot.smartbulb.lightingservice']['transition_light_state']['transition_period'];
          $this->assertEquals($decode,200);

        }
      }
      //print_r($device::debug_last_request());
    }

    public function testGetDeviceById()
    {
      foreach(self::$ref_testDeviceList as $device)
      {
        if (!self::$conf['cloud'] || !$device['virtual'])
        {
          $dev = self::$ref_client->getDeviceById($device['deviceId']);
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
            foreach($device['children'] as $child)
            {
              $dev = self::$ref_client->getDeviceById($device['deviceId'],$child);
              // print_r($dev);
              $this->assertInstanceOf(KKPA\Clients\KKPASlotPlugApiClient::class,$dev);
              $this->assertEquals(
                $child,
                $dev->getVariable('child_id','')
              );
            }
          }
        }
      }
    }

    public function testLightState(): void
    {
      $client = $this::instance(self::$conf);
      $deviceList = array_merge(array(),self::$ref_deviceList);
      foreach($deviceList as $device)
      {
        if ($device->getType()=='IOT.SMARTBULB')
        {
          $device->switchOn();
          $expected = array();
          if($device->is_featured('DIM'))
          {
            $brightness = rand(0,100);
            $expected['brightness'] = $brightness;
          }
          else
            $brightness = null;
          if($device->is_featured('TMP'))
          {
            $color_temp = rand(2700,6500);
            $expected['color_temp'] = $color_temp;
          }
          else
            $color_temp = null;
          if($device->is_featured('COL'))
          {
            $hue = rand(0,360);
            $saturation = rand(0,100);
            $expected['hue'] = $hue;
            $expected['saturation'] = $saturation;
          } else
          {
            $hue = null;
            $saturation = null;
          }
          $device->setLightState($color_temp,$hue,$saturation,$brightness);
          $device->switchOff();
          $device->switchOn();
          sleep(DELAY_BEFORE_STATE);
          $state = $device->getLightState();
          $this->assertEquals(
            $state,
            $expected
          );
          $device->switchOff();
        }
      }
      //print_r($device::debug_last_request());
    }

    public function testMultiSlots():void
    {
      $client = $this::instance(self::$conf);
      //$deviceList = $client->getDeviceList();
      $deviceList = array_merge(array(),self::$ref_deviceList);
      // print_r($client::debug_last_request());
      foreach($deviceList as $device)
      {
        // print_r($device);
        // print_r($device->is_featured('MUL'));
        if ($device->is_featured('MUL'))
        {
          $this->assertTrue($device->has_children());
          $deviceId = $device->getSysInfo('deviceId')['deviceId'];

          $slots_id = $device->getAllIds();
          $this->assertIsArray($slots_id);

          $slots_obj = $device->getChildren();
          $this->assertIsArray($slots_obj);
          foreach($slots_obj as $slot)
          {
            $this->assertInstanceOf(
              KKPA\Clients\KKPASlotPlugApiClient::class,
              $slot
            );
            $child_id = $slot->getVariable('child_id');
            $this->assertStringStartsWith($deviceId,$child_id,"Slot ID $child_id does not include $deviceId");
            $this->assertGreaterThan(strlen($deviceId),strlen($child_id));
          }

        } else {
          $this->assertFalse($device->has_children());
        }
      }
    }

    public function testGetDevicesList():void
    {
      $client = $this::instance(self::$conf);
      $deviceList = $client->getDeviceList();
      $this->assertIsArray($deviceList);
      foreach($deviceList as $device)
      {
        $this->assertInstanceOf(
          KKPA\Clients\KKPADeviceApiClient::class,
          $device
        );
        $this->assertIsArray($device->getSysInfo());
        if ($device->is_featured('ENE'))
        {
          $this->assertIsArray($device->getRealTime());
        }
      }
    }
}
?>
