<?php
require 'wpml-url-converter.class.php';

class WPML_Lang_Subdir_Converter extends WPML_URL_Converter {

	private $dir_default;

	public function __construct( $dir_default, $default_language, $hidden_languages ) {
		parent::__construct($default_language, $hidden_languages);
		$this->dir_default      = $dir_default;
	}

	protected function get_lang_from_url_string( $url ) {
		$home_url  = $this->get_abs_home();
		$url_path  = ltrim ( str_replace ( $home_url, "", $url ), "/" );
		$fragments = explode ( "/", $url_path );
		return $fragments[ 0 ];
	}

	protected function convert_url_string( $url, $code ) {

		$new_url = $url;
		$absolute_home_url = $this->get_abs_home();
		if ( 0 === strpos ( $new_url, 'https://' ) ) {
			$absolute_home_url = preg_replace ( '#^http://#', 'https://', $absolute_home_url );
		}
		if ( $absolute_home_url === $new_url ) {
			$new_url .= '/';
		}
		if ( false === strpos ( $new_url, $absolute_home_url . '/' . $code . '/' ) ) {
			$current_language = $this->current_lang;
			//we have to check if we have a language slug in the current url
			$current_lang_slug = "";
			if ( false !== strpos ( $new_url, $absolute_home_url . '/' . $current_language . '/' ) ) {
				$current_lang_slug = '/' . $current_language;
			}
			$code = !$this->dir_default && $code === $this->default_language ? '' : '/' . $code;
			$new_url = str_replace ( $absolute_home_url . $current_lang_slug, $absolute_home_url . $code, $new_url );
		}

		return $new_url;
	}
}