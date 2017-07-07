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
   * Create a new calendar.
   *
   * @param string $file
   *   The path to a YAML file containing calendar instructions.
   *
   * @return static|null
   *   A new Calendar object or NULL if the file can't be loaded.
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

  /**
   * Process the title element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processTitle(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['title']['join']) ? $this->calInfo['events']['title']['join'] : ' ';

    return implode($joiner, $values);
  }

  /**
   * Process the description element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processDescription(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['description']['join']) ? $this->calInfo['events']['description']['join'] : ' ';

    return implode($joiner, $values);
  }

  /**
   * Process the location element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processLocation(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['location']['join']) ? $this->calInfo['events']['location']['join'] : ' ';

    return implode($joiner, $values);
  }
  /**
   * Process the StartTime element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processStarttime(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['starttime']['join']) ? $this->calInfo['events']['starttime']['join'] : ' ';

    return implode($joiner, $values);
  }

  /**
   * Process the EndTime element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processEndtime(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['endtime']['join']) ? $this->calInfo['events']['endtime']['join'] : ' ';

    return implode($joiner, $values);
  }

  /**
   * Process the Timezone element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processTimezone(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['timezone']['join']) ? $this->calInfo['events']['timezone']['join'] : ' ';

    return implode($joiner, $values);
  }
  /**
   * Process the URL element.
   *
   * @param array $values
   *   All values found in the DOM.
   * @param \CalDom\Event\Event $event
   *   The Event object containing the data.
   *
   * @return string
   *   A single piece of data for the field.
   */
  public function processUrl(array $values, Event $event) {
    $joiner = isset($this->calInfo['events']['url']['join']) ? $this->calInfo['events']['url']['join'] : ' ';

    return implode($joiner, $values);
  }

}
