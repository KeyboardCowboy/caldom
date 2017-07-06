<?php
/**
 * @file
 * Contains \Event.
 */

namespace CalDom\Event;

use Artack\DOMQuery\DOMQuery;
use CalDom\Calendar\Calendar;

class Event {

  const DEFAULT_TIMEZONE = 'America/New_York';

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
   * Formatted values the event template.
   *
   * @var string
   */
  private $title = '';
  private $description = '';
  private $location = '';
  private $timezone = '';

  /**
   * @var \DateTime
   */
  private $startTime;

  /**
   * @var \DateTime
   */
  private $endTime;
  private $url = '';

  /**
   * @var bool
   */
  private $allDay = FALSE;

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
   * @return string
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * @return string
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * @return string
   */
  public function getLocation() {
    return $this->location;
  }

  /**
   * @return string
   */
  public function getTimezone() {
    return $this->timezone;
  }

  /**
   * @return \DateTime
   */
  public function getStartTime() {
    return $this->startTime;
  }

  /**
   * @return \DateTime
   */
  public function getEndTime() {
    return $this->endTime;
  }

  /**
   * @return string
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Setter for $title.
   *
   * @param $value
   */
  public function setTitle($value) {
    $this->title = html_entity_decode(trim(strip_tags($value)));
  }

  public function setDescription($value) {
    $this->description = html_entity_decode(trim(strip_tags($value)));
  }

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
      $this->setAllDay();
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
   * @param $value
   */
  public function setStarttime($value) {
    // Handle format YYYY-MM-DDTHH:MM:SSZ.
    if (is_string($value) && (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value))) {
      $value = str_replace('T', ' ', $value);
      $value = str_replace('Z', '', $value);

      $datetime = new \DateTime($value, $this->timezone);
      $this->startTime = $datetime;
    }
    // Handle other strings.
    elseif (is_string($value) && ($datetime = new \DateTime($value, $this->timezone))) {
      $this->startTime = $datetime;
    }
    // Handle preset DateTime objects.
    elseif (is_object($value) && get_class($value) === 'DateTime') {
      $this->startTime = $value;
    }
    // Default.
    else {
      $this->startTime = new \DateTime('now', $this->timezone);
    }
  }

  /**
   * Set the end time.
   *
   * @param $value
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
   * @param $value
   */
  public function setUrl($value) {
    $this->url = $this->cal->setUrlHost($value);
  }

  /**
   * Make an event an all-day event or not.
   *
   * @param bool $value
   */
  public function setAllDay($value = TRUE) {
    $this->allDay = $value;
  }

  /**
   * Extract data from the DOM using instructions from the YAML file.
   */
  private function extractData() {
    $raw = [];

    // Collect raw values from the DOM.
    try {
      foreach ($this->dataFields as $field) {
        $raw[$field] = '';

        // Use the CSS selectors to find and populate each available field.
        if (isset($this->eventInfo[$field]['selector'])) {
          $element = $this->eventDom->find($this->eventInfo[$field]['selector']);

          // Check for an element attribute value.
          if (isset($this->eventInfo[$field]['attribute'])) {
            $raw[$field] = $element->getAttribute($this->eventInfo[$field]['attribute']);
          }
          // Use the innerHTML or text value of the element.
          else {
            $raw[$field] = $element->getInnerHtml();
          }
        }
      }
    }
    catch (\Exception $e) {
      // @todo: Setup proper logger.
      print ($e->getMessage());
    }

    // Process and store data.
    foreach ($raw as $field => $value) {
      // Allow extending classes to implement alteration methods to clean up
      // the data before it is stored.
      $processor = 'process' . ucwords($field);
      if (method_exists($this->cal, $processor)) {
        $value = $this->cal->{$processor}($value, $this);
      }

      // Store the value.
      $setter = 'set' . ucwords($field);
      $this->{$setter}($value);
    }
  }

}

/**
 * Class Event.
 */
class OldEvent {
  // The SoccerCal object that manages the event.
  private $cal;

  // The matchup data.
  private $matchup;

  // The venue data.
  private $venue;

  // The 'watch' and extra info data.
  private $info;

  // The calculated datetime of the event.
  private $datetime;

