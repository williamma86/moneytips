<?php

abstract class WPML_Request {

	protected $active_languages;
	protected $default_language;

	public function __construct( $active_languages, $default_language ) {
		$this->active_languages = $active_languages;
		$this->default_language = $default_language;
		add_filter( 'WPML_get_language_cookie', array( $this, 'get_cookie_lang' ), 10, 0 );
		add_filter( 'wmpl_get_language_cookie', array( $this, 'get_cookie_lang' ), 10, 0 );
	}

	public abstract function get_requested_lang();

	public function get_request_uri_lang() {
		global $wpml_url_converter;
		return $wpml_url_converter->get_language_from_url ( untrailingslashit($_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ]) );
	}


	public function get_cookie_lang() {

		$lang = isset( $_COOKIE[ '_icl_current_language' ] ) ? substr ( $_COOKIE[ '_icl_current_language' ], 0, 10 ) : "";
		$lang = in_array ( $lang, $this->active_languages, true ) ? $lang : $this->default_language;

		return $lang;
	}

	public function get_queried_element_lang() {
		$query_string = parse_url ( $_SERVER[ 'REQUEST_URI' ], PHP_URL_QUERY );
		$query        = new WP_Query( $query_string );
		if ( $query->is_singular () === true ) {
			$post_id = $query->get_queried_object_id ();
			global $wpml_post_translations;
			return $wpml_post_translations->get_element_lang_code ( $post_id );
		} elseif ( $query->is_archive() ) {
			return false;
		}

	}

	function set_language_cookie($lang_code) {
		if ( !headers_sent() ) {
			if ( preg_match( '@\.(css|js|png|jpg|gif|jpeg|bmp)@i', basename( preg_replace( '@\?.*$@', '', $_SERVER[ 'REQUEST_URI' ] ) ) ) || isset( $_POST[ 'icl_ajx_action' ] ) || isset( $_POST[ '_ajax_nonce' ] ) || defined( 'DOING_AJAX' ) ) {
				return;
			}

			$server_host_name = $this->get_server_host_name();
			$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : $server_host_name;
			$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
			setcookie( '_icl_current_language', $lang_code, time() + 86400, $cookie_path, $cookie_domain );
		}
	}

	/**
	 * Returns SERVER_NAME, or HTTP_HOST if the first is not available
	 * @return mixed
	 */
	public function get_server_host_name() {
		$host = isset( $_SERVER[ 'HTTP_HOST' ] ) ? $_SERVER[ 'HTTP_HOST' ] : null;
		$host = $host !== null ? $host : ( isset( $_SERVER[ 'SERVER_NAME' ] )
			? $_SERVER[ 'SERVER_NAME' ]
			  . ( isset($_SERVER['SERVER_PORT']) && !in_array($_SERVER['SERVER_PORT'], array(80, 443))
				? $_SERVER['SERVER_PORT'] : '' )
			: '' );

		//Removes standard ports 443 (80 should be already omitted in all cases)
		$result = preg_replace ( "@:[443]+([/]?)@", '$1', $host );

		return $result;
	}

}