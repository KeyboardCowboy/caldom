<?php

require_once __DIR__ . '/init.php';

use CalDom\Calendar\Calendar;
use CalDom\Event\Event;

/**
 * Class USSoccerCal
 *
 * @package USMNTCal
 */
class USSoccerCal extends Calendar {

  /**
   * {@inheritdoc}
   */
  public function processTimezone(array $values, Event $event) {
    $parts = explode(' ', $values[0]);
    $tz = array_pop($parts);

    return $tz;
  }

}

if ($cal = USSoccerCal::load('usmnt.yml')) {
  $cal->generateCalendar(__DIR__ . '/calendars');
  $cal->printCal();
}
