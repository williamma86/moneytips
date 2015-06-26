<?php
/**
 * @package wpml-core
 */

function update_wpml_config_index_event(){

	// Fetch the wpml config files for known plugins and themes.
	
	$wp_http_class = new WP_Http();
	$response = $wp_http_class->get( ICL_REMOTE_WPML_CONFIG_FILES_INDEX . 'wpml-config/config-index.json');


	if(!is_wp_error($response) && $response['response']['code'] == 200) {
		$arr = json_decode($response['body']);

		if( isset( $arr->plugins ) && isset( $arr->themes ) ) {
			update_option('wpml_config_index',$arr);
			update_option('wpml_config_index_updated',time());

			$config_files_arr = maybe_unserialize(get_option('wpml_config_files_arr'));

			$config_files_themes_arr  = array();
			$config_files_plugins_arr = array();
			if ( $config_files_arr ) {
				if ( isset( $config_files_arr->themes ) ) {
					$config_files_themes_arr = $config_files_arr->themes;
				}
				if ( isset( $config_files_arr->plugins ) ) {
					$config_files_plugins_arr = $config_files_arr->plugins;
				}
			}
			$wp_http_class = new WP_Http();


			$theme_data = wp_get_theme();

			foreach($arr->themes as $theme){

				if( $theme_data->get( 'Name' ) == $theme->name && ( !isset( $config_files_themes_arr[$theme->name] ) || md5( $config_files_themes_arr[$theme->name] ) != $theme->hash ) ){
					$response = $wp_http_class->get(ICL_REMOTE_WPML_CONFIG_FILES_INDEX . $theme->path);
					if($response['response']['code'] == 200){
						$config_files_themes_arr[$theme->name] = $response['body'];
					}
				}
			}

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$active_plugins = get_plugins();

			$active_plugins_names = array();
			foreach( $active_plugins as $active_plugin ){
				$active_plugins_names[] = $active_plugin['Name'];
			}

			foreach($arr->plugins as $plugin){


				if( in_array($plugin->name,$active_plugins_names) && ( !isset( $config_files_plugins_arr[$plugin->name] ) || md5( $config_files_plugins_arr[$plugin->name] ) != $plugin->hash ) ){
					$response = $wp_http_class->get(ICL_REMOTE_WPML_CONFIG_FILES_INDEX . $plugin->path);

					if(!is_wp_error($response) &&$response['response']['code'] == 200){
						$config_files_plugins_arr[$plugin->name] = $response['body'];
					}
				}
			}

			if ( ! isset( $config_files_arr ) || ! $config_files_arr ) {
				$config_files_arr = new stdClass();
			}
			$config_files_arr->themes = $config_files_themes_arr;
			$config_files_arr->plugins = $config_files_plugins_arr;

			update_option('wpml_config_files_arr',$config_files_arr);

			return true;
		}
	}

	return false;
}
