<?php
/**
 * Plugin Name: Open Source Insta Feed
 * Plugin Author: Matt Bedford
 * Author Uri: https://mattbedford.com
 * Version: 0.0.0
 *
 */

defined( 'ABSPATH' ) || exit;

/**
 * Not using namespace for the bootstrap file.
 */


/**
 * Set some useful references for lifecycle hooks.
 */
define( 'OSIF_FILE', __FILE__ ); // Absolute path to the main plugin file.
define( 'OSIF_DIR', plugin_dir_path( __FILE__ ) ); // Absolute path to the plugin directory, with a trailing slash.
define( 'OSIF_URL', plugin_dir_url( __FILE__ ) ); // Public URL of the plugin directory, with a trailing slash.


/**
 * Do requires for the plugin. We have no autoloader.
 */
require_once OSIF_DIR . 'inc/class-plugin.php';
require_once OSIF_DIR . 'inc/class-admin.php';
require_once OSIF_DIR . 'inc/class-syncer.php';
require_once OSIF_DIR . 'inc/class-client.php';
require_once OSIF_DIR . 'inc/class-block.php';
require_once OSIF_DIR . 'inc/class-image-cache.php';
require_once OSIF_DIR . 'inc/class-cron.php';

// Load cron
osif\Cron::boot();

// Load block
osif\Block::boot();

// Load admin setup
add_action( 'admin_init', [ osif\Admin::class, 'boot' ] );


/**
 * Lifecycle hooks using Plugin class but not needing constants from above.
 */
register_activation_hook( __FILE__, [ osif\Plugin::class, 'activate' ] );
register_uninstall_hook( __FILE__, [ osif\Plugin::class, 'uninstall' ] );

