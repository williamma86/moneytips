<?php

require 'wpml-request.class.php';

class WPML_Backend_Request extends WPML_Request {

	function check_if_admin_action_from_referer() {
		$referer = isset( $_SERVER[ 'HTTP_REFERER' ] ) ? $_SERVER[ 'HTTP_REFERER' ] : '';

		return strpos ( $referer, strtolower ( admin_url () ) ) === 0;
	}

	public function get_current_language_code() {
		$lang = $this->get_queried_element_lang ();
		$lang = $lang ? $lang : $this->get_request_uri_lang ();
		$lang = $lang ? $lang : $this->get_cookie_lang ();

		return $lang;
	}

	public function force_default(){
		return isset( $_GET[ 'page' ] )
		       && ( ( defined( 'WPML_ST_FOLDER' )
		              && $_GET[ 'page' ] === WPML_ST_FOLDER . '/menu/string-translation.php' )
		            || ( defined( 'WPML_TM_FOLDER' )
		                 && $_GET[ 'page' ] === WPML_TM_FOLDER . '/menu/translations-queue.php' ) );
	}

	public function get_post_edit_lang(){

		return filter_input(INPUT_POST, 'action') === 'editpost'
			? filter_input(INPUT_POST, 'icl_post_language') : null;
	}

	public function get_ajax_request_lang() {
		global $wpml_url_converter;

		$al   = $this->active_languages;
		$lang = isset( $_POST[ 'lang' ] ) && isset( $al[ $_POST[ 'lang' ] ] ) ? $_POST[ 'lang' ] : null;
		$lang = $lang === null && $this->check_if_admin_action_from_referer ()
			? ( $cookie_lang = $this->get_cookie_lang () ) : $lang;
		$lang = $lang === null && isset( $_SERVER[ 'HTTP_REFERER' ] )
			? $wpml_url_converter->get_language_from_url ( $_SERVER[ 'HTTP_REFERER' ] ) : $lang;
		$lang = $lang ? $lang : ( isset( $cookie_lang ) ? $cookie_lang : $this->get_cookie_lang () );
		$lang = $lang ? $lang : $this->default_language;

		return $lang;
	}

	public function get_requested_lang(){
		if (
			( $lang_code_get = filter_input( INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) !== null
		) {
			$lang = rtrim( $lang_code_get , '/' );
			$al              = $this->active_languages;
			$al[ 'all' ]     = true;
			if ( empty( $al[ $lang ] ) ) {
				$lang = $this->default_language;
			}
			// force default language for string translation
			// we also make sure it's not saved in the cookie
		} elseif ($this->force_default() === true) {
			$lang = $this->default_language;
		} elseif ( wpml_is_ajax() ) {
			$lang = $this->get_ajax_request_lang();
		} elseif ( null !== ($icl_p_lang = $this->get_post_edit_lang())) {
			$lang = $icl_p_lang;
		}	elseif ( ($p = filter_input(INPUT_GET, 'p', FILTER_SANITIZE_NUMBER_INT) ) !== null ) {
			global $wpml_post_translations;
			$lang = $wpml_post_translations->get_element_lang_code($p);
		}

		if(empty($lang) && isset($_SERVER[ "REQUEST_URI" ])){
			global $wpml_url_converter;

			$lang = $wpml_url_converter->get_language_from_url( $_SERVER[ "REQUEST_URI" ]);
		}

		return $lang;
	}
}