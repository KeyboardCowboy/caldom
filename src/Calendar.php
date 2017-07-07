<?php
/**
 * @file
 * Generate an iCal feed from an HTML DOM.
 */

namespace CalDom\Calendar;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Artack\DOMQuery\DOMQuery;
use CalDom\Event\Event;
use CalDom\Renderer\Renderer;

class Calendar {

  /**
   * The YAML data for this cal.
   *
   * @var array
   */
  protected $calInfo;

  /**
   * The DOMQuery objects for the pages.
   *
   * @var array
   */
  protected $documents;

  /**
   * Events objects extracted from the DOM.
   *
   * @var array
   */
  protected $events = [];

  /**
   * Calendar constructor.
   *
   * @param array $cal_data
   *   Imported YAML data for the calendar.
   */
  protected function __construct(array $cal_data) {
    $this->calInfo = $cal_data;

    $this->fetchDocuments();
    $this->extractEvents();
  }

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

  /**
   * Fetch the DOM of a schedule page.
   */
  private function fetchDocuments() {
    foreach ((array) $this->calInfo['url'] as $url) {
      if ($contents = file_get_contents($url)) {
        $this->documents[] = $this->prepareDocument(DOMQuery::create($contents));
      }
      else {
        throw new \Exception("Failed to fetch data from url.");
      }
    }
  }

  /**
   * Allow extending classes to manipulate the document.
   *
   * @param DOMQuery $document
   *
   * @return DOMQuery
   */
  protected function prepareDocument(DOMQuery $document) {
    return $document;
  }

  /**
   * Extract event objects from the DOM.
   */
  private function extractEvents() {
    foreach ($this->documents as $document) {
      foreach ($document->find($this->calInfo['events']['selector']) as $event_dom) {
        $this->events[] = new Event($this, $event_dom, $this->calInfo['events']);
      }
    }
  }

  /**
   * Create the subscribable calendar file.
   */
  public function generateCalendar($dir = '') {
    $calendar = $this->render();

    // Store the URL.
    $path = rtrim($dir, '/') . '/' . $this->calInfo['name'] . '.ics';

    // Create the calendar.
    if (!file_put_contents($path, $calendar)) {
      throw new \Exception("Failed to save updated calendar.");
    }
    else {
      print "Calendar '{$this->calInfo['name']}.ics' successfully created!";
    }
  }

  /**
   * Render the ical file.
   */
  public function render() {
    $twig = Renderer::load()->twig;
    $vars = [];

    // Calendar title.
    $vars['title'] = $this->calInfo['title'];

    // Build events.
    foreach ($this->events as $event) {
      $vars['events'][] = $event->render();
    }

    return $twig->render('ical.twig', $vars);
  }

  /**
   * Ensure a URL has a hostname.
   *
   * @param $url
   *
   * @return string
   */
  public function setUrlHost($url) {
    return stripos($url, 'http') === 0 ? $url : rtrim($this->calInfo['base_url'], '/') . '/' . ltrim($url, '/');
  }

  /**
   * Get a value from the $calinfo array.
   *
   * @param string $param
   *   An optional parameter from the calInfo object.
   *
   * @return mixed
   *   A value from the calInfo array or the whole array itself.
   */
  public function getCalInfo($param = NULL) {
    if (isset($param)) {
      return isset($this->calInfo[$param]) ? $this->calInfo[$param] : NULL;
    }
    else {
      return $this->calInfo;
    }
  }


  public function processTitle(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['title']['join']) ? $this->calInfo['events']['title']['join'] : ' ';

