<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bracket_PO_Query {

	public static function init() {
		add_action( 'pre_get_posts', [ __CLASS__, 'set_menu_order' ] );
		add_filter( 'posts_clauses', [ __CLASS__, 'apply_term_post_order' ], 10, 2 );

		// Taxonomy term ordering
		add_filter( 'get_terms_orderby', [ __CLASS__, 'filter_terms_orderby' ], 10, 3 );
		add_filter( 'wp_get_object_terms', [ __CLASS__, 'sort_object_terms' ], 10, 4 );
	}

	/**
	 * Set menu_order as default ordering for enabled post types.
	 *
	 * @param WP_Query $query
	 */
	public static function set_menu_order( $query ) {
		// Skip REST API requests that set their own order
		if ( $query->get( 'suppress_filters' ) ) {
			return;
		}

		// Don't override REST API requests.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$post_type = $query->get( 'post_type' );

		// On frontend taxonomy archives, post_type may be empty.
		// Detect enabled taxonomy query vars and infer the post type.
		if ( empty( $post_type ) && ! is_admin() ) {
			$enabled_taxonomies = Bracket_PO_Core::get_enabled_taxonomies();
			$enabled            = Bracket_PO_Core::get_enabled_post_types();

			foreach ( $enabled_taxonomies as $taxonomy ) {
				$tax_obj = get_taxonomy( $taxonomy );
				if ( ! $tax_obj || ! $tax_obj->query_var ) {
					continue;
				}
				if ( $query->get( $tax_obj->query_var ) ) {
					$matching = array_intersect( $tax_obj->object_type, $enabled );
					if ( ! empty( $matching ) ) {
						$query->set( 'orderby', 'menu_order' );
						$query->set( 'order', 'ASC' );
						return;
					}
				}
			}

			// Also check via queried object for taxonomy archives.
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term ) {
				$tax_obj = get_taxonomy( $queried->taxonomy );
				if ( $tax_obj ) {
					$matching = array_intersect( $tax_obj->object_type, $enabled );
					if ( count( $matching ) === 1 ) {
						$post_type = reset( $matching );
					}
				}
			}

			if ( empty( $post_type ) ) {
				return;
			}
		}

		if ( empty( $post_type ) ) {
			return;
		}

		// Normalize to string for single post type queries
		if ( is_array( $post_type ) ) {
			if ( count( $post_type ) !== 1 ) {
				return;
			}
			$post_type = reset( $post_type );
		}

		$enabled = Bracket_PO_Core::get_enabled_post_types();
		if ( ! in_array( $post_type, $enabled, true ) ) {
			return;
		}

		// Don't override explicit orderby (from user, column header click, etc.)
		$explicit_orderby = $query->get( 'orderby' );
		if ( $explicit_orderby && $explicit_orderby !== 'menu_order' ) {
			return;
		}

		// On admin edit.php, also check URL params
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading column sort parameter from URL.
		if ( is_admin() && ! empty( $_GET['orderby'] ) ) {
			return;
		}

		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Apply per-term post ordering via SQL FIELD() when a tax_query targets
	 * a single term from an enabled taxonomy and orderby is menu_order.
	 *
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query
	 * @return array Modified clauses.
	 */
	public static function apply_term_post_order( $clauses, $query ) {
		if ( $query->get( 'suppress_filters' ) ) {
			return $clauses;
		}

		// Don't modify REST API queries.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $clauses;
		}

		// Only apply when ordering by menu_order.
		$orderby = $query->get( 'orderby' );
		if ( $orderby !== 'menu_order' ) {
			return $clauses;
		}

		// Extract single term ID from tax_query
		$term_data = self::extract_single_term( $query );
		if ( ! $term_data ) {
			return $clauses;
		}

		$term_id  = $term_data['term_id'];
		$taxonomy = $term_data['taxonomy'];

		// Check if this taxonomy is enabled for per-term ordering
		$enabled_taxonomies = Bracket_PO_Core::get_enabled_taxonomies();
		if ( ! in_array( $taxonomy, $enabled_taxonomies, true ) ) {
			return $clauses;
		}

		// Get saved order
		$ordered_ids = Bracket_PO_Core::get_term_order( $term_id );

		/**
		 * Filter the retrieved per-term post order before it's applied.
		 *
		 * @param array $ordered_ids Array of post IDs in order.
		 * @param int   $term_id     The term ID.
		 */
		$ordered_ids = apply_filters( 'bracket_po_get_term_post_order', $ordered_ids, $term_id );

		if ( empty( $ordered_ids ) ) {
			return $clauses;
		}

		/**
		 * Filter whether per-term post order should be applied to this query.
		 *
		 * @param bool     $apply    Whether to apply ordering.
		 * @param int      $term_id  The term ID.
		 * @param WP_Query $query    The query object.
		 */
		if ( ! apply_filters( 'bracket_po_apply_term_post_order', true, $term_id, $query ) ) {
			return $clauses;
		}

		global $wpdb;

		// Build FIELD() clause — ordered posts appear first in saved order,
		// new/unordered posts appear last sorted by date (newest first).
		$ids_str = implode( ',', array_map( 'absint', $ordered_ids ) );
		$clauses['orderby'] = "FIELD({$wpdb->posts}.ID, {$ids_str}) = 0, FIELD({$wpdb->posts}.ID, {$ids_str}) ASC, {$wpdb->posts}.post_date DESC";

		return $clauses;
	}

	/**
	 * Extract a single term ID and taxonomy from a WP_Query's tax_query.
	 *
	 * @param WP_Query $query
	 * @return array|false [ 'term_id' => int, 'taxonomy' => string ] or false.
	 */
	private static function extract_single_term( $query ) {
		$tax_query = $query->get( 'tax_query' );

		// Check for simple taxonomy query vars (e.g., 'category_name', 'tag', custom taxonomy slugs)
		$enabled_taxonomies = Bracket_PO_Core::get_enabled_taxonomies();
		foreach ( $enabled_taxonomies as $taxonomy ) {
			$tax_obj = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				continue;
			}

			$query_var = $tax_obj->query_var;
			if ( $query_var ) {
				$term_slug = $query->get( $query_var );
				if ( $term_slug ) {
					$term = get_term_by( 'slug', $term_slug, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						return [
							'term_id'  => $term->term_id,
							'taxonomy' => $taxonomy,
						];
					}
				}
			}
		}

		// Check tax_query array
		if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
			return false;
		}

		// Only handle simple single-term queries
		$tax_clauses = array_filter( $tax_query, 'is_array' );

		if ( count( $tax_clauses ) !== 1 ) {
			return false;
		}

		$clause = reset( $tax_clauses );

		if ( empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) {
			return false;
		}

		// Must be a single term
		$terms = (array) $clause['terms'];
		if ( count( $terms ) !== 1 ) {
			return false;
		}

		$taxonomy = $clause['taxonomy'];
		$field = isset( $clause['field'] ) ? $clause['field'] : 'term_id';
		$term_value = reset( $terms );

		// Resolve to term_id
		switch ( $field ) {
			case 'term_id':
			case 'id':
				$term_id = absint( $term_value );
				break;
			case 'slug':
				$term = get_term_by( 'slug', $term_value, $taxonomy );
				$term_id = $term ? $term->term_id : 0;
				break;
			case 'name':
				$term = get_term_by( 'name', $term_value, $taxonomy );
				$term_id = $term ? $term->term_id : 0;
				break;
			default:
				$term_id = absint( $term_value );
		}

		if ( ! $term_id ) {
			return false;
		}

		// Check for OR relation (not supported)
		if ( isset( $tax_query['relation'] ) && strtoupper( $tax_query['relation'] ) === 'OR' ) {
			return false;
		}

		return [
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
		];
	}

	/**
	 * Override terms orderby for enabled taxonomies to use term_order.
	 *
	 * @param string $orderby   Current orderby SQL.
	 * @param array  $query_vars Query vars.
	 * @param array  $taxonomies Taxonomy names.
	 * @return string
	 */
	public static function filter_terms_orderby( $orderby, $query_vars, $taxonomies ) {
		$enabled = Bracket_PO_Core::get_enabled_term_order_taxonomies();
		if ( empty( $enabled ) ) {
			return $orderby;
		}

		// Only override if the query is for a single enabled taxonomy and no explicit orderby
		if ( ! is_array( $taxonomies ) || count( $taxonomies ) !== 1 ) {
			return $orderby;
		}

		$taxonomy = reset( $taxonomies );
		if ( ! in_array( $taxonomy, $enabled, true ) ) {
			return $orderby;
		}

		// Don't override explicit orderby set by the caller (except 'name' which is WP default)
		if ( isset( $query_vars['orderby'] ) && $query_vars['orderby'] !== 'name' && $query_vars['orderby'] !== 'term_order' ) {
			return $orderby;
		}

		// Verify the term_order column exists to prevent fatal SQL errors.
		if ( ! self::term_order_column_exists() ) {
			return $orderby;
		}

		return 't.term_order';
	}

	/**
	 * Check if the term_order column exists in wp_terms.
	 * Result is cached for the duration of the request.
	 *
	 * @return bool
	 */
	private static function term_order_column_exists() {
		static $exists = null;

		if ( $exists !== null ) {
			return $exists;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->terms} LIKE 'term_order'" );
		$exists = ! empty( $row );

		return $exists;
	}

	/**
	 * Sort object terms by term_order for enabled taxonomies.
	 *
	 * @param array  $terms      Terms.
	 * @param array  $object_ids Object IDs.
	 * @param string $taxonomies Taxonomies.
	 * @param array  $args       Arguments.
	 * @return array
	 */
	public static function sort_object_terms( $terms, $object_ids, $taxonomies, $args ) {
		$enabled = Bracket_PO_Core::get_enabled_term_order_taxonomies();
		if ( empty( $enabled ) || empty( $terms ) ) {
			return $terms;
		}

		// Normalize taxonomies
		if ( is_string( $taxonomies ) ) {
			$taxonomies = [ $taxonomies ];
		}

		// Check if any of the queried taxonomies are enabled
		$has_enabled = ! empty( array_intersect( (array) $taxonomies, $enabled ) );
		if ( ! $has_enabled ) {
			return $terms;
		}

		// Verify the term_order column exists.
		if ( ! self::term_order_column_exists() ) {
			return $terms;
		}

		// Don't override explicit orderby
		if ( ! empty( $args['orderby'] ) && $args['orderby'] !== 'term_order' ) {
			return $terms;
		}

		// Sort by term_order with stable tiebreaker.
		usort( $terms, function( $a, $b ) {
			$a_order = isset( $a->term_order ) ? (int) $a->term_order : 0;
			$b_order = isset( $b->term_order ) ? (int) $b->term_order : 0;
			if ( $a_order !== $b_order ) {
				return $a_order - $b_order;
			}
			return $a->term_id - $b->term_id;
		} );

		return $terms;
	}
}
