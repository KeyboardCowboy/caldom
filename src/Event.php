<?php
/**
 * @file
 * Contains \Event.
 */

namespace CalDom\Event;

use Artack\DOMQuery\DOMQuery;
use CalDom\Calendar\Calendar;
use CalDom\Renderer\Renderer;

/**
 * Class Event
 *
 * @package CalDom\Event.
 */
class Event {
  // Default timezone to use if one can't be determined.
  const DEFAULT_TIMEZONE = 'America/New_York';

  // Used to specify a format to return a date.
  const DATE_FORMAT_ICAL = 'ical';

  // Event status codes.
  const STATUS_INVALID = 0;
  const STATUS_ALL_DAY = 1;
  const STATUS_SCHEDULED = 2;

  /**
   * @var Calendar
   */
  protected $cal;

  /**
   * @var DOMQuery
   */
  protected $eventDom;

  /**
   * @var array
   */
  protected $eventInfo;

  /**
   * Fields needed for the events.
   *
   * Field will be processed in the order listed here.  Make sure timezone
   * is always processed before other datetime fields.
   *
   * @var array
   */
  private $dataFields = [
    'title',
    'description',
    'timezone',
    'starttime',
    'endtime',
    'location',
    'url',
  ];

  /**
   * @var string
   */
  private $title = '';

  /**
   * @var string
   */
  private $description = '';

  /**
   * @var string
   */
  private $location = '';

  /**
   * @var \DateTimeZone
   */
  private $timezone;

  /**
   * @var \DateTime
   */
  private $startTime;

  /**
   * @var \DateTime
   */
  private $endTime;

  /**
   * @var string
   */
  private $url = '';

  /**
   * The status of the event.
   *
   * @var int
   */
  private $status = self::STATUS_INVALID;

  /**
   * Event constructor.
   *
   * @param $event_dom
   * @param $event_info
   */
  public function __construct(Calendar $cal, $event_dom, $event_info) {
    $this->cal = $cal;
    $this->eventDom = DOMQuery::create($event_dom);
    $this->eventInfo = $event_info;
    $this->extractData();
  }


  /**
   * Get the title.
   *
   * @return string
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Get the description.
   *
   * @return string
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Get the location.
   *
   * @return string
   */
  public function getLocation() {
    return $this->location;
  }

  /**
   * Get the timezone.
   *
   * @param string $format
   *   An optional formatter.  NULL to return the DateTimeZone object.
   *
   * @return \DateTimeZone|string
   */
  public function getTimezone($format = null) {
    return $format === self::DATE_FORMAT_ICAL ? $this->timezone->getName() : $this->timezone;
  }

  /**
   * Get the starttime.
   *
   * @param string $format
   *   An optional formatter.  NULL to return the DateTime object.
   *
   * @return \DateTime|string
   */
  public function getStartTime($format = null) {
    return $format === self::DATE_FORMAT_ICAL ? $this->formatDateTime($this->startTime, $this->status) : $this->startTime;
  }

  /**
   * Get the endtime.
   *
   * @param string $format
   *   An optional formatter.  NULL to return the DateTime object.
   *
   * @return \DateTime|string
   */
  public function getEndTime($format = null) {
    return $format === self::DATE_FORMAT_ICAL ? $this->formatDateTime($this->endTime, $this->status) : $this->endTime;
  }

  /**
   * Get the event URL.
   *
   * @return string
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Get a unique identifier for this event.
   *
   * @return string
   */
  private function getUid() {
    return $this->cal->getCalInfo('name') . '-' . $this->getStartTime(self::DATE_FORMAT_ICAL);
  }

  /**
   * Setter for $title.
   *
   * @param string $value
   */
  public function setTitle($value) {
    $this->title = html_entity_decode(trim(strip_tags($value)));
  }

  /**
   * Set the description.
   *
   * @param string $value
   */
  public function setDescription($value) {
    // Extract all links.
    $element = DOMQuery::create('<div>' . $value . '</div>');
    $links = [];

    foreach ($element->find('a') as $link) {
      $links[$link->getAttribute('href')] = $link->getNodes()[0]->textContent;
      $link->remove();
    }

    // Remove any other nodes from the element.
    $texts = [];
    foreach ($element->getChildren() as $child) {
      foreach ($child->getNodes() as $node) {
        if ($node->nodeName === '#text' && ($text = trim($node->textContent))) {
          $texts[] = $text;
        }
      }
    }

    // First, add the text value.
    $description[] = implode("\\n", $texts);

    // Add the links.
    foreach ($links as $url => $text) {
      $description[] = $text . ':' . "\\n" . $url;
    }

    $this->description = implode("\\n\\n", $description);
  }

  /**
   * Set the location.
   *
   * @param string $value
   */
  public function setLocation($value) {
    $this->location = html_entity_decode(trim(strip_tags($value)));
  }

  /**
   * Set the timezone.
   *
   * @param string $value
   *   A timezone abbreviation (EST) or full timezone code (America/New_York).
   */
  public function setTimezone($value) {
    $tz_abbrev = \DateTimeZone::listAbbreviations();

    // If no timezone is set, then we don't have a game time yet.
    if ($value === 'TBD') {
      $this->setStatus(self::STATUS_ALL_DAY);
    }

    // Convert 2 char US timezones to standard 3 char abbreviations.
    if (in_array($value, ['ET', 'CT', 'MT', 'PT'])) {
      $value = strtolower($value[0] . 'S' . $value[1]);
    }

    // Check for valid timezone abbreviations.
    if (isset($tz_abbrev[$value])) {
      $tz_array = reset($tz_abbrev[$value]);
      $this->timezone = new \DateTimeZone($tz_array['timezone_id']);
    }
    // Check for a full timezone string.
    elseif (preg_match('#^\w+/\w+$#', $value)) {
      $this->timezone = new \DateTimeZone($value);
    }
    // Use the default.
    else {
      $this->timezone = new \DateTimeZone(static::DEFAULT_TIMEZONE);
    }
  }

