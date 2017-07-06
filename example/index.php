<?php
/**
 * @file
 * Example webcal for US Soccer schedules.
 */

namespace USMNTCal;

require_once __DIR__ . '/../src/init.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use CalDom\Calendar\Calendar;
use CalDom\Event\Event;

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
   *
   * @todo: Generalize this into the parent.
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

  /**
   * Process the timezone for US Soccer schedule.
   *
   * @param $value
   * @param \CalDom\Event\Event $event
   *
   * @return mixed
   */
  public function processTimezone($value, Event $event) {
    $parts = explode(' ', $value);
    $tz = array_pop($parts);

    return $tz;
  }

  public function __destruct() {
    unset($this->document);
    dump($this->events);
  }

}


$calendar = USSoccerCal::create('./usmnt.yml');
// $calendar->generateCalendar();

function dump($var) {
  print '<pre>' . print_r($var,1 ) . '</pre>';
}
