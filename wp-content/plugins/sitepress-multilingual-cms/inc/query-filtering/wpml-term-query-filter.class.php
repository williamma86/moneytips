<?php

class WPML_Term_Query_Filter{

	public function __construct(){
		add_filter( 'list_terms_exclusions', array( $this, 'exclude_other_terms' ), 1, 2 );

	}

	private function exclusions_necessary($args) {

		return !( filter_input ( INPUT_GET, 'lang' ) === 'all'
		          || ( isset( $args[ '_icl_show_all_langs' ] )
		               && $args[ '_icl_show_all_langs' ] )
		);
	}

	private function current_tax_translated(){
		global $pagenow, $sitepress;

		$taxonomy = filter_input(INPUT_GET, 'taxonomy' );
		$taxonomy = $taxonomy === null && isset( $args[ 'taxonomy' ] ) ? $args[ 'taxonomy' ] : $taxonomy;
		$taxonomy = $taxonomy === null
		            && filter_input ( INPUT_POST, 'action' ) === 'get-tagcloud'
			? filter_input(INPUT_POST, 'tax' ) : $taxonomy;

		if ( $taxonomy === null && in_array ( $pagenow, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) {
			//Limit to first 4 stack frames, since 3 is the highest index we use
			$debug_backtrace = $sitepress->get_backtrace ( 4, false, true );
			$taxonomy = isset( $debug_backtrace[ 3 ][ 'args' ][ 0 ] ) ? $debug_backtrace[ 3 ][ 'args' ][ 0 ]  : 'post_tag';
		}

		return !$taxonomy || !$sitepress->is_translated_taxonomy ( $taxonomy ) ? false : $taxonomy;
	}

	function exclude_other_terms( $exclusions, $args ) {

		if ( $this->exclusions_necessary($args) === false || ($taxonomy = $this->current_tax_translated()) === false) {
			return $exclusions;
		}

		global $sitepress;

		if ( ( $tag_id = filter_input ( INPUT_GET, 'tag_ID' ) ) !== null ) {
			/** @var WPML_Term_Translation $wpml_term_translations */
			global $wpml_term_translations;
			$this_lang = $wpml_term_translations->lang_code_by_termid ( $tag_id );
		} elseif ( ($current_lang = $sitepress->get_current_language()) !== ($default_language = $sitepress->get_default_language () ) ) {
			$this_lang = $current_lang;
		} elseif ( ( $post_id = filter_input ( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) ) !== null ) {
			global $wpml_post_translations;
			$posts_lang_code = $wpml_post_translations->get_element_lang_code ( $post_id );
			$this_lang = $posts_lang_code ? $posts_lang_code : $default_language;
		} elseif (
			isset( $_SERVER[ 'HTTP_REFERER' ] )
			&& in_array ( filter_input ( INPUT_POST, 'action' ), array( 'get-tagcloud', 'menu-quick-search' ), true )
		) {
			$url_query = (string)parse_url ( $_SERVER[ 'HTTP_REFERER' ], PHP_URL_QUERY );
			parse_str ( $url_query, $qvars );
			$this_lang = isset( $qvars[ 'lang' ] ) ? $qvars[ 'lang' ] : $default_language;
		} else {
			$this_lang = $default_language;
		}
		if ( $this_lang !== 'all' ) {

			$exclude_snippet = $this->get_exclude_snippet($taxonomy, $this_lang);
			$exclusions_add = " AND tt.term_taxonomy_id NOT IN ({$exclude_snippet})";
			$exclusions     = (bool) $exclusions === false ? $exclusions_add : $exclusions . $exclusions_add;
		}
		return $exclusions;
	}

	private function get_exclude_snippet($taxonomy, $language_code){
		global $wpdb;

		return $wpdb->prepare (
			" SELECT t.term_taxonomy_id
              FROM {$wpdb->term_taxonomy} t
              LEFT JOIN {$wpdb->prefix}icl_translations i
                ON t.term_taxonomy_id = i.element_id
                  AND CONCAT('tax_',t.taxonomy ) = i.element_type
              WHERE t.taxonomy = %s
                AND i.language_code <> %s",
			$taxonomy,
			$language_code
		);
	}

}