    return implode($joiner, $values);
  }

  public function processDescription(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['description']['join']) ? $this->calInfo['events']['description']['join'] : ' ';

    return implode($joiner, $values);
  }

  public function processLocation(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['location']['join']) ? $this->calInfo['events']['location']['join'] : ' ';

    return implode($joiner, $values);
  }
  public function processStarttime(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['starttime']['join']) ? $this->calInfo['events']['starttime']['join'] : ' ';

    return implode($joiner, $values);
  }
  public function processEndtime(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['endtime']['join']) ? $this->calInfo['events']['endtime']['join'] : ' ';

    return implode($joiner, $values);
  }
  public function processTimezone(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['timezone']['join']) ? $this->calInfo['events']['timezone']['join'] : ' ';

    return implode($joiner, $values);
  }
  public function processUrl(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['url']['join']) ? $this->calInfo['events']['url']['join'] : ' ';

    return implode($joiner, $values);
  }

}

/**
 * Class Calendar.
 */
class OldCalendar {
  // Hostname for the US Soccer website.
  const USSOCCER_HOSTNAME = 'http://www.ussoccer.com';

  // Data files.
  const CALENDAR_FILE = __DIR__ . '/../data/calendars.json';
  const STATUS_FILE = __DIR__ . '/../data/statuses.json';

  // The calInfo shortname we are building.
  protected $calInfo;

  // THe retrieved document object from ussoccer.com.
  protected $document;

  // Event objects extracted from a ussoccer.com schedule page.s
  protected $events = [];

  /**
   * SoccerCal constructor.
   *
   * @param object $cal_info
   *   A data object for a calendar from the data.json file.
   */
  public function __construct($cal_info) {
    $this->calInfo = $cal_info;
    $this->fetchDocument();
    $this->extractEvents();
  }

  /**
   * Load the calendar data.
   *
   * @return object
   *   The json object from the data.
   */
  public static function loadCalendarInfo() {
    $data = file_get_contents(static::CALENDAR_FILE);

    return json_decode($data);
  }

  /**
   * Load the calendar statuses.
   *
   * @return object
   *   The json object from the data.
   */
  public static function loadCalendarStatuses() {
    if (file_exists(static::STATUS_FILE)) {
      $data = file_get_contents(static::STATUS_FILE);

      return json_decode($data);
    }
    else {
      return json_decode("{}");
    }
  }

  /**
   * Save the json data.
   *
   * @param object $json
   *   The calendar json data.
   */
  public static function saveCalendarStatuses($json) {
    $data = json_encode($json);
    file_put_contents(static::STATUS_FILE, $data);
  }

  /**
   * Fetch the DOM of a schedule page.
   */
  private function fetchDocument() {
    if ($contents = file_get_contents($this->calInfo->url)) {
      $dom = new DOMDocument();
      @$dom->loadHTML($contents, LIBXML_NOERROR);
      $dom->preserveWhiteSpace = FALSE;
      $dom->normalizeDocument();

      $this->document = $dom;
    }
    else {
      throw new Exception("Failed to fetch data from url.");
    }
  }

  /**
   * Extract events from the schedule DOM.
   */
  private function extractEvents() {
    $tables = $this->document->getElementsByTagName('table');

    // Cycle through each table row.
    foreach ($tables as $table) {
      // Validate the table element.
      if (!$this->validateTable($table)) {
        continue;
      }

      $rows = $table->getElementsByTagName('tr');

      // Pull the data out of the TD elements.
      foreach ($rows as $tr) {
        $cells = $tr->getElementsByTagName('td');

        if ($cells->length > 0) {
          $this->events[] = new Event($this, $cells);
        }
      }
    }
  }

  /**
   * Verify that the table element is a valid match table.
   *
   * @param \DOMElement $table
   *   A table element from the schedule page.
   *
   * @return bool
   *   TRUE if the table has the appropriate class.
   */
  private function validateTable(DOMElement $table) {
    $classes = explode(' ', $table->getAttribute('class'));

    return in_array('match-table', $classes);
  }

  /**
   * Render the ical file.
   */
  public function render() {
    $twig = Renderer::load()->twig;
    $vars = [];

    // Calendar title.
    $vars['title'] = $this->calInfo->title;

    // Build events.
    foreach ($this->events as $event) {
      $vars['events'][] = $event->render();
    }

    return $twig->render('ical.twig', $vars);
  }

