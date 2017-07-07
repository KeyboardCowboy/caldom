<?php
/**
 * @file
 * Example webcal for US Soccer schedules.
 */

namespace USMNTCal;

require_once __DIR__ . '/init.php';

use Artack\DOMQuery\DOMQuery;
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

/**
 * Class GoldCupCal
 *
 * @package USMNTCal
 */
class GoldCupCal extends Calendar {

  /**
   * {@inheritdoc}
   */
  public function prepareDocument(DOMQuery $document) {
    $date = '';

    // Add the date from the preceding table header to each match within the
    // subsequent tbody element.
    foreach ($document->find('table.wisbb_scheduleTable > *') as $table_element) {
      $tag = $table_element->getNodes()[0]->tagName;

      // If we hit a header, store the date.
      if ($tag === 'thead') {
        $date = $table_element->find('th')->getNodes()[0]->textContent;
        continue;
      }

      // If we hit a body, add the date to each match's time.
      if ($tag === 'tbody') {
        foreach ($table_element->find($this->calInfo['events']['starttime']['selector']) as &$time) {
          $time->replaceInner($date . ', ' . $time->getInnerHtml());
        }
      }
    }

    return parent::prepareDocument($document);
  }

  /**
   * {@inheritdoc}
   */
  public function processTimezone(array $values, Event $event) {
    $parts = explode(' ', $values[0]);
    $tz = array_pop($parts);

    return $tz;
  }

  /**
   * {@inheritdoc}
   */
  public function processStarttime(array $values, Event $event) {
    $parts = explode(' ', $values[0]);

    // Remove the timezone.
    array_pop($parts);
    $date = implode(' ', $parts);

    // Make sure the ToD is am or pm, and not the shorthand.
    if (substr($date, -1) !== 'm') {
      $date .= 'm';
    }

    // Break it down into a timestamp.
    return strtotime($date);
  }

}

// Create the calendars.
USSoccerCal::load(__DIR__ . '/usmnt.yml')->generateCalendar( __DIR__ . '/calendars/');
GoldCupCal::load(__DIR__ . '/gc.yml')->generateCalendar( __DIR__ . '/calendars/');
