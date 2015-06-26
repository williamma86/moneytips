<?php

class WPML_Term_Translation extends WPML_Element_Translation {

	protected $ttids = array();
	protected $term_ids = array();

	public function lang_code_by_termid( $term_id ) {

		return $this->get_element_lang_code ( $this->get_ttid_by_termid( $term_id ) );
	}

	private function get_ttid_by_termid($term_id){
		global $wpdb;

		if ( !isset( $this->ttids[ $term_id ] ) ) {
			$ttid = $wpdb->get_var (
				$wpdb->prepare ( "SELECT t.element_id {$this->element_join}
								  WHERE term_id = %d LIMIT 1", $term_id ) );
			$this->cache_ids ( $ttid, $term_id );
		} else {
			$ttid = $this->ttids[ $term_id ];
		}

		return $ttid;
	}

	private function get_term_id_by_ttid( $ttid ) {
		global $wpdb;

		if ( $ttid ) {
			if ( !isset( $this->term_ids[ $ttid ] ) ) {
				$term_id = $wpdb->get_var (
					$wpdb->prepare (
						"SELECT tax.term_id {$this->element_join}
						 WHERE element_id = %d LIMIT 1",
						$ttid
					)
				);
				$this->cache_ids ( $ttid, $term_id );
			} else {
				$term_id = $this->term_ids[ $ttid ];
			}
		} else {
			$term_id = null;
		}

		return $term_id;
	}

	private function cache_ids( $ttid, $term_id ) {
		$this->ttids[ $term_id ] = $ttid;
		$this->term_ids[ $ttid ] = $term_id;
	}

	public function term_id_in( $term_id, $lang_code ) {

		return $this->get_term_id_by_ttid (
			$this->element_id_in ( $this->get_ttid_by_termid ( $term_id ), $lang_code )
		);
	}

	protected function get_element_join() {
		global $wpdb;

		return "FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->term_taxonomy} tax
					ON t.element_id = tax.term_taxonomy_id
						AND t.element_type = CONCAT('tax_', tax.taxonomy)";
	}

	public function is_translated_type( $element_type ) {
		is_taxonomy_translated ( $element_type );
	}
}
