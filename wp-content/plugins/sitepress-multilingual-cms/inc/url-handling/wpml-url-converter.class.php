<?php

abstract class WPML_URL_Converter {

	protected $default_language;
	protected $active_languages;
	protected $current_lang;
	protected $absolute_home;
	protected $cache;
	protected $hidden_languages;

	public function __construct($default_language, $hidden_languages){
		global $wpml_language_resolution;
		add_filter( 'term_link', array( $this, 'tax_permalink_filter' ), 1, 3 );
		$this->absolute_home = $this->get_abs_home();
		$this->default_language = $default_language;
		$this->hidden_languages = (array)$hidden_languages;
		$this->active_languages = $wpml_language_resolution->get_active_language_codes();
	}

	public function is_url_admin($url){
		$url_query_parts = parse_url ( $url );

		return isset( $url_query_parts[ 'path' ] )
		       && strpos ( wpml_strip_subdir_from_url($url_query_parts[ 'path' ]), '/wp-admin' ) === 0;
	}

	protected function lang_by_param( $url, $only_admin = true ) {

		if(isset($this->cache[$url])){
			return $this->cache[$url];
		}

		$url_query_parts = parse_url ( $url );
		$url_query       = ($only_admin === false
		                       || isset( $url_query_parts[ 'path' ] )
		                          && strpos ( $url_query_parts[ 'path' ], '/wp-admin' ) === 0)
		                   && isset( $url_query_parts[ 'query' ] )
			? untrailingslashit($url_query_parts[ 'query' ]) : null;
		if ( isset( $url_query ) ) {
			parse_str ( $url_query, $vars );
			if ( isset( $vars[ 'lang' ] )
			     && ($only_admin === true && $vars['lang'] === 'all' || in_array ( $vars[ 'lang' ], $this->active_languages, true ) )) {
				$lang = $vars[ 'lang' ];
			}
		}

		$lang = isset( $lang ) ? $lang : null;
		$this->cache[ $url ] = $lang;

		return $lang;
	}

	public function get_abs_home() {
		global $wpdb;

		$this->absolute_home = $this->absolute_home
			? $this->absolute_home :$wpdb->get_var("SELECT option_value
													FROM {$wpdb->options}
													WHERE option_name = 'home'
													LIMIT 1");

		return $this->absolute_home;
	}

	public function convert_url( $url, $lang_code = false ) {
		if ( !$url ) {
			return false;
		}

		$this->current_lang = $this->current_lang
			? $this->current_lang : $this->get_language_from_url( $_SERVER[ "REQUEST_URI" ]);
		$current_language = $this->current_lang;
		$lang_code        = $lang_code ? $lang_code : $current_language;

		$cache_key_args = array( $url, $lang_code, $current_language );
		$cache_key      = md5 ( wp_json_encode ( $cache_key_args ) );
		$cache_group    = 'convert_url';

		$cache_found = false;
		$new_url     = wp_cache_get ( $cache_key, $cache_group, false, $cache_found );

		if ( !$cache_found ) {
			$new_url = $this->convert_url_string ( $url, $lang_code );
			wp_cache_set ( $cache_key, $new_url, $cache_group );
		}

		return $new_url;
	}

	public function get_language_from_url( $url ) {
		if(isset($this->cache[$url])){
			return $this->cache[$url];
		}

		if ( !( $language = $this->lang_by_param ( $url ) ) ) {
			$language = $this->get_lang_from_url_string($url);
		}

		$lang = in_array ( $language, $this->active_languages, true )
		        || $language === 'all' && defined('WP_ADMIN') ? $language : $this->default_language;
		$this->cache[$url] = $lang;
		return $lang;
	}

	protected abstract function get_lang_from_url_string($url);

	protected abstract function convert_url_string( $url, $lang );

	public function rewrite_rules_filter( $value ) {
		if ( WPML_Root_Page::get_root_id () ) {
			$value = str_replace ( '/' . $this->default_language . '/index.php', '/index.php', $value );
			$value = str_replace ( 'RewriteBase /' . $this->default_language . '/', 'RewriteBase /', $value );
		}

		return $value;
	}

	function tax_permalink_filter( $p, $tag, $taxonomy ) {
		/** @var WPML_Term_Translation $wpml_term_translations */
		global $wpml_term_translations;
		$tag = is_object($tag) ? $tag : get_term($tag, $taxonomy);
		$tag_id   = $tag ? $tag->term_taxonomy_id : 0;
		$cached_permalink_key =  $tag_id . '.' . $taxonomy;
		$found  = false;
		$cached_permalink = wp_cache_get($cached_permalink_key, 'icl_tax_permalink_filter', $found);
		if($found === true) {
			return $cached_permalink;
		}
		$term_language = $tag_id ? $wpml_term_translations->get_element_lang_code($tag_id) : false;
		$p = (bool) $term_language === true  ? $this->convert_url( $p, $term_language ) : $p;

		wp_cache_set($cached_permalink_key, $p, 'icl_tax_permalink_filter');

		return $p;
	}
}