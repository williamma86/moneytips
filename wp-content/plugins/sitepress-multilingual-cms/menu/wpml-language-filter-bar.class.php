<?php

abstract class WPML_Language_Filter_Bar{

	protected $default_language;
	protected $active_languages;
	protected $current_language;

	public function __construct( $default_language ) {
		$this->default_language = $default_language;
	}

	protected function init() {
		global $sitepress;

		if ( !isset( $this->active_languages[ 'all' ] ) ) {
			$this->current_language          = $sitepress->get_current_language ();
			$this->active_languages          = $sitepress->get_active_languages ();
			$this->active_languages[ 'all' ] = array( 'display_name' => __ ( 'All languages', 'sitepress' ) );
		}
	}

	protected function sanitize_request() {
		global $wp_post_types, $wp_taxonomies;
		$taxonomy  = (string) filter_input ( INPUT_GET, 'taxonomy', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$taxonomy  = $taxonomy !== '' && isset( $wp_taxonomies[ $taxonomy ] ) ? $taxonomy : '';
		$post_type = (string) filter_input ( INPUT_GET, 'post_type' );
		$post_type = $post_type !== '' && isset( $wp_post_types[ $post_type ] ) ? $post_type : '';

		return array( 'req_tax' => $taxonomy, 'req_ptype' => $post_type );
	}

	private function generate_counts_array($data){
		$languages = array( 'all' => 0 );
		foreach ( $data as $language_count ) {
			$languages[ $language_count->language_code ] = $language_count->c;
			$languages[ 'all' ] += $language_count->c;
		}

		return $languages;
	}

	protected abstract function get_count_data($element_type);

	protected function extra_conditions_snippet(){

		return " AND t.language_code IN (" . wpml_prepare_in( array_keys( $this->active_languages ) ) . ")
				 GROUP BY language_code";
	}

	protected function get_counts($element_type){

		return $this->generate_counts_array ( $this->get_count_data($element_type) );
	}
}