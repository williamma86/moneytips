<?php

require 'wpml-request.class.php';

class WPML_Frontend_Request extends WPML_Request {

	public function get_requested_lang() {
		$lang = $this->get_request_uri_lang();
		$lang = $lang ? $lang : $this->get_queried_element_lang();

		return $lang;
	}
}