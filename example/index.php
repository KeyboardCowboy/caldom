<?php
/**
 * @file
 * Example webcal for US Soccer schedules.
 */

namespace USMNTCal;

require_once __DIR__ . '/../src/init.php';

use CalDom\Calendar\Calendar;
use CalDom\Event\Event;

/**
 * Class USSoccerCal
 *
 * @package USMNTCal
 */
class USSoccerCal extends Calendar {

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

}

USSoccerCal::create(__DIR__ . '/usmnt.yml')->generateCalendar( __DIR__ . '/calendars/');

/**
 * Debugger.
 *
 * @param $var
 */
function dump($var) {
  print '<pre>' . print_r($var,1 ) . '</pre>';
}