  // The timezone of the event.
  private $timezone;

  // Links gathered from within match data.
  private $links = [];

  // The URL to the match page.
  private $url;

  // Map data structure to DOM elements.
  protected static $field = [
    'date' => 0,
    'time' => 1,
    'matchup' => 2,
    'venue' => 3,
    'info' => 4,
  ];

  /**
   * SoccerCalEvent constructor.
   *
   * @param \Calendar $cal
   * @param \DOMNodeList $cells
   */
  public function __construct(Calendar $cal, DOMNodeList $cells) {
    $this->cal = $cal;
    $this->extractData($cells);
  }

  /**
   * Extract data from each cell in the schedule table.
   *
   * @param \DOMNodeList $cells
   */
  private function extractData(DOMNodeList $cells) {
    $this->datetime = $this->extractDateTime(
      $cells->item(static::$field['date']),
      $cells->item(static::$field['time'])
    );
    $this->matchup = $this->extractMatchup($cells->item(static::$field['matchup']));
    $this->venue = $this->extractVenue($cells->item(static::$field['venue']));
    $this->info = $this->extractInfo($cells->item(static::$field['info']));
  }

  /**
   * Extract the datetime from the schedule event.
   *
   * @param \DOMElement $date_cell
   *   The cell containing the date.
   *
   * @return string
   *   The datetime of the event in the format YYYYMMDDTHHMMSS.
   */
  private function extractDateTime(DOMElement $date_cell, DOMElement $time_cell) {
    // Get the string values from the date and time cells since the value from
    // the time element doesn't contain a timezone and reports the time in
    // whatever the event timezone is.
    $date_string = $date_cell->getElementsByTagName('time')->item(0)->nodeValue;
    $time_string = $time_cell->nodeValue;

    // Extract the timezone abbreviation from the time string.
    $time_parts = explode(' ', $time_string);
    $tz_abbrev = array_pop($time_parts);
    $time_string = implode(' ', $time_parts);

    // The website lists timezones in American timezones, but with two char
    // format.  We need three chars to use the conversion function, so inject an
    // S in between to form something like EST.
    if (strlen($tz_abbrev) === 2) {
      $tz_abbrev = $tz_abbrev[0] . 'S' . $tz_abbrev[1];
    }

    // Store the timezone for later reference.
    $this->timezone = $tz_abbrev ==='TBD' ? 'America/New_York' : timezone_name_from_abbr($tz_abbrev);

    $datetime = new DateTime("$date_string $time_string");

    return $datetime->format("Ymd\THis");
  }

  /**
   * Extract matchup info.
   *
   * @param \DOMElement $cell
   *   The cell containing the matchup info.
   *
   * @return string
   *   The event matchup info (title).
   */
  private function extractMatchup(DOMElement $cell) {
    $attributes = $cell->getElementsByTagName('meta')->item(0)->attributes;
    $value = $attributes->getNamedItem('content')->value;

    // Remove sponsorships.
    list($value,) = explode(',', $value, 2);

    // Store the URL to the event.
    $attributes = $cell->getElementsByTagName('a')->item(0)->attributes;
    $url = $attributes->getNamedItem('href')->value;
    $this->url = Calendar::USSOCCER_HOSTNAME . $url;

    return $value;
  }

  /**
   * Extract the venue information.
   *
   * @param \DOMElement $cell
   *   The cell containing the venue info.
   *
   * @return string
   *   The venue info for the event.
   */
  private function extractVenue(DOMElement $cell) {
    // Store links for the description.
    $this->extractLinks($cell);

    // Grab the data from the meta element.
    $attributes = $cell->getElementsByTagName('meta')->item(0)->attributes;
    $venue = $attributes->getNamedItem('content')->value;

    // Exclude anything after a line break.  This is usually links and other
    // junk we don't need.
    list($venue) = explode('<br />', $venue);

    return $venue;
  }

  /**
   * Extract the 'watch' info.
   *
   * @param \DOMElement $cell
   *   The last cell in the table, containing the channel and other info.
   *
   * @return string
   *   Info from the 'watch' cell.
   */
  private function extractInfo(DOMElement $cell) {
    // Store links for the description.
    $this->extractLinks($cell);

    $text = $this->extractNodeText($cell);

    return trim($text);
  }

