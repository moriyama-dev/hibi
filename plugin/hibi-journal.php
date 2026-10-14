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
