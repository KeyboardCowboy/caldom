<?php
/**
 * @file
 * Contains \Renderer.
 */

/**
 * Event renderer.
 */
class Renderer {
  // The twig object.
  public $twig;

  /**
   * Renderer constructor.
   */
  public function __construct() {
    // Load the twig renderer.
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../templates');
    $this->twig = new Twig_Environment($loader);
  }

  /**
   * Singleton loader to load $twig for template rendering.
   *
   * @return static
   *   The Renderer object.
   */
  public static function load() {
    static $instance;

    if (!isset($instance)) {
      $instance = new static();
    }

    return $instance;
  }

}
