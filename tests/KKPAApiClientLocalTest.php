<?php

use PHPUnit\Framework\TestCase;

if (!defined("DELAY_BEFORE_STATE"))
  define('DELAY_BEFORE_STATE',0.5);

require_once (__ROOT__.'/src/autoload.php');
require_once('KKPATestPrototype.php');

final class KKPAApiClientLocalTest extends \KKPATestPrototype
{
    public static function setUpBeforeClass()
    {
      self::$conf = array(
        "cloud" => false,
        "username" => "niouf",
        "password" => "niorf"
      );
    }

}
?>
