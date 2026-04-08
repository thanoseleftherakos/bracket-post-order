<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bracket_PO_Core {

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return get_option( 'bracket_po_settings', [
			'post_types'  => [],
			'taxonomies'  => [],
			'term_order'  => [],
		] );
	}

	/**
	 * Get enabled post type slugs.
	 *
	 * @return array
	 */
	public static function get_enabled_post_types() {
		$settings = self::get_settings();
		return ! empty( $settings['post_types'] ) ? (array) $settings['post_types'] : [];
	}

	/**
	 * Get enabled taxonomies for per-term post ordering.
	 *
	 * @return array
	 */
	public static function get_enabled_taxonomies() {
		$settings = self::get_settings();
		return ! empty( $settings['taxonomies'] ) ? (array) $settings['taxonomies'] : [];
	}

	/**
	 * Get enabled taxonomies for term reordering.
	 *
	 * @return array
	 */
	public static function get_enabled_term_order_taxonomies() {
		$settings = self::get_settings();
		return ! empty( $settings['term_order'] ) ? (array) $settings['term_order'] : [];
	}

	/**
	 * Get saved per-term post order.
	 *
	 * @param int $term_id
	 * @return array Array of post IDs in order.
	 */
	public static function get_term_order( $term_id ) {
		$order = get_term_meta( $term_id, '_bracket_po_order', true );
		if ( ! is_array( $order ) || empty( $order ) ) {
			return [];
		}
		return array_map( 'absint', $order );
	}

	/**
	 * Initialize menu_order for all posts of a given post type.
	 *
	 * Pages are sorted alphabetically (title ASC), other post types by date DESC.
	 * Only runs if posts need initialization (all have menu_order 0).
	 *
	 * @param string $post_type Post type slug.
	 */
	public static function initialize_post_type_order( $post_type ) {
		global $wpdb;

		$statuses_arr  = array( 'publish', 'pending', 'draft', 'private', 'future' );
		$placeholders  = implode( ', ', array_fill( 0, count( $statuses_arr ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time initialization check.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})",
				array_merge( array( $post_type ), $statuses_arr )
			)
		);

		if ( $total === 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time initialization check.
		$max_order = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})",
				array_merge( array( $post_type ), $statuses_arr )
			)
		);

		// If COUNT equals MAX+1 the menu_order is already sequential — skip.
		if ( $max_order > 0 && $total === $max_order + 1 ) {
			return;
		}

		// If MAX > 0 but not sequential, it still has some ordering — skip init.
		if ( $max_order > 0 ) {
			return;
		}

		// All posts have menu_order 0 — assign sequential values.
		$order_col = esc_sql( ( $post_type === 'page' ) ? 'post_title ASC' : 'post_date DESC' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk initialization.
		$all_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) ORDER BY {$order_col}",
				array_merge( array( $post_type ), $statuses_arr )
			)
		);

		foreach ( $all_ids as $i => $pid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk initialization; cache cleared after.
			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => $i ],
				[ 'ID' => (int) $pid ],
				[ '%d' ],
				[ '%d' ]
			);
			clean_post_cache( (int) $pid );
		}
	}

	/**
	 * Refresh menu_order for a post type if gaps are detected.
	 *
	 * Checks COUNT(*) === MAX(menu_order) + 1. If not, re-sequences.
	 *
	 * @param string $post_type Post type slug.
	 */
	public static function refresh_post_type_order( $post_type ) {
		global $wpdb;

		$statuses_arr  = array( 'publish', 'pending', 'draft', 'private', 'future' );
		$placeholders  = implode( ', ', array_fill( 0, count( $statuses_arr ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lightweight check on admin_init.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})",
				array_merge( array( $post_type ), $statuses_arr )
			)
		);

		if ( $total === 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lightweight check on admin_init.
		$max_order = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})",
				array_merge( array( $post_type ), $statuses_arr )
			)
		);

		// Sequential: total posts matches max+1, no gaps.
		if ( $total === $max_order + 1 ) {
			return;
		}

		// Re-sequence by current menu_order, then by date/title as tiebreaker.
		// ID ASC ensures deterministic sort when menu_order and date/title tie.
		$order_col = esc_sql(
			( $post_type === 'page' )
				? 'menu_order ASC, post_title ASC, ID ASC'
				: 'menu_order ASC, post_date DESC, ID ASC'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Refresh to fix gaps.
		$all_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) ORDER BY {$order_col}",
				array_merge( array( $post_type ), $statuses_arr )
			)
		);

		foreach ( $all_ids as $i => $pid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk refresh; cache cleared after.
			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => $i ],
				[ 'ID' => (int) $pid ],
				[ '%d' ],
				[ '%d' ]
			);
			clean_post_cache( (int) $pid );
		}
	}

	/**
	 * Check if a taxonomy filter is active on the current admin screen.
	 *
	 * @return array|false [ 'taxonomy' => slug, 'term_id' => int ] or false.
	 */
	public static function is_taxonomy_filter_active() {
		$enabled_taxonomies = self::get_enabled_taxonomies();

		foreach ( $enabled_taxonomies as $taxonomy ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin list table filter, no form submission.
			if ( ! empty( $_GET[ $taxonomy ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$slug = sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) );

				if ( $slug === '0' || $slug === '' ) {
					continue;
				}

				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					return [
						'taxonomy' => $taxonomy,
						'term_id'  => $term->term_id,
						'term_name' => $term->name,
					];
				}
			}
		}

		return false;
	}

}
