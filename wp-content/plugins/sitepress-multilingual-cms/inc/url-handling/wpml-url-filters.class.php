<?php

class WPML_URL_Filters {

	private $default_language;

	public function __construct() {
		$this->default_language          = icl_get_setting ( 'default_language' );

		if ( (bool) WPML_Root_Page::get_root_id () === true ) {
			add_filter ( 'page_link', array( $this, 'permalink_filter_root' ), 1, 2 );
		} else {
			add_filter ( 'page_link', array( $this, 'permalink_filter' ), 1, 2 );
		}

		// posts and pages links filters
		add_filter ( 'post_link', array( $this, 'permalink_filter' ), 1, 2 );
		add_filter ( 'post_type_link', array( $this, 'permalink_filter' ), 1, 2 );
		add_filter ( 'home_url', array( $this, 'home_url_filter' ), -10, 2 );
	}

	public function permalink_filter_root( $p, $pid ) {
		/* @var WPML_URL_Converter $wpml_url_converter */
		global $wpml_url_converter;

		$pid = is_object ( $pid ) ? $pid->ID : $pid;
		$p   = WPML_Root_Page::get_root_id () != $pid
			? $this->permalink_filter ( $p, $pid ) : $wpml_url_converter->get_abs_home ();

		return $p;
	}

	function permalink_filter( $p, $pid ) {
		/* @var WPML_URL_Converter $wpml_url_converter */
		global $wp_query, $sitepress, $wpml_post_translations, $wpml_url_converter;

		$pid       = is_object ( $pid ) ? $pid->ID : $pid;
		$post_type = isset( $p->post_type ) ? $p->post_type : get_post_type ( $pid );

		if ( !$sitepress->is_translated_post_type ( $post_type ) ) {
			return $p;
		}

		if (filter_input(INPUT_POST, 'action') === 'sample-permalink' ) { // check whether this is an autosaved draft
			$code = !isset($_SERVER[ 'HTTP_REFERER' ] ) ? $this->default_language : $wpml_url_converter->get_language_from_url( $_SERVER[ "HTTP_REFERER" ]);
		} else {
			$code = $wpml_post_translations->get_element_lang_code ( $pid );
		}

		$p = $wpml_url_converter->convert_url ( $p, $code );
		$p = isset( $wp_query ) && is_feed ()  ? str_replace ( "&lang=", "&#038;lang=", $p ) : $p;

		return $p;
	}

	public function home_url_filter($url){
		/* @var WPML_URL_Converter $wpml_url_converter */
		global $wpml_url_converter;

		return  $wpml_url_converter->convert_url($url, $wpml_url_converter->get_language_from_url( $_SERVER[ "REQUEST_URI" ]));
	}
}

global $wpml_url_filters;
$wpml_url_filters = new WPML_URL_Filters();