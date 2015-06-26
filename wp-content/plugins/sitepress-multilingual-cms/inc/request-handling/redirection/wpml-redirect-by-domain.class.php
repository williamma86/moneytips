<?php

class WPML_Redirect_By_Domain {

	public function maybe_redirect( $domains ) {
		global $wpml_request_handler, $wpml_language_resolution;
		$language = $wpml_request_handler->get_request_uri_lang ();

		if ( $wpml_language_resolution->is_language_hidden ( $language )
		     && strpos ( $_SERVER[ 'REQUEST_URI' ], 'wp-login.php' ) === false
		     && !user_can ( wp_get_current_user (), 'manage_options' )
		) {
			wp_redirect ( trailingslashit ( $domains[ $language ] ) . 'wp-login.php' );
			exit;
		}
	}
}