<?php
require ICL_PLUGIN_PATH . '/inc/post-translation/wpml-post-duplication.class.php';
require ICL_PLUGIN_PATH . '/inc/post-translation/wpml-admin-post-actions.class.php';

class WPML_Post_Translation extends WPML_Element_Translation {

	public function get_original_post_status( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'post_status', $source_lang_code );
	}

	public function get_original_post_date( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'post_date', $source_lang_code );
	}

	public function get_original_post_ID($trid, $source_lang_code = null){

		return $this->get_original_post_attr ( $trid, 'ID', $source_lang_code );
	}

	public function get_original_menu_order($trid, $source_lang_code = null){

		return $this->get_original_post_attr ( $trid, 'menu_order', $source_lang_code );
	}

	public function get_original_comment_status($trid, $source_lang_code = null){

		return $this->get_original_post_attr ( $trid, 'comment_status', $source_lang_code );
	}

	public function get_original_ping_status($trid, $source_lang_code = null){

		return $this->get_original_post_attr ( $trid, 'ping_status', $source_lang_code );
	}

	public function get_original_post_format( $trid, $source_lang_code = null ) {

		return get_post_format ( $this->get_original_post_ID ( $trid, $source_lang_code ) );
	}

	private function get_original_post_attr($trid, $attribute, $source_lang_code){
		global $wpdb;

		$legal_attributes = array(
			'post_status',
			'post_date',
			'menu_order',
			'comment_status',
			'ping_status',
			'ID'
		);
		$res = false;
		if ( in_array( $attribute, $legal_attributes, true ) ) {
			$attribute = 'p.' . $attribute;
			$source_snippet = $source_lang_code === null
				? " AND t.source_language_code IS NULL "
				: $wpdb->prepare ( " AND t.language_code = %s ", $source_lang_code );
			$res = $wpdb->get_var (
				$wpdb->prepare (
					"SELECT {$attribute}
					 {$this->element_join}
					 WHERE t.trid=%d
					{$source_snippet}
					LIMIT 1",
					$trid
				)
			);
		}

		return $res;
	}

	protected function get_element_join() {
		global $wpdb;

		return "FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->posts} p
					ON t.element_id = p.ID
						AND t.element_type = CONCAT('post_', p.post_type)";
	}

	public function is_translated_type( $post_type ) {
		global $sitepress;

		return $sitepress->is_translated_post_type( $post_type );
	}

	/**
	 * @param WP_Post $post
	 *
	 * @return string[] all language codes the post can be translated into
	 */
	public function get_allowed_target_langs( $post ) {
		global $sitepress;

		$active_languages = $sitepress->get_active_languages();
		$can_translate    = array_keys( $active_languages );
		$can_translate    = array_diff( $can_translate,
		                                array( $this->get_element_lang_code( $post->ID ) ) );

		return apply_filters( 'wpml_allowed_target_langs', $can_translate, $post->ID, 'post' );
	}
}
