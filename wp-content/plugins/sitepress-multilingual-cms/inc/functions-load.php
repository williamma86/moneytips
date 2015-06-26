<?php

function load_essential_globals() {
	global $wpml_language_resolution,
	       $wpml_slug_filter,
	       $wpml_post_translations,
	       $wpml_term_translations;

	$wpml_languages                       = array();
	$settings                             = get_option ( 'icl_sitepress_settings' );
	$active_language_codes                = isset( $settings[ 'active_languages' ] ) ? $settings[ 'active_languages' ] : array();
	$active_language_codes                = (bool) $active_language_codes === true
		? $active_language_codes : wpml_reload_active_languages_setting ();
	$wpml_languages[ 'active_languages' ] = $active_language_codes;
	$default_lang_code                    = isset( $settings[ 'default_language' ] ) ? $settings[ 'default_language' ]
		: false;
	$wpml_language_resolution             = new WPML_Language_Resolution( $active_language_codes, $default_lang_code );

	if ( ( $admin = is_admin () ) === true ) {
		$wpml_post_translations = new WPML_Admin_Post_Actions( $settings );
		$wpml_post_translations->init ();
		require ICL_PLUGIN_PATH . '/inc/url-handling/wpml-slug-filter.class.php';
		$wpml_slug_filter = new WPML_Slug_Filter( $active_language_codes, $default_lang_code );
	} else {
		$wpml_post_translations = new WPML_Post_Translation();
	}
	$wpml_term_translations = new WPML_Term_Translation();
	$domain_validation = filter_input ( INPUT_GET, '____icl_validate_domain' );
	load_wpml_url_converter ( $settings, $domain_validation, $default_lang_code );
	if ( $domain_validation ) {
		wpml_validate_host ();
	}
	wpml_load_request_handler($admin, $active_language_codes, $default_language);
}

function wpml_load_request_handler($admin, &$active_language_codes, &$default_language){
	global $wpml_request_handler;

	if($admin === true){
		require ICL_PLUGIN_PATH . '/inc/request-handling/wpml-backend-request.class.php';
		$wpml_request_handler = new WPML_Backend_Request($active_language_codes, $default_language);
	} else {
		require ICL_PLUGIN_PATH . '/inc/request-handling/wpml-frontend-request.class.php';
		$wpml_request_handler = new WPML_Frontend_Request($active_language_codes, $default_language);
	}
}

function load_query_filter( $installed ) {
	global $wpml_query_filter, $wpml_tax_query_filter;

	$wpml_query_filter = new WPML_Query_Filter();
	require ICL_PLUGIN_PATH . '/inc/query-filtering/wpml-term-query-filter.class.php';
	$wpml_tax_query_filter = new WPML_Term_Query_Filter();
	if ( $installed ) {
		add_filter ( 'posts_join', array( $wpml_query_filter, 'posts_join_filter' ), 10, 2 );
		add_filter ( 'posts_where', array( $wpml_query_filter, 'posts_where_filter' ), 10, 2 );
		add_filter ( 'query', array( $wpml_query_filter, 'filter_queries' ) );
	}
}

function load_wpml_url_converter($settings, $domain_validation, &$default_lang_code){
	global $wpml_url_converter;

	$url_type          = isset( $settings[ 'language_negotiation_type' ] ) ? $settings[ 'language_negotiation_type' ]
		: false;
	$url_type          = $domain_validation ? $domain_validation : $url_type;
	$hidden_langs = isset( $settings[ 'hidden_languages' ] ) ? $settings[ 'hidden_languages' ] : array();
	if ( $url_type == 1 ) {
		require ICL_PLUGIN_PATH . '/inc/url-handling/wpml-lang-subdir-converter.class.php';
		$dir_default        = isset( $settings[ 'urls' ] ) && isset( $settings[ 'urls' ][ 'directory_for_default_language' ] )
			? $settings[ 'urls' ][ 'directory_for_default_language' ] : false;
		$wpml_url_converter = new WPML_Lang_Subdir_Converter( $dir_default, $default_lang_code, $hidden_langs );
	} elseif ( $url_type == 2 ) {
		require ICL_PLUGIN_PATH . '/inc/url-handling/wpml-lang-domains-converter.class.php';
		$domains            = isset( $settings[ 'language_domains' ] ) ? $settings[ 'language_domains' ] : array();
		$wpml_url_converter = new WPML_Lang_Domains_Converter( $domains, $default_lang_code, $hidden_langs );
	} else {
		require ICL_PLUGIN_PATH . '/inc/url-handling/wpml-lang-parameter-converter.class.php';
		$wpml_url_converter = new WPML_Lang_Parameter_Converter( $default_lang_code, $hidden_langs );
	}
}

function wpml_validate_host() {
	echo '<!--' . trailingslashit ( get_home_url () ) . '-->';
	exit;
}

function is_taxonomy_translated( $taxonomy ) {
	if ( $taxonomy === 'category' || $taxonomy === 'post_tag' ) {
		$translatable = true;
	} else {
		$settings     = get_option ( 'icl_sitepress_settings' );
		$tm_settings  = isset( $settings[ 'translation-management' ] ) ? $settings[ 'translation-management' ] : false;
		$tax_settings = $tm_settings !== false && isset( $tm_settings[ 'taxonomies_readonly_config' ] )
			? $tm_settings[ 'taxonomies_readonly_config' ] : false;
		$translatable = isset( $tax_settings[ $taxonomy ] ) && $tax_settings[ $taxonomy ] ? $tax_settings[ $taxonomy ]
			: false;
	}

	return $translatable;
}

function setup_admin_menus() {
	global $pagenow;

	if ( $pagenow === 'edit-tags.php' ) {
		maybe_load_translated_tax_screen ();
	}
}

function maybe_load_translated_tax_screen() {
	$taxonomy_get = (string) filter_input ( INPUT_GET, 'taxonomy' );
	$taxonomy_get = $taxonomy_get ? $taxonomy_get : 'post_tag';
	if ( is_taxonomy_translated ( $taxonomy_get ) ) {
		require ICL_PLUGIN_PATH . '/menu/term-taxonomy-menus/wpml-tax-menu-loader.class.php';
		new WPML_Tax_Menu_Loader( $taxonomy_get );
	}
}

function wpml_reload_active_languages_setting() {
	global $wpdb, $sitepress_settings;
	if ( icl_get_setting ( 'setup_complete' ) ) {
		$active_languages = $wpdb->get_col ( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" );
		$sitepress_settings[ 'active_languages' ] = $active_languages;
		icl_set_setting ( 'active_languages', $active_languages, true );
	} else {
		$active_languages = array();
	}

	return (array) $active_languages;
}