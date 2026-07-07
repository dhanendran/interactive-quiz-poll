<?php
/**
 * Plugin Name:       Interactive Quiz & Poll
 * Plugin URI:        https://github.com/dhanendran/interactive-quiz-poll
 * Description:       First-party quiz and poll blocks built on native Gutenberg and the WordPress Interactivity API. Author interactive quizzes and polls as reusable content, drop them anywhere with an embed block, and collect votes with server-side validation — no third-party embeds, no data leaving your site.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Dhanendran Rajagopal
 * Author URI:        https://dhanendranrajagopal.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       interactive-quiz-poll
 *
 * @package D9QP
 */

namespace D9QP;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'D9QP_VERSION', '1.0.0' );
define( 'D9QP_FILE', __FILE__ );
define( 'D9QP_DIR', plugin_dir_path( __FILE__ ) );
define( 'D9QP_URL', plugin_dir_url( __FILE__ ) );

require_once D9QP_DIR . 'includes/class-block-tree.php';
require_once D9QP_DIR . 'includes/class-counters.php';
require_once D9QP_DIR . 'includes/class-rate-limiter.php';
require_once D9QP_DIR . 'includes/class-post-types.php';
require_once D9QP_DIR . 'includes/class-blocks.php';
require_once D9QP_DIR . 'includes/class-rest-controller.php';
require_once D9QP_DIR . 'includes/class-admin-columns.php';
require_once D9QP_DIR . 'includes/class-results.php';
require_once D9QP_DIR . 'includes/class-results-page.php';
require_once D9QP_DIR . 'includes/class-plugin.php';

/**
 * Boot the plugin once all files are loaded.
 */
function bootstrap() {
	Plugin::instance()->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\\Post_Types', 'register' ) );
register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
