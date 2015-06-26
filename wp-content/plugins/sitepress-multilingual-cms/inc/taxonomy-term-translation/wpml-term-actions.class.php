<?php

class WPML_Term_Actions{

	private function save_term_requested_lang($tt_id, $post_action, $taxonomy){
		global $sitepress, $wpml_post_translations;

		$term_lang   = $sitepress->get_current_language ();

		if ( filter_input(INPUT_POST, '_ajax_nonce') !== null && $post_action === 'add-' . $taxonomy ) {
			$referrer = isset( $_SERVER[ 'HTTP_REFERER' ] ) ? $_SERVER[ 'HTTP_REFERER' ] : '';
			parse_str ( (string) parse_url ( $referrer, PHP_URL_QUERY ), $qvars );
			$term_lang = !empty( $qvars[ 'post' ] ) && $sitepress->is_translated_post_type ( get_post_type ( $qvars[ 'post' ] ) )
				? $wpml_post_translations->get_element_lang_code ( $qvars[ 'post' ] )
				: ( isset( $qvars[ 'lang' ] ) ? $qvars[ 'lang' ] : $term_lang );
		}
		$icl_post_lang = filter_input ( INPUT_POST, 'icl_post_language' );
		$term_lang     = $post_action === 'editpost' && $icl_post_lang ? $icl_post_lang : $term_lang;
		$term_lang     = $post_action === 'post-quickpress-publish' ? $sitepress->get_default_language () : $term_lang;
		$term_lang     = $post_action === 'inline-save-tax'
			? $sitepress->get_language_for_element ( $tt_id, 'tax_' . $taxonomy ) : $term_lang;
		$term_lang     = $post_action === 'inline-save'
			? $wpml_post_translations->get_element_lang_code ( filter_input ( INPUT_POST, 'post_ID', FILTER_SANITIZE_NUMBER_INT ) ) : $term_lang;
		$term_lang     = $term_lang
			? $term_lang : filter_input ( INPUT_POST, 'icl_tax' . $taxonomy . '_language', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$term_lang     = $term_lang ? $term_lang : $sitepress->get_current_language ();
		$term_lang     = apply_filters ( 'wpml_create_term_lang', $term_lang );
		$term_lang     = $sitepress->is_active_language ( $term_lang ) ? $term_lang
			: $sitepress->get_default_language ();

		return $term_lang;
	}

	public function save_term_actions( $cat_id, $tt_id, $taxonomy ) {
		global $sitepress;

		if ( !$sitepress->is_translated_taxonomy ( $taxonomy ) ) {
			return;
		};

		$post_action = filter_input ( INPUT_POST, 'action' );
		$term_lang = $this->save_term_requested_lang ( $tt_id, $post_action, $taxonomy );
		$trid      = filter_input ( INPUT_POST, 'icl_trid', FILTER_SANITIZE_NUMBER_INT );
		$trid      = $trid !== null && filter_input ( INPUT_POST, 'icl_tax' . $taxonomy . '_language' ) ? $trid : null;
		$trid      = $trid ? $trid : $sitepress->get_element_trid ( $tt_id, 'tax_' . $taxonomy );

		$src_ttid     = filter_input ( INPUT_POST, 'icl_translation_of', FILTER_VALIDATE_INT );
		$src_language = $sitepress->get_language_for_element ( $src_ttid, 'tax_' . $taxonomy );

		$sitepress->set_element_language_details ( $tt_id, 'tax_' . $taxonomy, $trid, $term_lang, $src_language );
		WPML_Terms_Translations::sync_parent_child_relations ( $taxonomy, $term_lang );
	}
}