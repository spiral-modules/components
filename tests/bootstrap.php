<?php
/**
 * Spiral Framework, SpiralScout LLC.
 *
 * @package   spiralFramework
 * @author    Anton Titov (Wolfy-J)
 */
define('SPIRAL_INITIAL_TIME', microtime(true));

/**
 * Error reporting.
 */
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);

mb_internal_encoding('UTF-8');

//Composer
require dirname(__DIR__) . '/vendor/autoload.php';