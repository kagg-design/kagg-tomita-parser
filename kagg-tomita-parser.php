<?php
/**
 * Plugin Name: Tomita parser
 * Plugin URI: https://kagg.eu/en/
 * Description: Selection of whole phrases in search and other queries with Tomita parser by Yandex.
 * Author: KAGG Design
 * Version: 1.0.0
 * Author URI: https://kagg.eu/en/
 * Requires at least: 4.4
 * Tested up to: 5.0
 *
 * Text Domain: tomita-parser
 * Domain Path: /languages/
 *
 * @package tomita-parser
 * @author KAGG Design
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'TOMITA_PARSER_PATH', dirname( __FILE__ ) );
define( 'TOMITA_PARSER_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'TOMITA_PARSER_FILE', __FILE__ );
define( 'TOMITA_PARSER_VERSION', '1.0.0' );

// Init plugin class on plugin load.
static $plugin;

if ( ! isset( $plugin ) ) {
	// Require main class of the plugin.
	require_once TOMITA_PARSER_PATH . '/includes/class-kagg-tomita-parser.php';

	$plugin = new KAGG_Tomita_Parser();
}
