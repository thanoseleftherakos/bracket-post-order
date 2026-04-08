<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bracket_PO_Ajax {

	public static function init() {
		add_action( 'wp_ajax_bracket_po_save_global_order', [ __CLASS__, 'save_global_order' ] );
		add_action( 'wp_ajax_bracket_po_save_term_post_order', [ __CLASS__, 'save_term_post_order' ] );
		add_action( 'wp_ajax_bracket_po_save_term_order', [ __CLASS__, 'save_term_order' ] );
		add_action( 'wp_ajax_bracket_po_reset_order', [ __CLASS__, 'reset_order' ] );
	}

	/**
	 * Save global post order (menu_order).
	 */
	public static function save_global_order() {
		check_ajax_referer( 'bracket_po_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bracket-post-order' ) );
		}

		if ( empty( $_POST['order'] ) ) {
			wp_send_json_error( __( 'No order data received.', 'bracket-post-order' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed then sanitized with absint below.
		parse_str( wp_unslash( $_POST['order'] ), $data );

		if ( empty( $data['post'] ) || ! is_array( $data['post'] ) ) {
			wp_send_json_error( __( 'Invalid order data.', 'bracket-post-order' ) );
		}

		$post_ids = array_map( 'absint', $data['post'] );

		global $wpdb;

		// Get the current menu_order values for the dragged posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Immediate write follows.
		$current_orders = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholder count for IN clause.
			$wpdb->prepare(
				"SELECT ID, menu_order FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ')',
				...$post_ids
			),
			OBJECT_K
		);

		// Collect and sort the existing menu_order values.
		$order_values = wp_list_pluck( $current_orders, 'menu_order' );
		sort( $order_values, SORT_NUMERIC );

		// Deduplicate: if there are duplicate menu_order values, generate unique sequential values
		// starting from the minimum to prevent non-deterministic sort ties.
		if ( count( $order_values ) !== count( array_unique( $order_values ) ) ) {
			$min_val = ! empty( $order_values ) ? (int) $order_values[0] : 0;
			$order_values = range( $min_val, $min_val + count( $post_ids ) - 1 );
		}

		// Reassign sorted values in the new drag order.
		foreach ( $post_ids as $i => $post_id ) {
			$new_order = isset( $order_values[ $i ] ) ? $order_values[ $i ] : $i;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk reorder; clean_post_cache() called after.
			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => $new_order ],
				[ 'ID' => $post_id ],
				[ '%d' ],
				[ '%d' ]
			);
			clean_post_cache( $post_id );
		}

		do_action( 'bracket_po_global_order_updated', $post_ids );

		wp_send_json_success();
	}

	/**
	 * Save per-term post order.
	 */
	public static function save_term_post_order() {
		check_ajax_referer( 'bracket_po_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bracket-post-order' ) );
		}

		if ( empty( $_POST['order'] ) || empty( $_POST['term_id'] ) ) {
			wp_send_json_error( __( 'Missing order data or term ID.', 'bracket-post-order' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed then sanitized with absint below.
		parse_str( wp_unslash( $_POST['order'] ), $data );

		if ( empty( $data['post'] ) || ! is_array( $data['post'] ) ) {
			wp_send_json_error( __( 'Invalid order data.', 'bracket-post-order' ) );
		}

		$term_id  = absint( $_POST['term_id'] );
		$post_ids = array_map( 'absint', $data['post'] );

		// Validate term belongs to an enabled taxonomy.
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( __( 'Invalid term.', 'bracket-post-order' ) );
		}

		$enabled_taxonomies = Bracket_PO_Core::get_enabled_taxonomies();
		if ( ! in_array( $term->taxonomy, $enabled_taxonomies, true ) ) {
			wp_send_json_error( __( 'Taxonomy not enabled.', 'bracket-post-order' ) );
		}

		// Merge with existing saved order so paginated views don't discard other pages.
		$existing_order = Bracket_PO_Core::get_term_order( $term_id );

		if ( ! empty( $existing_order ) ) {
			// Remove the visible posts from their old positions in the saved array.
			$remaining = array_diff( $existing_order, $post_ids );
			// Append non-visible posts after the reordered visible ones.
			$post_ids = array_values( array_merge( $post_ids, $remaining ) );
		}

		update_term_meta( $term_id, '_bracket_po_order', $post_ids );

		do_action( 'bracket_po_term_post_order_updated', $term_id, $post_ids );

		wp_send_json_success();
	}

	/**
	 * Save taxonomy term order.
	 */
	public static function save_term_order() {
		check_ajax_referer( 'bracket_po_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bracket-post-order' ) );
		}

		if ( empty( $_POST['order'] ) ) {
			wp_send_json_error( __( 'No order data received.', 'bracket-post-order' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed then sanitized with absint below.
		parse_str( wp_unslash( $_POST['order'] ), $data );

		if ( empty( $data['tag'] ) || ! is_array( $data['tag'] ) ) {
			wp_send_json_error( __( 'Invalid order data.', 'bracket-post-order' ) );
		}

		$term_ids = array_map( 'absint', $data['tag'] );

		global $wpdb;

		// Get current term_order values.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Immediate write follows; caching would be stale.
		$current_orders = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholder count for IN clause.
			$wpdb->prepare(
				"SELECT term_id, term_order FROM {$wpdb->terms} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $term_ids ), '%d' ) ) . ')',
				...$term_ids
			),
			OBJECT_K
		);

		// Collect and sort existing term_order values.
		$order_values = wp_list_pluck( $current_orders, 'term_order' );
		sort( $order_values, SORT_NUMERIC );

		// If all values are 0, generate sequential values.
		if ( count( array_unique( $order_values ) ) === 1 && reset( $order_values ) == 0 ) {
			$order_values = range( 0, count( $term_ids ) - 1 );
		}

		// Reassign sorted values in new order.
		foreach ( $term_ids as $i => $term_id ) {
			$new_order = isset( $order_values[ $i ] ) ? $order_values[ $i ] : $i;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk reorder requires direct updates; clean_term_cache() called after.
			$wpdb->update(
				$wpdb->terms,
				[ 'term_order' => $new_order ],
				[ 'term_id' => $term_id ],
				[ '%d' ],
				[ '%d' ]
			);
			clean_term_cache( $term_id );
		}

		do_action( 'bracket_po_term_order_updated', $term_ids );

		wp_send_json_success();
	}

	/**
	 * Reset post order (global or per-term).
	 */
	public static function reset_order() {
		check_ajax_referer( 'bracket_po_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bracket-post-order' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$reset_type = isset( $_POST['reset_type'] ) ? sanitize_key( $_POST['reset_type'] ) : '';
		$sort_by    = isset( $_POST['sort_by'] ) ? sanitize_key( $_POST['sort_by'] ) : 'date_desc';

		if ( $reset_type === 'term' ) {
			$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
			if ( ! $term_id ) {
				wp_send_json_error( __( 'Invalid term ID.', 'bracket-post-order' ) );
			}

			delete_term_meta( $term_id, '_bracket_po_order' );

			/**
			 * Fires after per-term post order is reset.
			 *
			 * @param int $term_id The term ID.
			 */
			do_action( 'bracket_po_term_post_order_reset', $term_id );

			wp_send_json_success();
		}

		if ( $reset_type === 'global' ) {
			$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';
			if ( empty( $post_type ) ) {
				wp_send_json_error( __( 'Invalid post type.', 'bracket-post-order' ) );
			}

			$enabled = Bracket_PO_Core::get_enabled_post_types();
			if ( ! in_array( $post_type, $enabled, true ) ) {
				wp_send_json_error( __( 'Post type not enabled.', 'bracket-post-order' ) );
			}

			global $wpdb;

			$statuses_arr = array( 'publish', 'pending', 'draft', 'private', 'future' );
			$placeholders = implode( ', ', array_fill( 0, count( $statuses_arr ), '%s' ) );

			switch ( $sort_by ) {
				case 'date_asc':
					$order_col = 'post_date ASC';
					break;
				case 'title_asc':
					$order_col = 'post_title ASC';
					break;
				case 'title_desc':
					$order_col = 'post_title DESC';
					break;
				case 'date_desc':
				default:
					$order_col = 'post_date DESC';
					break;
			}

			$order_col = esc_sql( $order_col );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reset requires fresh data.
			$all_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders}) ORDER BY {$order_col}, ID ASC",
					array_merge( array( $post_type ), $statuses_arr )
				)
			);

			foreach ( $all_ids as $i => $pid ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk reset; cache cleared after.
				$wpdb->update(
					$wpdb->posts,
					[ 'menu_order' => $i ],
					[ 'ID' => (int) $pid ],
					[ '%d' ],
					[ '%d' ]
				);
				clean_post_cache( (int) $pid );
			}

			// Also clear per-term post order for taxonomies linked to this post type.
			$enabled_taxonomies   = Bracket_PO_Core::get_enabled_taxonomies();
			$post_type_taxonomies = get_object_taxonomies( $post_type );
			$taxonomies_to_clear  = array_intersect( $enabled_taxonomies, $post_type_taxonomies );

			if ( ! empty( $taxonomies_to_clear ) ) {
				foreach ( $taxonomies_to_clear as $taxonomy ) {
					$terms = get_terms( [
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'fields'     => 'ids',
					] );

					if ( ! is_wp_error( $terms ) ) {
						foreach ( $terms as $tid ) {
							delete_term_meta( $tid, '_bracket_po_order' );
						}
					}
				}
			}

			/**
			 * Fires after global post order is reset.
			 *
			 * @param string $post_type The post type.
			 * @param string $sort_by   The sort method used.
			 */
			do_action( 'bracket_po_global_order_reset', $post_type, $sort_by );

			wp_send_json_success();
		}

		wp_send_json_error( __( 'Invalid reset type.', 'bracket-post-order' ) );
	}
}
