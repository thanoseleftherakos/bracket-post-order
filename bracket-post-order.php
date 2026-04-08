<?php
/**
 * Plugin Name:       Bracket Post Order
 * Plugin URI:        https://wordpress.org/plugins/bracket-post-order/
 * Description:       Drag-and-drop post ordering with per-taxonomy-term support. Reorder posts globally or within specific categories/tags.
 * Version:           1.2.4
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Bracket
 * Author URI:        https://bracket.gr
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bracket-post-order
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRACKET_PO_VERSION', '1.2.4' );
define( 'BRACKET_PO_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRACKET_PO_URL', plugin_dir_url( __FILE__ ) );

require_once BRACKET_PO_PATH . 'includes/class-bracket-po-core.php';
require_once BRACKET_PO_PATH . 'includes/class-bracket-po-settings.php';
require_once BRACKET_PO_PATH . 'includes/class-bracket-po-ajax.php';
require_once BRACKET_PO_PATH . 'includes/class-bracket-po-admin.php';
require_once BRACKET_PO_PATH . 'includes/class-bracket-po-query.php';

/**
 * Initialize plugin on plugins_loaded.
 */
function bracket_po_init() {
	Bracket_PO_Settings::init();
	Bracket_PO_Ajax::init();
	Bracket_PO_Admin::init();
	Bracket_PO_Query::init();

	// WPML compatibility.
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		require_once BRACKET_PO_PATH . 'includes/class-bracket-po-compat-wpml.php';
		Bracket_PO_Compat_WPML::init();
	}

	// Polylang compatibility.
	if ( function_exists( 'pll_current_language' ) ) {
		require_once BRACKET_PO_PATH . 'includes/class-bracket-po-compat-polylang.php';
		Bracket_PO_Compat_Polylang::init();
	}
}
add_action( 'plugins_loaded', 'bracket_po_init' );

/**
 * Ensure the term_order column exists in wp_terms.
 * Runs on activation and on admin_init (version check) to handle
 * FTP installs or failed ALTER TABLE during activation.
 */
function bracket_po_ensure_term_order_column() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
	$row = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->terms} LIKE 'term_order'" );
	if ( empty( $row ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required schema change.
		$result = $wpdb->query( "ALTER TABLE {$wpdb->terms} ADD `term_order` INT(4) NULL DEFAULT '0'" );
		if ( $result === false ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p>';
				echo wp_kses(
					__( '<strong>Bracket Post Order:</strong> Failed to add the <code>term_order</code> column to the <code>wp_terms</code> table. Please run this SQL manually: <code>ALTER TABLE wp_terms ADD term_order INT(4) NULL DEFAULT \'0\'</code>', 'bracket-post-order' ),
					[ 'strong' => [], 'code' => [] ]
				);
				echo '</p></div>';
			} );
		}
	}
}
register_activation_hook( __FILE__, 'bracket_po_ensure_term_order_column' );

/**
 * Check term_order column on admin_init using a version flag,
 * so FTP deployments or failed activations are caught.
 */
function bracket_po_admin_check_schema() {
	$db_version = get_option( 'bracket_po_db_version', '0' );
	if ( version_compare( $db_version, BRACKET_PO_VERSION, '<' ) ) {
		bracket_po_ensure_term_order_column();
		update_option( 'bracket_po_db_version', BRACKET_PO_VERSION );
	}
}
add_action( 'admin_init', 'bracket_po_admin_check_schema' );

/**
 * Add Settings link to plugin action links.
 *
 * @param array $links Existing links.
 * @return array
 */
function bracket_po_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=bracket-post-order' ) ) . '">'
		. esc_html__( 'Settings', 'bracket-post-order' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bracket_po_plugin_action_links' );
