<?php
require 'wpml-url-converter.class.php';

class WPML_Lang_Domains_Converter extends WPML_URL_Converter {

	protected $domains;

	public function __construct( $domains, $default_language, $hidden_languages ) {
		parent::__construct($default_language, $hidden_languages);
		$this->domains          = $domains;
		add_filter( 'login_url', array( $this, 'convert_url' ) );
		add_filter( 'logout_url', array( $this, 'convert_url' ) );
		add_filter( 'site_url', array( $this, 'convert_url' ) );
	}

	protected function get_lang_from_url_string( $url ) {
		foreach ( $this->domains as $code => $domain ) {
			if ( strpos ( $url, parse_url ( $domain, PHP_URL_HOST ) ) === 0 ) {
				$lang = $code;
			}
		}

		return isset( $lang ) ? $lang : null;
	}

	protected function convert_url_string( $url, $lang ) {
		if ( is_admin () && $this->is_url_admin ( $url ) ) {
			return $url;
		}

		$domains           = $this->domains;
		$absolute_home_url = $this->get_abs_home ();
		$new_url           = $url;
		$is_https          = strpos ( $new_url, 'https://' ) === 0;
		if ( $is_https ) {
			preg_replace ( '#^http://#', 'https://', $new_url );
		}
		$domain  = isset( $domains[ $lang ] ) ? $domains[ $lang ] : $absolute_home_url;
		$new_url = str_replace ( $absolute_home_url, $domain, $new_url );
		if ( $is_https ) {
			preg_replace ( '#^http://#', 'https://', $new_url );
		}

		return $new_url;
	}
}