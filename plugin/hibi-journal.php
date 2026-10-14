<?php
/**
 * Plugin Name:       Hibi Journal
 * Plugin URI:        https://github.com/moriyama-dev/hibi
 * Description:       Turns WordPress into a private, distraction-free daily journal.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Yoshiro Moriyama (Takumi Web Services)
 * Author URI:        https://takumi.ca
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hibi-journal
 * Domain Path:       /languages
 *
 * @package HibiJournal
 */

defined( 'ABSPATH' ) || exit;

define( 'HIBI_VERSION', '0.1.0' );
define( 'HIBI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HIBI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HIBI_PLUGIN_FILE', __FILE__ );

require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-settings.php';
require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-day.php';
require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-entry-type.php';
require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-entries.php';
require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-stats.php';
require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-rest.php';

Hibi_Entry_Type::init();
Hibi_Entries::init();
Hibi_Rest::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once HIBI_PLUGIN_DIR . 'includes/class-hibi-cli.php';
	WP_CLI::add_command( 'hibi', 'Hibi_CLI' );
}

/**
 * Whether the Hibi Journal plugin is loaded. The Hibi theme checks this.
 *
 * @return bool
 */
function hibi_is_active() {
	return true;
}
