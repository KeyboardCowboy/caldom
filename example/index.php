<?php

namespace USMNTCal;

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use CalDom\Calendar\Calendar;

/**
 * Class USSoccerCal
 *
 * @package USMNTCal
 */
class USSoccerCal extends Calendar {

  /**
   * @param $file
   *
   * @return static
   */
  public static function create($file) {
    try {
      $data = Yaml::parse(file_get_contents($file));
      $calendar = new static($data);

      return $calendar;
    } catch (ParseException $e) {
      printf("Unable to parse the YAML file: %s", $e->getMessage());
    }
  }

  public function processStartdate(&$value, $info) {
    $value = strtotime($value);
  }

}


$calendar = USSoccerCal::create('./usmnt.yml');
// $calendar->generateCalendar();

function dump($var) {
  print '<pre>' . print_r($var,1 ) . '</pre>';
}
