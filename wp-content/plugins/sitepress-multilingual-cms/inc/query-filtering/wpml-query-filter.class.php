<?php

class WPML_Query_Filter {

	/**
	 * @param $query WP_Query
	 * @param String $pagenow
	 * @return bool
	 */
	private function apply_join_filter( $query, $pagenow ) {
		global $sitepress_settings, $sitepress;

		return !( ( $pagenow === 'upload.php'
		            || $pagenow === 'media-upload.php'
		            || $query->is_attachment () )
		          && !$sitepress->is_translated_post_type ( 'attachment' ) )
		       || ( isset( $query->queried_object )
		            && isset( $query->queried_object->ID )
		            && $query->queried_object->ID == $sitepress_settings[ 'urls' ][ 'root_page' ] );
	}

	/**
	 * @param String $query_type
	 * @return array|bool|string
	 */
	private function determine_post_type($query_type) {
		global $sitepress;
		$debug_backtrace = $sitepress->get_backtrace ( 0, true, false ); //Limit to a maximum level?
		$post_type       = false;
		foreach ( $debug_backtrace as $o ) {
			if ( $o[ 'function' ] == 'apply_filters_ref_array' && $o[ 'args' ][ 0 ] === $query_type ) {
				$post_type = esc_sql ( $o[ 'args' ][ 1 ][ 1 ]->query_vars[ 'post_type' ] );
				break;
			}
		}

		return $post_type;
	}

	public function filter_single_type_join( $join, $post_type ) {
		global $sitepress;

		if ( $sitepress->is_translated_post_type ( $post_type ) ) {
			$join .= $this->any_post_type_join( false );
		} elseif ( $post_type === 'any' ) {
			$join .= $this->any_post_type_join();
		}

		return $join;
	}

	private function any_post_type_join($left = true) {
		global $wpdb;

		$left = $left ? " LEFT " : "";

		return $left . " JOIN {$wpdb->prefix}icl_translations t
							ON {$wpdb->posts}.ID = t.element_id
								AND t.element_type = CONCAT('post_', {$wpdb->posts}.post_type) ";
	}

	private function has_translated_type($core_types){
		global $sitepress;

		$res = false;
		foreach ( $core_types as $ptype ) {
			if ( $sitepress->is_translated_post_type ( $ptype ) ) {
				$res = true;
				break;
			}
		}

		return $res;
	}

	/**
	 * @param String $join
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	public function posts_join_filter( $join, $query ) {
		global $pagenow;

		if ( !$this->apply_join_filter ( $query, $pagenow ) ) {
			return $join;
		}

		$post_type = $this->determine_post_type ( 'posts_join' );
		$post_type = $post_type ? $post_type : 'post';

		if ( is_array ( $post_type ) && $this->has_translated_type ( $post_type ) === true ) {
			$join .= $this->any_post_type_join ();
		} elseif ( $post_type ) {
			$join = $this->filter_single_type_join ( $join, $post_type );
		} else {
			$taxonomy_post_types = $this->tax_ptypes_from_query ( $query );
			$join                = $this->tax_types_join ( $join, $taxonomy_post_types );
		}

		return $join;
	}

	/**
	 * @param WP_Query $query
	 * @return String[]
	 */
	private function tax_ptypes_from_query($query){
		global $sitepress;

		if ( $query->is_tax () && $query->is_main_query () ) {
			$taxonomy_post_types = $this->get_tax_query_posttype($query);
		} else {
			$taxonomy_post_types = array_keys ( $sitepress->get_translatable_documents ( false ) );
		}

		return $taxonomy_post_types;
	}

	private function tax_types_join( $join, $tax_post_types ) {
		if ( !empty( $tax_post_types ) ) {
			foreach ( $tax_post_types as $k => $v ) {
				$tax_post_types[ $k ] = 'post_' . $v;
			}
			$join .=   $this->any_post_type_join() . " AND t.element_type IN (" . wpml_prepare_in ( $tax_post_types ) . ") ";
		}

		return $join;
	}

	/**
	 * @param WP_Query $query
	 *
	 * @return String[]
	 */
	private function get_tax_query_posttype( $query ) {
		global $sitepress;

		$tax       = $query->get ( 'taxonomy' );
		$post_type = WPML_Terms_Translations::get_taxonomy_post_types ( $tax );
		foreach ( $post_type as $k => $v ) {
			if ( !$sitepress->is_translated_post_type ( $v ) ) {
				unset( $post_type[ $k ] );
			}
		}

		return $post_type;
	}

