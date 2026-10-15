<?php
/**
 * Uninstall: removes the plugin's keys and caches, never the journal.
 *
 * Entries (private posts) and photos stay, so reinstalling the plugin, or
 * reading the database directly, brings everything back.
 *
 * @package HibiJournal
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'hibi_vapid_keys' );
wp_clear_scheduled_hook( 'hibi_send_reminders' );

// Device subscriptions cannot work without the keys above.
delete_metadata( 'user', 0, 'hibi_push_subscriptions', '', true );
delete_metadata( 'user', 0, 'hibi_reminder_sent', '', true );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transients created by this plugin; there is no API to delete by prefix.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_hibi_' ) . '%', $wpdb->esc_like( '_transient_timeout_hibi_' ) . '%' ) );
