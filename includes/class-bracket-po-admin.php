<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bracket_PO_Admin {

	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'refresh_order' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
		add_action( 'admin_notices', [ __CLASS__, 'conflict_notice' ] );
		add_action( 'admin_notices', [ __CLASS__, 'term_mode_notice' ] );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar_indicator' ], 999 );

		// Mark post type as stale when posts change.
		add_action( 'save_post', [ __CLASS__, 'mark_stale' ], 10, 2 );
		add_action( 'delete_post', [ __CLASS__, 'mark_stale_by_id' ] );
		add_action( 'wp_trash_post', [ __CLASS__, 'mark_stale_by_id' ] );

	}

	/**
	 * Mark a post type as stale when a post is created, updated, or trashed.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function mark_stale( $post_id, $post ) {
		// Skip autosaves and revisions — they don't affect ordering.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( $post->post_status === 'auto-draft' ) {
			return;
		}

		$enabled = Bracket_PO_Core::get_enabled_post_types();
		if ( in_array( $post->post_type, $enabled, true ) ) {
			set_transient( 'bracket_po_stale_' . $post->post_type, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Mark a post type as stale by post ID (for delete/trash hooks).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function mark_stale_by_id( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$enabled = Bracket_PO_Core::get_enabled_post_types();
		if ( in_array( $post->post_type, $enabled, true ) ) {
			set_transient( 'bracket_po_stale_' . $post->post_type, 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Refresh menu_order on admin_init only for stale post types.
	 *
	 * Only runs when a transient flag exists, then deletes it.
	 * Skips AJAX requests to avoid corrupting concurrent drag operations.
	 */
	public static function refresh_order() {
		// Don't refresh during AJAX — it would interfere with drag-and-drop saves.
		if ( wp_doing_ajax() ) {
			return;
		}

		$enabled = Bracket_PO_Core::get_enabled_post_types();
		foreach ( $enabled as $post_type ) {
			if ( get_transient( 'bracket_po_stale_' . $post_type ) ) {
				Bracket_PO_Core::refresh_post_type_order( $post_type );
				delete_transient( 'bracket_po_stale_' . $post_type );
			}
		}
	}

	/**
	 * Determine the current ordering mode on edit.php.
	 *
	 * @return string|false 'global', 'term', or false if not applicable.
	 */
	private static function get_mode() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'edit' ) {
			return false;
		}

		$post_type = $screen->post_type;
		$enabled_pts = Bracket_PO_Core::get_enabled_post_types();

		if ( ! in_array( $post_type, $enabled_pts, true ) ) {
			return false;
		}

		// If user explicitly sorted via column header, disable drag-and-drop
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading column sort parameter from URL.
		if ( ! empty( $_GET['orderby'] ) ) {
			return false;
		}

		$tax_filter = Bracket_PO_Core::is_taxonomy_filter_active();
		if ( $tax_filter ) {
			return 'term';
		}

		return 'global';
	}

	/**
	 * Enqueue scripts and styles on relevant admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_scripts( $hook ) {
		// Post list table (edit.php)
		if ( $hook === 'edit.php' ) {
			$mode = self::get_mode();
			if ( ! $mode ) {
				return;
			}

			$screen = get_current_screen();

			// Dequeue SCPO scripts to prevent conflict
			wp_dequeue_script( 'scporderjs' );
			wp_dequeue_script( 'scporder' );
			wp_dequeue_script( 'scpo-script' );

			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-touch-punch' );
			wp_enqueue_script(
				'bracket-po-sortable',
				BRACKET_PO_URL . 'assets/js/bracket-po-sortable.js',
				[ 'jquery', 'jquery-ui-sortable', 'jquery-touch-punch' ],
				BRACKET_PO_VERSION,
				true
			);

			$localize_data = [
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bracket_po_nonce' ),
				'mode'      => $mode,
				'term_id'   => 0,
				'term_name' => '',
				'post_type' => $screen->post_type,
				'i18n'      => [
					'order_saved'           => __( 'Order saved.', 'bracket-post-order' ),
					'undo'                  => __( 'Undo', 'bracket-post-order' ),
					'order_reverted'        => __( 'Order reverted.', 'bracket-post-order' ),
					'save_failed'           => __( 'Failed to save order.', 'bracket-post-order' ),
					'reset_confirm'         => __( 'Are you sure you want to reset the order? This cannot be undone.', 'bracket-post-order' ),
					'reset_success'         => __( 'Order has been reset.', 'bracket-post-order' ),
					'reset_failed'          => __( 'Failed to reset order.', 'bracket-post-order' ),
					'reset_order'           => __( 'Reset Order', 'bracket-post-order' ),
					'date_desc'             => __( 'Date (newest first)', 'bracket-post-order' ),
					'date_asc'              => __( 'Date (oldest first)', 'bracket-post-order' ),
					'title_asc'             => __( 'Title (A-Z)', 'bracket-post-order' ),
					'title_desc'            => __( 'Title (Z-A)', 'bracket-post-order' ),
					'keyboard_activated'    => __( 'Reorder mode activated. Use arrow keys to move, Enter to save, Escape to cancel.', 'bracket-post-order' ),
					'keyboard_moved_up'     => __( 'Moved up.', 'bracket-post-order' ),
					'keyboard_moved_down'   => __( 'Moved down.', 'bracket-post-order' ),
					'keyboard_saved'        => __( 'Position saved.', 'bracket-post-order' ),
					'keyboard_cancelled'    => __( 'Reorder cancelled.', 'bracket-post-order' ),
				],
			];

			if ( $mode === 'term' ) {
				$tax_filter = Bracket_PO_Core::is_taxonomy_filter_active();
				if ( $tax_filter ) {
					$localize_data['term_id']   = $tax_filter['term_id'];
					$localize_data['term_name'] = $tax_filter['term_name'];
				}
			}

			wp_localize_script( 'bracket-po-sortable', 'bracket_po_vars', $localize_data );

			wp_enqueue_style(
				'bracket-po-admin',
				BRACKET_PO_URL . 'assets/css/bracket-po-admin.css',
				[],
				BRACKET_PO_VERSION
			);

			return;
		}

		// Taxonomy term list (edit-tags.php)
		if ( $hook === 'edit-tags.php' ) {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$taxonomy = $screen->taxonomy;
			$enabled = Bracket_PO_Core::get_enabled_term_order_taxonomies();

			if ( ! in_array( $taxonomy, $enabled, true ) ) {
				return;
			}

			// Dequeue SCPO scripts to prevent conflict
			wp_dequeue_script( 'scporderjs' );
			wp_dequeue_script( 'scporder' );
			wp_dequeue_script( 'scpo-script' );

			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'jquery-touch-punch' );
			wp_enqueue_script(
				'bracket-po-taxonomy',
				BRACKET_PO_URL . 'assets/js/bracket-po-taxonomy.js',
				[ 'jquery', 'jquery-ui-sortable', 'jquery-touch-punch' ],
				BRACKET_PO_VERSION,
				true
			);

			wp_localize_script( 'bracket-po-taxonomy', 'bracket_po_tax_vars', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bracket_po_nonce' ),
				'i18n'     => [
					'order_saved'         => __( 'Term order saved.', 'bracket-post-order' ),
					'undo'                => __( 'Undo', 'bracket-post-order' ),
					'order_reverted'      => __( 'Order reverted.', 'bracket-post-order' ),
					'save_failed'         => __( 'Failed to save term order.', 'bracket-post-order' ),
					'keyboard_activated'  => __( 'Reorder mode activated. Use arrow keys to move, Enter to save, Escape to cancel.', 'bracket-post-order' ),
					'keyboard_moved_up'   => __( 'Moved up.', 'bracket-post-order' ),
					'keyboard_moved_down' => __( 'Moved down.', 'bracket-post-order' ),
					'keyboard_saved'      => __( 'Position saved.', 'bracket-post-order' ),
					'keyboard_cancelled'  => __( 'Reorder cancelled.', 'bracket-post-order' ),
				],
			] );

			wp_enqueue_style(
				'bracket-po-admin',
				BRACKET_PO_URL . 'assets/css/bracket-po-admin.css',
				[],
				BRACKET_PO_VERSION
			);
		}
	}

	/**
	 * Show conflict notice if Simple Custom Post Order is active.
	 */
	public static function conflict_notice() {
		if ( ! class_exists( 'SCPO_Engine' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, [ 'edit', 'edit-tags', 'plugins' ], true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			__( '<strong>Bracket Post Order:</strong> Simple Custom Post Order is also active. To avoid conflicts, please deactivate Simple Custom Post Order.', 'bracket-post-order' ),
			[ 'strong' => [] ]
		);
		echo '</p></div>';
	}

	/**
	 * Show info notice when in term ordering mode.
	 */
	public static function term_mode_notice() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'edit' ) {
			return;
		}

		$mode = self::get_mode();
		if ( $mode !== 'term' ) {
			return;
		}

		$tax_filter = Bracket_PO_Core::is_taxonomy_filter_active();
		if ( ! $tax_filter ) {
			return;
		}

		$post_type_obj = get_post_type_object( $screen->post_type );
		$pt_label = $post_type_obj ? $post_type_obj->labels->name : $screen->post_type;

		printf(
			'<div class="notice notice-info bracket-po-term-notice"><p>%s</p></div>',
			sprintf(
				/* translators: 1: post type label, 2: term name */
				esc_html__( 'Drag to reorder %1$s in "%2$s". Changes save automatically.', 'bracket-post-order' ),
				esc_html( $pt_label ),
				esc_html( $tax_filter['term_name'] )
			)
		);
	}

	/**
	 * Add ordering mode indicator to admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public static function admin_bar_indicator( $wp_admin_bar ) {
		$mode = self::get_mode();
		if ( ! $mode ) {
			return;
		}

		$label = $mode === 'term'
			? __( 'BPO: Per-Term Mode', 'bracket-post-order' )
			: __( 'BPO: Global Mode', 'bracket-post-order' );

		$wp_admin_bar->add_node( [
			'id'    => 'bracket-po-mode',
			'title' => $label,
			'href'  => admin_url( 'options-general.php?page=bracket-post-order' ),
			'meta'  => [ 'class' => 'bracket-po-admin-bar-mode' ],
		] );
	}

}
