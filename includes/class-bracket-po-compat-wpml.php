<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPML compatibility for Bracket Post Order.
 *
 * Maps term IDs and filters post IDs to the current language
 * so per-term ordering works correctly in multilingual sites.
 */
class Bracket_PO_Compat_WPML {

	public static function init() {
		add_filter( 'bracket_po_get_term_post_order', [ __CLASS__, 'filter_term_post_order' ], 10, 2 );
	}

	/**
	 * Filter per-term post order for WPML compatibility.
	 *
	 * Maps the term ID to the default language term to retrieve the saved order,
	 * then filters post IDs to only include those in the current language.
	 *
	 * @param array $ordered_ids Array of post IDs in order.
	 * @param int   $term_id     The term ID.
	 * @return array Filtered array of post IDs.
	 */
	public static function filter_term_post_order( $ordered_ids, $term_id ) {
		if ( ! function_exists( 'wpml_object_id_filter' ) ) {
			return $ordered_ids;
		}

		$current_language = apply_filters( 'wpml_current_language', null );
		$default_language = apply_filters( 'wpml_default_language', null );

		// If we're on the default language, just filter post IDs.
		if ( $current_language === $default_language ) {
			return self::filter_posts_by_language( $ordered_ids, $current_language );
		}

		// Map term to default language to get the canonical saved order.
		$term_obj = get_term( $term_id );
		$term_taxonomy = $term_obj && ! is_wp_error( $term_obj ) ? $term_obj->taxonomy : 'category';
		$default_term_id = apply_filters( 'wpml_object_id', $term_id, $term_taxonomy, true, $default_language );

		if ( $default_term_id && $default_term_id !== $term_id ) {
			// Get order from the default language term.
			$default_order = Bracket_PO_Core::get_term_order( $default_term_id );
			if ( ! empty( $default_order ) && empty( $ordered_ids ) ) {
				$ordered_ids = $default_order;
			}
		}

		// Map post IDs to current language equivalents.
		$mapped_ids = [];
		foreach ( $ordered_ids as $post_id ) {
			$translated_id = apply_filters( 'wpml_object_id', $post_id, get_post_type( $post_id ), false, $current_language );
			if ( $translated_id ) {
				$mapped_ids[] = (int) $translated_id;
			}
		}

		return $mapped_ids;
	}

	/**
	 * Filter post IDs to only include those in the specified language.
	 *
	 * @param array  $post_ids Array of post IDs.
	 * @param string $language Language code.
	 * @return array Filtered post IDs.
	 */
	private static function filter_posts_by_language( $post_ids, $language ) {
		if ( empty( $post_ids ) || ! $language ) {
			return $post_ids;
		}

		$filtered = [];
		foreach ( $post_ids as $post_id ) {
			$post_language = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( is_array( $post_language ) && isset( $post_language['language_code'] ) && $post_language['language_code'] === $language ) {
				$filtered[] = $post_id;
			}
		}

		return $filtered;
	}
}