	private function posttypes_not_translated( $post_types ) {
		global $sitepress;

		$post_types = is_array($post_types) ? $post_types : array($post_types);

		$none_translated = true;
		foreach ( $post_types as $ptype ) {
			if ( $sitepress->is_translated_post_type ( $ptype ) ) {
				$none_translated = false;
				break;
			}
		}

		return $none_translated;
	}

	private function all_langs_where( ) {
		global $sitepress;

		return ' AND t.language_code IN (' . wpml_prepare_in ( array_keys ( $sitepress->get_active_languages () ) ) . ') ';
	}

	/**
	 * @param String $where
	 * @param String | String[] $post_type
	 *
	 * @return string
	 */
	public function filter_single_type_where( $where, $post_type ) {
		if ( $this->posttypes_not_translated ( $post_type ) === false ) {
			global $sitepress;
			$where .= $this->specific_lang_where ($sitepress->get_current_language());
		}

		return $where;
	}

	private function specific_lang_where($current_language){
		global $wpdb;

		return $wpdb->prepare ( " AND t.language_code = %s ", $current_language );
	}

	/**
	 * @param WP_Query $query
	 *
	 * @return Boolean
	 */
	private function where_filter_active( $query ) {
		global $sitepress, $sitepress_settings, $pagenow;

		$active = isset( $query->queried_object )
		          && isset( $query->queried_object->ID )
		          && $query->queried_object->ID == $sitepress_settings[ 'urls' ][ 'root_page' ]
			? false : true;

		if ( $active === true ) {
			$post_type = $this->determine_post_type ( 'posts_where' );
			$post_type = empty( $post_type ) && $query->is_tax () ? $this->get_tax_query_posttype ( $query ) : $post_type;
			$post_type = $post_type ? $post_type : 'post';
			$active    = ( $post_type && ( $this->posttypes_not_translated ( $post_type ) === false )
			               || $pagenow === 'upload.php'
			               || $pagenow === 'media-upload.php')
			               && ! ( $query->is_attachment () && !$sitepress->is_translated_post_type ( 'attachment' ) ) ;
		}

		return $active;
	}

	/**
	 * @param string $where
	 * @param WP_Query $query
	 *
	 * @return string
	 */
	function posts_where_filter( $where, $query ) {
		global $sitepress, $wpml_post_translations;

		if ( $query === null || $this->where_filter_active($query) === false ) {
			return $where;
		}

		$requested_id = isset( $_REQUEST[ 'attachment_id' ] ) && $_REQUEST[ 'attachment_id' ] ? $_REQUEST[ 'attachment_id' ] : false;
		$requested_id = isset( $_REQUEST[ 'post_id' ] ) && $_REQUEST[ 'post_id' ] ? $_REQUEST[ 'post_id' ] : $requested_id;
		$current_language = $requested_id ? $wpml_post_translations->get_element_lang_code ( $requested_id ) : $sitepress->get_current_language();
		$current_language = $current_language ? $current_language : $sitepress->get_default_language ();
		$condition = $current_language === 'all' ? $this->all_langs_where () : $this->specific_lang_where ( $current_language );
		$where .= $condition;

		return $where;
	}

	function filter_queries( $sql ) {
		global $sitepress;

		if ( version_compare( $GLOBALS[ 'wp_version' ], '3.9', '>=' ) ) {
			global $wpdb;
			if ( preg_match (
				"#\n\t\tSELECT ID, post_name, post_parent, post_type\n\t\tFROM {$wpdb->posts}\n\t\tWHERE post_name IN \(([^)]+)\)\n\t\tAND post_type IN \(([^)]+)\)#",
				$sql,
				$matches
			) ) {
				$sql .= $wpdb->prepare(" AND ( post_type NOT IN (" . (wpml_prepare_in(array_keys($sitepress->get_translatable_documents()))) . ")
											OR ID = (SELECT element_id FROM {$wpdb->prefix}icl_translations
													 WHERE element_id = ID
													    AND element_type = CONCAT('post_', post_type)
														AND language_code = %s LIMIT 1)) ", $sitepress->get_current_language());
			}
		}

		return $sql;
	}
}