  /**
   * Extract hyperlinks from within cell data.
   *
   * Some cells in the schedule table have complimentary links with structured
   * data.  We want to pull those links out and list them in the description.
   *
   * @param \DOMElement $cell
   *   A cell from the schedule table.
   */
  private function extractLinks(DOMElement $cell) {
    $links = $cell->getElementsByTagName('a');

    $i = 0;
    while ($a = $links->item($i)) {
      $attributes = $a->attributes;
      $href = $attributes->getNamedItem('href')->value;
      $text = $a->textContent;

      $this->links[$href] = $text;

      $i++;
    }
  }

  private function extractNodeText(DOMElement $element) {
    $text = '';

    foreach ($element->childNodes as $node) {
      if ($node->nodeName === '#text') {
        $text .= $node->textContent;
      }
    }

    return preg_replace('/(\s+)|\|/', ' ', $text);
  }

  /**
   * Format the $datetime parameter for ical usage.
   *
   * @param string $datetime
   *   The event date in the required ical format.
   * @param bool $full
   *   FALSE to print the timestamp with just the date (time TBD).
   *
   * @return string
   *   The timestamp with timezone prepended.
   */
  protected function formatDateTime($datetime, $full = TRUE) {
    $date = $full ? date('Ymd\THis', $datetime) : date('Ymd', $datetime);

    return $this->timezone . ':' .$date;
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
      'summary' => $this->getSummary(),
      'description' => $this->getDescription(),
      'location' => $this->getLocation(),
      'url' => $this->getUrl(),
      'startTime' => $this->getStartDate(),
      'endTime' => $this->getEndDate(),
    ];

    return $twig->render('event.twig', $vars);
  }

  /**
   * Get the UID for the calendar event.
   *
   * @return string
   *   A unique ID for the event.
   */
  public function getUid() {
    return $this->cal->getCalInfo('name') . '-' . $this->getStartDate() . '@ussoccer.com';
  }

  /**
   * Get the URL for the event page.
   *
   * @return string
   *   The event page URL.
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Get the ical Summary (title) field.
   *
   * @return string
   *   The matchup info to be used as the summary.
   */
  public function getSummary() {
    return strtr($this->matchup, array('&' => 'and'));
  }

  /**
   * Get the ical description info.
   *
   * @return string
   *   Description info for the event.
   */
  public function getDescription() {
    $out = [];

    // Get the text info.
    $out[] = "Watch: {$this->info}";

    // Add extracted URLs.
    foreach ($this->links as $href => $text) {
      $url = (stripos($href, 'http') === 0) ? $href : Calendar::USSOCCER_HOSTNAME . $href;

      $out[] = $text . ':\n' . $url;
    }

    return implode('\n\n', $out);
  }

  /**
   * Get the venue/location info for the event.
   *
   * @return string
   *   The event venue.
   */
  public function getLocation() {
    return $this->venue;
  }

  /**
   * Get the start datestamp for the event.
   *
   * @return string
   *   The event start date in ical format.
   */
  public function getStartDate() {
    $timestamp = $this->getTimeStamp();

    return static::formatDateTime($timestamp, $this->hasEndTime());
  }

  /**
   * Determine whether the event has an end time.
   *
   * @return bool
   *   TRUE if an event time is set.
   */
  public function hasEndTime() {
    list($date, $time) = explode('T', $this->datetime);

    return ($time !== '000000');
  }

  /**
   * Get the end datestamp for the event.
   *
   * We add two hours to the start date.
   *
   * @return string
   *   The event end date in ical format.
   */
  public function getEndDate() {
    if ($this->hasEndTime()) {
      $start_date = $this->getTimeStamp();
      $end_date = strtotime('+2 hours', $start_date);

      return static::formatDateTime($end_date);
    }
    else {
      return '';
    }
  }

  /**
   * Get a unix timestamp for the event datetime.
   *
   * @return false|int
   *   The unix timestamp of the event datetime.
   */
  private function getTimeStamp() {
    return strtotime($this->datetime);
  }

}