  /**
   * Set the start time.
   *
   * @param string|int|\DateTime $value
   */
  public function setStarttime($value) {
    // If this is a date without a time, set the AllDay flag.  Be careful to
    // allow midnight as a valid time.
    if (preg_match('/^\d{4}-?\d{2}-?\d{2}$/', $value)) {
      $this->setStatus(self::STATUS_ALL_DAY);
    }

    // Handle format YYYY-MM-DDTHH:MM:SSZ.
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
      $value = str_replace('T', ' ', $value);
      $value = str_replace('Z', '', $value);

      $datetime = new \DateTime($value, $this->timezone);
      $this->startTime = $datetime;
    }
    // Handle other strings.
    elseif (is_string($value) && ($datetime = new \DateTime($value, $this->timezone))) {
      $this->startTime = $datetime;
    }
    // Handle UNIX timestamps.
    elseif (is_numeric($value)) {
      $date_string = date('Y-m-d\TH:i:s', $value);
      $this->startTime = new \DateTime($date_string, $this->timezone);
    }
    // Handle preset DateTime objects.
    elseif (is_object($value) && get_class($value) === 'DateTime') {
      $this->startTime = $value;
    }
    // Default.
    else {
      $this->setStatus(self::STATUS_INVALID);
    }
  }

  /**
   * Set the end time.
   *
   * @param string|int|\DateTime $value
   */
  public function setEndtime($value) {
    // If a duration is specified, create an end time from the start time.
    if (empty($value) && isset($this->eventInfo['endtime']['duration']) && $this->startTime instanceof \DateTime) {
      $datetime = clone $this->startTime;
      $datetime->add(\DateInterval::createFromDateString($this->eventInfo['endtime']['duration']));
      $this->endTime = $datetime;
    }
    // Handle format YYYY-MM-DDTHH:MM:SSZ.
    elseif (is_string($value) && (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value))) {
      $value = str_replace('T', ' ', $value);
      $value = str_replace('Z', '', $value);

      $datetime = new \DateTime($value, $this->timezone);
      $this->endTime = $datetime;
    }
    // Handle other strings.
    elseif (is_string($value) && ($datetime = new \DateTime($value, $this->timezone))) {
      $this->endTime = $datetime;
    }
    // Handle UNIX timestamps.
    elseif (is_numeric($value)) {
      $date_string = date('Y-m-d\TH:i:s', $value);
      $this->startTime = new \DateTime($date_string, $this->timezone);
    }
    // Handle preset DateTime objects.
    elseif (is_object($value) && get_class($value) === 'DateTime') {
      $this->endTime = $value;
    }
    // Default.
    else {
      $this->endTime = new \DateTime('now', $this->timezone);
    }
  }

  /**
   * Set a URL for the event.
   *
   * @param string $value
   */
  public function setUrl($value) {
    $this->url = $this->cal->setUrlHost($value);
  }

  /**
   * Set the status of the event.
   *
   * @param int $value
   */
  public function setStatus($value) {
    $this->status = $value;
  }

  public function isValid() {
    return in_array($this->status, [self::STATUS_ALL_DAY, self::STATUS_SCHEDULED]);
  }

  /**
   * Extract data from the DOM using instructions from the YAML file.
   */
  private function extractData() {
    $raw = [];

    // Collect raw values from the DOM.
    try {
      foreach ($this->dataFields as $field) {
        $raw[$field] = [];

        // Use the CSS selectors to find and populate each available field.
        if (isset($this->eventInfo[$field]['selector'])) {
          foreach ($this->eventDom->find($this->eventInfo[$field]['selector']) as $element) {

            // Check for an element attribute value.
            if (isset($this->eventInfo[$field]['attribute'])) {
              $raw[$field][] = $element->getAttribute($this->eventInfo[$field]['attribute']);
            }
            // Use the innerHTML or text value of the element.
            else {
              $raw[$field][] = $element->getInnerHtml();
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      // @todo: Setup proper logger.
      print ($e->getMessage());
    }

    // Process and store data.
    foreach ($raw as $field => $values) {
      // Allow extending classes to implement alteration methods to clean up
      // the data before it is stored.
      $processor = 'process' . ucwords($field);
      $value = $this->cal->{$processor}($values, $this);

      // Store the value.
      $setter = 'set' . ucwords($field);
      $this->{$setter}($value);
    }
  }

  /**
   * Render the ical event.
   *
   * @return string
   *   The formatted ical event.
   */
  public function render() {
    $twig = Renderer::load()->twig;

    $vars = [
      'uid' => $this->getUid(),
      'title' => $this->getTitle(),
      'description' => $this->getDescription(),
      'location' => $this->getLocation(),
      'url' => $this->getUrl(),
      'startTime' => $this->getStartTime(self::DATE_FORMAT_ICAL),
      'endTime' => $this->getEndTime(self::DATE_FORMAT_ICAL),
    ];

    return $twig->render('event.twig', $vars);
  }

  /**
   * Format the $datetime parameter for ical usage.
   *
   * @param \DateTime $datetime
   *   An event date.
   * @param bool $allday
   *   TRUE to print the timestamp with just the date (time TBD).
   *
   * @return string
   *   The timestamp with timezone prepended.
   */
  protected function formatDateTime(\DateTime $datetime, $allday = TRUE) {
    $date = $allday ? $datetime->format('Ymd') : $datetime->format('Ymd\THis');

    return $this->getTimezone(self::DATE_FORMAT_ICAL) . ':' . $date;
  }

}