  /**
   * Get the calInfo shortcode.
   *
   * @param string $param
   *   An optional parameter from the calInfo object.
   *
   * @return string|object
   *   A value from the calInfo object or the whole object itself.
   */
  public function getCalInfo($param = NULL) {
    if (isset($param)) {
      return isset($this->calInfo->{$param}) ? $this->calInfo->{$param} : NULL;
    }
    else {
      return $this->calInfo;
    }
  }

  /**
   * Get the URL for the calInfo schedule.
   *
   * @return string
   *   The URL for the calInfo schedule.
   */
  public function getUrl() {
    return $this->calInfo->url;
  }

  /**
   * Get the HTTP HOST value.
   *
   * @return string
   *   The value of $_SERVER['HTTP_HOST'] or localhost if not set.
   */
  private static function httpHost() {
    return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
  }

  /**
   * Create the subscribable calendar file.
   */
  public function generateCalendar() {
    $calendar = $this->render();

    // Store the URL.
    $path = __DIR__ . "/../calendars/{$this->calInfo->name}.ics";

    // Create the calendar.
    if (!file_put_contents($path, $calendar)) {
      throw new Exception("Failed to save updated calendar.");
    }
  }

  /**
   * Store the status values.
   *
   * @param string $name
   *   The name of the calendar.
   * @param array $values
   *   The values to set.
   */
  public static function setStatus($name, array $values = []) {
    $statuses = static::loadCalendarStatuses();

    // Make sure we have an object for the cal.
    if (!isset($statuses->{$name})) {
      $statuses->{$name} = new stdClass();
    }

    // Store the values for the cal.
    foreach ($values as $field => $value) {
      $statuses->{$name}->{$field} = $value;
    }

    static::saveCalendarStatuses($statuses);
  }

  /**
   * Render the status reports for calendars.
   *
   * @return string
   *   The HTML for the calendar statuses.
   */
  public static function renderStatusReports() {
    $out = [];
    $info = static::loadCalendarInfo();
    $statuses = static::loadCalendarStatuses();

    foreach ($info->calendars as $cal_info) {
      $vars = [
        'title' => $cal_info->title,
        'url' => 'http://' . static::httpHost() . "/calendars/{$cal_info->name}.ics",
        'generated' => isset($statuses->{$cal_info->name}->generated) ? $statuses->{$cal_info->name}->generated : 0,
        'last_attempt' => isset($statuses->{$cal_info->name}->last_attempt) ? $statuses->{$cal_info->name}->last_attempt : 0,
        'message' => isset($statuses->{$cal_info->name}->message) ? $statuses->{$cal_info->name}->message : '',
        'status' => isset($statuses->{$cal_info->name}->status) ? $statuses->{$cal_info->name}->status : 1,
        'source' => $cal_info->url,
      ];

      $twig = Renderer::load()->twig;

      $out[] = $twig->render('summary.twig', $vars);
    }

    return implode(PHP_EOL, $out);
  }

  /**
   * Render the calendar links.
   *
   * @return string
   *   The HTML for the calendar items.
   */
  public static function renderCalendars() {
    $out = [];
    $info = static::loadCalendarInfo();
    $statuses = static::loadCalendarStatuses();

    foreach ($info->calendars as $cal_info) {
      $cal_path = __DIR__ . "/../calendars/{$cal_info->name}.ics";
      $has_cal = file_exists($cal_path);

      $vars = [
        'title' => $cal_info->title,
        'url' => $has_cal ? 'http://' . static::httpHost() . "/calendars/{$cal_info->name}.ics" : '',
        'generated' => ($has_cal && isset($statuses->{$cal_info->name}->generated)) ? $statuses->{$cal_info->name}->generated : 0,
        'icon' => file_get_contents(__DIR__ . '/../images/cal.svg'),
      ];

      $twig = Renderer::load()->twig;

      $out[] = $twig->render('cal-download.twig', $vars);
    }

    return implode(PHP_EOL, $out);
  }

}
