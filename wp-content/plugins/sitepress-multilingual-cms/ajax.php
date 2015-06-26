<?php
/**
 * @package wpml-core
 * @used-by Sitepress::ajax_setup
 */
global $wpdb, $sitepress, $sitepress_settings;

@header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
header( "Cache-Control: no-cache, must-revalidate" );
header( "Expires: Sat, 16 Aug 1980 05:00:00 GMT" );

$request = filter_input( INPUT_POST, 'icl_ajx_action'  );
$request = $request ? $request : filter_input( INPUT_GET, 'icl_ajx_action' );
switch ( $request ) {
    case 'health_check':
        icl_set_setting( 'ajx_health_checked', true, true );
        exit;
    case 'get_browser_language':
        $http_accept_language            = $_SERVER[ 'HTTP_ACCEPT_LANGUAGE' ];
        $accepted_languages              = explode( ';', $http_accept_language );
        $default_accepted_language       = $accepted_languages[ 0 ];
        $default_accepted_language_codes = explode( ',', $default_accepted_language );
        echo wpml_mb_strtolower( $default_accepted_language_codes[ 0 ] );
        exit;
}

$request = wpml_get_authenticated_action();

$iclsettings = $this->get_settings();
$default_language = $this->get_default_language();

switch($request){
    case 'set_active_languages':
        $resp = array();
        $old_active_languages_count = count($this->get_active_languages());
	    $lang_codes = filter_input( INPUT_POST, 'langs', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
        $lang_codes = explode(',',$lang_codes);
        if($this->set_active_languages($lang_codes)){
            $resp[0] = 1;
            $iclsettings['active_languages'] = wpml_reload_active_languages_setting();
            $active_langs = $this->get_active_languages();
            $iclresponse ='';
            $default_categories = $sitepress->get_setting( 'default_categories', array() );
            $default_category_main = $wpdb->get_var(
                $wpdb->prepare("SELECT name FROM {$wpdb->terms} t
                                JOIN {$wpdb->term_taxonomy} tx ON t.term_id=tx.term_id
                                WHERE term_taxonomy_id = %d
                                  AND taxonomy='category'",
                               $default_categories[$default_language] ));
            $default_category_trid = $wpdb->get_var(
                $wpdb->prepare("SELECT trid FROM {$wpdb->prefix}icl_translations
                                WHERE element_id = %d
                                  AND element_type='tax_category'",
                               $default_categories[$default_language]));
            foreach($active_langs as $lang){
                $is_default = ( $default_language ==$lang['code']);
                $iclresponse .= '<li ';
                if($is_default) $iclresponse .= 'class="default_language"';
                $iclresponse .= '><label><input type="radio" name="default_language" value="' . $lang['code'] .'" ';
                if($is_default) $iclresponse .= 'checked="checked"';
                $iclresponse .= '>' . $lang['display_name'];
                if($is_default) $iclresponse .= ' ('. __('default','sitepress') . ')';
                $iclresponse .= '</label></li>';

                SitePress_Setup::insert_default_category ( $lang[ 'code' ] );
            }

            $resp[1] = $iclresponse;
            // response 1 - blog got more than 2 languages; -1 blog reduced to 1 language; 0 - no change
            if(count($lang_codes) > 1){
                $resp[2] =$this->get_setting('setup_complete') ? 1 : -2;
            }elseif($old_active_languages_count > 1 && count($lang_codes) < 2){
                $resp[2] = $this->get_setting('setup_complete') ? -1 : -3;
            }else{
                $resp[2] = $this->get_setting('setup_complete') ? 0 : -3;
            }
            if(count($active_langs) > 1){
                $iclsettings['dont_show_help_admin_notice'] = true;
                $this->save_settings($iclsettings);
            }
        }else{
            $resp[0] = 0;
        }

        if(empty($iclsettings['setup_complete'])){
            $iclsettings['setup_wizard_step'] = 3;
            $this->save_settings($iclsettings);
        }

        echo join('|',$resp);
        do_action('icl_update_active_languages');
        break;
    case 'set_default_language':
        $previous_default = $default_language;
	    $new_default_language = filter_input( INPUT_POST, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
        if($response = $this->set_default_language( $new_default_language )){
            echo '1|'.$previous_default.'|';
        }else{
            echo'0||' ;
        }
        if(1 === $response){
            echo __('WordPress language file (.mo) is missing. Keeping existing display language.', 'sitepress');
        }
        break;
    case 'set_languages_order':
	    $languages_order = filter_input( INPUT_POST, 'order', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
        $iclsettings['languages_order'] = explode(';', $languages_order );
        $this->save_settings($iclsettings);
        echo json_encode(array('message' => __('Languages order updated', 'sitepress')));
        break;
    case 'icl_tdo_options':
        $iclsettings['translated_document_status']      = filter_input( INPUT_POST, 'icl_translated_document_status', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
        $iclsettings['translated_document_page_url']    = filter_input( INPUT_POST, 'icl_translated_document_page_url', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
        $this->save_settings($iclsettings);
        echo '1|';
       break;
    case 'icl_save_language_negotiation_type':

	    $filtered_icl_language_negotiation_type = filter_input( INPUT_POST, 'icl_language_negotiation_type', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
	    $filtered_language_domains              = filter_input( INPUT_POST, 'language_domains', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY | FILTER_NULL_ON_FAILURE  );
	    $filtered_use_directory                 = filter_input( INPUT_POST, 'use_directory', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
	    $filtered_show_on_root                  = filter_input( INPUT_POST, 'show_on_root', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
	    $filtered_root_html_file_path           = filter_input( INPUT_POST, 'root_html_file_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
	    $filtered_hide_language_switchers       = filter_input( INPUT_POST, 'hide_language_switchers', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );

        $iclsettings['language_negotiation_type'] = $filtered_icl_language_negotiation_type;
        if( !empty( $filtered_language_domains ) ) {
            $iclsettings['language_domains'] = $filtered_language_domains;
        }
        if($iclsettings['language_negotiation_type'] == 1){
            $iclsettings['urls']['directory_for_default_language'] = $filtered_use_directory !== false ? $filtered_use_directory : 0;
            if($iclsettings['urls']['directory_for_default_language']){
                $iclsettings['urls']['show_on_root']   = $filtered_use_directory ? $filtered_show_on_root : '';
                if($iclsettings['urls']['show_on_root'] == 'html_file'){
                    $iclsettings['urls']['root_html_file_path'] = $filtered_root_html_file_path ? $filtered_root_html_file_path : '';
                }else{
                    $iclsettings['urls']['hide_language_switchers'] = $filtered_hide_language_switchers !=- false ? $filtered_hide_language_switchers : 0;
                }
            }
        }
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'icl_save_language_switcher_options':
        $_POST   = stripslashes_deep( $_POST );

	    if ( isset( $_POST[ 'icl_language_switcher_sidebars' ] ) ) {
		    global $wp_registered_widgets, $wp_registered_sidebars;
		    $widget_icl_lang_sel_widget = get_option( 'widget_icl_lang_sel_widget' );
		    $counter                    = is_array( $widget_icl_lang_sel_widget ) ? max( array_keys( $widget_icl_lang_sel_widget ) ) : 0;
		    if ( ! is_numeric( $counter ) || $counter<=0 ) {
			    $counter = 1;
		    }

		    $language_switcher_name            = 'icl_lang_sel_widget';
		    $language_switcher_prefix          = $language_switcher_name . '-';
		    $active_widgets                    = get_option( 'sidebars_widgets' );
		    $posted_language_switcher_sidebars = $_POST[ 'icl_language_switcher_sidebars' ];
		    $update_sidebars_widgets           = false;
		    foreach ( $posted_language_switcher_sidebars as $target_sidebar_id => $add_widget ) {
			    $widget_exists = false;
			    if(isset($active_widgets[ $target_sidebar_id ])) {
				    $active_sidebar_widgets = $active_widgets[ $target_sidebar_id ];
				    foreach ( $active_sidebar_widgets as $index => $active_sidebar_widget ) {
					    if ( strpos( $active_sidebar_widget, $language_switcher_prefix ) !== false ) {
						    $widget_exists = true;
						    break;
					    }
				    }
			    }
			    if($add_widget && !$widget_exists) {
				    if(isset($active_widgets[ $target_sidebar_id ])) {
					    $active_sidebar_widgets = $active_widgets[ $target_sidebar_id ];
					    array_unshift( $active_sidebar_widgets, $language_switcher_prefix . $counter );
				    } else {
					    $active_sidebar_widgets = array();
					    $active_sidebar_widgets[] = $language_switcher_prefix . $counter;
				    }
				    $language_switcher_content             = get_option( 'widget_' . $language_switcher_name );
				    $language_switcher_content[ $counter ] = array( 'title_show' => 0 );
				    if ( ! array_key_exists( '_multiwidget', $language_switcher_content ) ) {
					    $language_switcher_content[ '_multiwidget' ] = 1;
				    }
				    update_option( 'widget_' . $language_switcher_name, $language_switcher_content );
				    $counter ++;
				    $active_widgets[ $target_sidebar_id ] = $active_sidebar_widgets;
				    $update_sidebars_widgets              = true;
			    }elseif(!$add_widget && $widget_exists) {
				    foreach ( $active_sidebar_widgets as $index => $active_sidebar_widget ) {
					    if ( strpos( $active_sidebar_widget, $language_switcher_prefix ) !== false ) {
						    unset( $active_widgets[ $target_sidebar_id ][ $index ] );
						    $update_sidebars_widgets = true;
					    }
				    }
			    }
		    }
		    if ( $update_sidebars_widgets ) {
			    wp_set_sidebars_widgets( $active_widgets );
		    }
	    }

		$filtered_icl_lso_link_empty    = filter_input( INPUT_POST, 'icl_lso_link_empty', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
		$filtered_icl_lso_flags         = filter_input( INPUT_POST, 'icl_lso_flags', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
		$filtered_icl_lso_native_lang   = filter_input( INPUT_POST, 'icl_lso_native_lang', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
		$filtered_icl_lso_display_lang  = filter_input( INPUT_POST, 'icl_lso_display_lang', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );

        $iclsettings['icl_lso_link_empty']      = intval( $filtered_icl_lso_link_empty );
        $iclsettings['icl_lso_flags']           = $filtered_icl_lso_flags !== false ? $filtered_icl_lso_flags : 0;
        $iclsettings['icl_lso_native_lang']     = $filtered_icl_lso_native_lang !== false ? $filtered_icl_lso_native_lang : 0;
        $iclsettings['icl_lso_display_lang']    = $filtered_icl_lso_display_lang !== false ? $filtered_icl_lso_display_lang : 0;

        if(!$this->get_setting('setup_complete')){
            $iclsettings['setup_wizard_step'] = 4;
            if(isset($iclsettings['setup_reset'])) unset($iclsettings['setup_reset']);
            /** @var WPML_Language_Resolution $wpml_language_resolution */
            global $wpml_language_resolution;
            $active_languages = $wpml_language_resolution->get_active_language_codes();
            $language_domains_helper = new WPML_Language_Domains($default_language, $iclsettings);
            foreach($active_languages as $language_code){
                if($language_code !== $default_language ){
                    if($language_domains_helper->validate_language_per_directory($language_code)){
                        $iclsettings['language_negotiation_type'] = 1;
                    }
                    break;
                }
            }
        }

        if(isset($_POST['icl_lang_sel_config'])){
            $iclsettings['icl_lang_sel_config'] = $_POST['icl_lang_sel_config'];
        }

        if(isset($_POST['icl_lang_sel_footer_config'])){
            $iclsettings['icl_lang_sel_footer_config'] = $_POST['icl_lang_sel_footer_config'];
        }

        if (isset($_POST['icl_lang_sel_type']))
            $iclsettings['icl_lang_sel_type'] = $_POST['icl_lang_sel_type'];
        if (isset($_POST['icl_lang_sel_stype']))
            $iclsettings['icl_lang_sel_stype'] = $_POST['icl_lang_sel_stype'];

        if($iclsettings['icl_lang_sel_type'] == 'list'){
            $iclsettings['icl_lang_sel_orientation'] = $_POST['icl_lang_sel_orientation'];
        }

        if (isset($_POST['icl_lang_sel_footer']))
            $iclsettings['icl_lang_sel_footer'] = 1;
        else $iclsettings['icl_lang_sel_footer'] = 0;

        if (isset($_POST['icl_post_availability']))
            $iclsettings['icl_post_availability'] = 1;
        else $iclsettings['icl_post_availability'] = 0;

        if (isset($_POST['icl_post_availability_position']))
            $iclsettings['icl_post_availability_position'] = $_POST['icl_post_availability_position'];

        if (isset($_POST['icl_post_availability_text']))
            $iclsettings['icl_post_availability_text'] = $_POST['icl_post_availability_text'];

        $iclsettings['icl_widget_title_show'] = (isset($_POST['icl_widget_title_show'])) ? 1 : 0;
        $iclsettings['icl_additional_css'] = $_POST['icl_additional_css'];

        $iclsettings['display_ls_in_menu'] = @intval($_POST['display_ls_in_menu']);
        $iclsettings['menu_for_ls'] = @intval($_POST['menu_for_ls']);

        $iclsettings['icl_lang_sel_copy_parameters'] = join(', ', array_map('trim', explode(',', $_POST['copy_parameters'])));

        if(!$iclsettings['icl_lso_flags'] && !$iclsettings['icl_lso_native_lang'] && !$iclsettings['icl_lso_display_lang']){
            echo '0|';
            echo __('At least one of the language switcher style options needs to be checked', 'sitepress');
        }else{
            $this->save_settings($iclsettings);
            echo 1;
        }
        break;
    
    case 'registration_form_submit':
        
        $ret['error'] = '';
        
        if($_POST['button_action'] == 'later'){
            
            //success
            $ret['success'] = sprintf(__('WPML will work on your site, but you will not receive updates. WPML updates are essential for keeping your site running smoothly and secure. To receive automated updates, you need to complete the registration, in the %splugins admin%s page.', 'sitepress'), 
                '<a href="' . admin_url('plugin-install.php?tab=commercial') . '">', '</a>');
            
            
        }elseif($_POST['button_action'] == 'finish'){
            
            $iclsettings['setup_complete'] = 1;        
            
        }else{
        
            if(empty($_POST['installer_site_key'])){
                $ret['error'] = __('Missing site key.');
            }else{
                
                $iclsettings['site_key'] = $_POST['installer_site_key'];
                
                if(class_exists('WP_Installer')){
                    $args['repository_id'] = 'wpml';
                    $args['nonce'] = wp_create_nonce('save_site_key_' . $args['repository_id']) ;
                    $args['site_key'] = $_POST['installer_site_key'];
                    $args['return']   = 1;
                    $r = WP_Installer()->save_site_key($args);    
                    if(!empty($r['error'])){
                        $ret['error'] = $r['error'];
                        
                    }else{
                        
                        //success
                        $ret['success'] = __('Thank you for registering WPML on this site. You will receive automatic updates when new versions are available.', 'sitepress');
                    }
                }
                
            }
        }
        
        if(!empty($iclsettings)){
            $this->save_settings($iclsettings);    
        }
        
        
        echo json_encode($ret);
    
        break;
    
    case 'icl_admin_language_options':
        $iclsettings['admin_default_language'] = $_POST['icl_admin_default_language'];
        $this->save_settings($iclsettings);
        $this->icl_locale_cache->clear();
        echo 1;
        break;
    case 'icl_blog_posts':
        $iclsettings['show_untranslated_blog_posts'] = $_POST['icl_untranslated_blog_posts'];
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'icl_page_sync_options':
        $iclsettings['sync_page_ordering'] = @intval($_POST['icl_sync_page_ordering']);
        $iclsettings['sync_page_parent'] = @intval($_POST['icl_sync_page_parent']);
        $iclsettings['sync_page_template'] = @intval($_POST['icl_sync_page_template']);
        $iclsettings['sync_comment_status'] = @intval($_POST['icl_sync_comment_status']);
        $iclsettings['sync_ping_status'] = @intval($_POST['icl_sync_ping_status']);
        $iclsettings['sync_sticky_flag'] = @intval($_POST['icl_sync_sticky_flag']);
        $iclsettings['sync_private_flag'] = @intval($_POST['icl_sync_private_flag']);
        $iclsettings['sync_post_format'] = @intval($_POST['icl_sync_post_format']);
        $iclsettings['sync_delete'] = @intval($_POST['icl_sync_delete']);
        $iclsettings['sync_delete_tax'] = @intval($_POST['icl_sync_delete_tax']);
        $iclsettings['sync_post_taxonomies'] = @intval($_POST['icl_sync_post_taxonomies']);
        $iclsettings['sync_post_date'] = @intval($_POST['icl_sync_post_date']);
        $iclsettings['sync_taxonomy_parents'] = @intval($_POST['icl_sync_taxonomy_parents']);
        $iclsettings['sync_comments_on_duplicates'] = @intval($_POST['icl_sync_comments_on_duplicates']);
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'language_domains':
        $language_domains_helper = new WPML_Language_Domains($default_language, $iclsettings);
        echo $language_domains_helper->render_domains_options();
        break;
    case 'validate_language_domain':
        $language_domains_helper = new WPML_Language_Domains($default_language, $iclsettings);
        $posted_url = filter_input(INPUT_POST, 'url');
        echo $language_domains_helper->validate_domain_networking($posted_url);
        break;
    case 'icl_theme_localization_type':
        $icl_tl_type = @intval($_POST['icl_theme_localization_type']);
        $iclsettings['theme_localization_type'] = $icl_tl_type;
        $iclsettings['theme_localization_load_textdomain'] = @intval($_POST['icl_theme_localization_load_td']);
	    $filtered_textdomain_value = filter_input( INPUT_POST, 'textdomain_value', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
        $iclsettings['gettext_theme_domain_name'] = $filtered_textdomain_value;
        if($icl_tl_type==1){
            icl_st_scan_theme_files();
        }elseif($icl_tl_type==2){
            $parent_theme = get_template_directory();
            $child_theme = get_stylesheet_directory();
            $languages_folders = array();

            if($found_folder = icl_tf_determine_mo_folder($parent_theme)){
                $languages_folders['parent'] = $found_folder;
            }
            if($parent_theme != $child_theme && $found_folder = icl_tf_determine_mo_folder($child_theme)){
                $languages_folders['child'] = $found_folder;
            }
            $iclsettings['theme_language_folders'] = $languages_folders;

        }
        $this->save_settings($iclsettings);
        echo '1|'.$icl_tl_type;
        break;
    case 'dismiss_help':
        icl_set_setting('dont_show_help_admin_notice', true);
        icl_save_settings();
        break;
    case 'dismiss_page_estimate_hint':
        icl_set_setting('dismiss_page_estimate_hint', !icl_get_setting('dismiss_page_estimate_hint'));
        icl_save_settings();
        break;
    case 'dismiss_upgrade_notice':
        icl_set_setting('hide_upgrade_notice', implode('.', array_slice(explode('.', ICL_SITEPRESS_VERSION), 0, 3)));
        icl_save_settings();
        break;
    case 'setup_got_to_step1':
        icl_set_setting('existing_content_language_verified', 0);
        icl_set_setting('setup_wizard_step', 1);
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}icl_translations");
        $wpdb->update($wpdb->prefix . 'icl_languages', array('active' => 0), array('active' => 1));
        icl_save_settings();

        break;
    case 'setup_got_to_step2':
        icl_set_setting('setup_wizard_step', 2);
        icl_save_settings();
        break;
    case 'toggle_show_translations':
        icl_set_setting('show_translations_flag', intval(!icl_get_setting('show_translations_flag', false)));
        icl_save_settings();
        break;
    case 'icl_messages':
        //TODO: handle with Translation Proxy
        if ( ! icl_get_setting( 'icl_disable_reminders' ) ) {
            break;
        }
        exit;
    case 'icl_help_links':
        if ( isset( $iclq ) && $iclq ) {
        $links = $iclq->get_help_links();
            $lang  = icl_get_setting( 'admin_default_language' );
        if (!isset($links['resources'][$lang])) {
            $lang = 'en';
        }

        if (isset($links['resources'][$lang])) {
            $output = '<ul>';
            foreach( $links['resources'][$lang]['resource'] as $resource) {
                if (isset($resource['attr'])) {
                    $title = $resource['attr']['title'];
                    $url = $resource['attr']['url'];
                    $icon = $resource['attr']['icon'];
                    $icon_width = $resource['attr']['icon_width'];
                    $icon_height = $resource['attr']['icon_height'];
                } else {
                    $title = $resource['title'];
                    $url = $resource['url'];
                    $icon = $resource['icon'];
                    $icon_width = $resource['icon_width'];
                    $icon_height = $resource['icon_height'];
                }
                $output .= '<li>';
                if ($icon) {
                    $output .= '<img style="vertical-align: bottom; padding-right: 5px;" src="' . $icon . '"';
                    if ($icon_width) {
                        $output .= ' width="' . $icon_width . '"';
                    }
                    if ($icon_height) {
                        $output .= ' height="' . $icon_height . '"';
                    }
                    $output .= '>';
                }
                $output .= '<a href="' . $url . '">' . $title . '</a></li>';

            }
            $output .= '</ul>';
            echo '1|' . $output;
        } else {
            echo '0|';
        }
        }
        break;
    case 'icl_show_sidebar':
        icl_set_setting('icl_sidebar_minimized', $_POST['state']=='hide'?1:0);
        icl_save_settings();
        break;
    case 'icl_promote_form':
        icl_set_setting('promote_wpml', @intval($_POST['icl_promote']));
        icl_save_settings();
        echo '1|';
        break;
    case 'save_translator_note':
        update_post_meta($_POST['post_id'], '_icl_translator_note', $_POST['note']);
        break;
    case 'icl_st_track_strings':
        foreach($_POST['icl_st'] as $k=>$v){
            $iclsettings['st'][$k] = $v;
        }
		if(isset($iclsettings)) {
        	$this->save_settings($iclsettings);
		}
        echo 1;
        break;
    case 'icl_st_more_options':
        $iclsettings['st']['translated-users'] = !empty($_POST['users']) ? array_keys($_POST['users']) : array();
        $this->save_settings($iclsettings);
        if(!empty($iclsettings['st']['translated-users'])){
            $sitepress_settings['st']['translated-users'] = $iclsettings['st']['translated-users'];
            icl_st_register_user_strings_all();
        }
        echo 1;
        break;
    case 'icl_st_ar_form':
        // Auto register string settings.
        $iclsettings['st']['icl_st_auto_reg'] = $_POST['icl_auto_reg_type'];
        $this->save_settings($iclsettings);
        echo 1;
        break;
    case 'icl_hide_languages':
        $iclsettings['hidden_languages'] = empty($_POST['icl_hidden_languages']) ? array() : $_POST['icl_hidden_languages'];
        $this->set_setting('hidden_languages', array()); //reset current value
        $active_languages = $this->get_active_languages();
        if(!empty($iclsettings['hidden_languages'])){
             if(1 == count($iclsettings['hidden_languages'])){
                 $out = sprintf(__('%s is currently hidden to visitors.', 'sitepress'),
                    $active_languages[$iclsettings['hidden_languages'][0]]['display_name']);
             }else{
                 foreach($iclsettings['hidden_languages'] as $l){
                     $_hlngs[] = $active_languages[$l]['display_name'];
                 }
                 $hlangs = join(', ', $_hlngs);
                 $out = sprintf(__('%s are currently hidden to visitors.', 'sitepress'), $hlangs);
             }
             $out .= ' ' . sprintf(__('You can enable its/their display for yourself, in your <a href="%s">profile page</a>.', 'sitepress'),
                                            'profile.php#wpml');
        } else {
            $out = __('All languages are currently displayed.', 'sitepress');
        }
        $this->save_settings($iclsettings);
        echo '1|'.$out;
        break;
    case 'icl_adjust_ids':
        $iclsettings['auto_adjust_ids'] = @intval($_POST['icl_adjust_ids']);
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'icl_automatic_redirect':
		if (!isset($_POST['icl_remember_language']) || $_POST['icl_remember_language'] < 24) {
			$_POST['icl_remember_language'] = 24;
		}
        $iclsettings['automatic_redirect'] = @intval($_POST['icl_automatic_redirect']);
        $iclsettings['remember_language'] = @intval($_POST['icl_remember_language']);
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'icl_troubleshooting_more_options':
        $iclsettings['troubleshooting_options'] = $_POST['troubleshooting_options'];
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'reset_languages':
        icl_reset_language_data();
        break;
    case 'icl_support_update_ticket':
        if (isset($_POST['ticket'])) {
            $temp = str_replace('icl_support_ticket_', '', $_POST['ticket']);
            $temp = explode('_', $temp);
            $id = (int)$temp[0];
            $num = (int)$temp[1];
            if ($id && $num) {
                if (isset($iclsettings['icl_support']['tickets'][$id])) {
                    $iclsettings['icl_support']['tickets'][$id]['messages'] = $num;
                    $this->save_settings($iclsettings);
                }
            }
        }
        break;
    case 'icl_custom_tax_sync_options':
        if(!empty($_POST['icl_sync_tax'])){
            foreach($_POST['icl_sync_tax'] as $k=>$v){
                $iclsettings['taxonomies_sync_option'][$k] = $v;
                if($v){
                    $this->verify_taxonomy_translations($k);
                }
            }
			if ( isset( $iclsettings ) ) {
				$this->save_settings($iclsettings);
			}
        }
        echo '1|';
        break;
	case 'icl_custom_posts_sync_options':

		if ( ! empty( $_POST[ 'icl_sync_custom_posts' ] ) ) {
			foreach ( $_POST[ 'icl_sync_custom_posts' ] as $k => $v ) {
				$iclsettings[ 'custom_posts_sync_option' ][ $k ] = $v;
				if ( $v ) {
					$this->verify_post_translations( $k );
				}
			}

			$posts_slug_translation = $this->get_setting( 'posts_slug_translation' );
			if ( isset( $posts_slug_translation[ 'on' ] ) && $posts_slug_translation[ 'on' ] ) {
				if ( isset( $_POST[ 'translate_slugs' ] ) && ! empty( $_POST[ 'translate_slugs' ] ) ) {

					foreach ( $_POST[ 'translate_slugs' ] as $type => $data ) {

						$iclsettings[ 'posts_slug_translation' ][ 'types' ][ $type ] = isset( $data[ 'on' ] ) ? intval( ! empty( $data[ 'on' ] ) ) : false;

						if ( empty( $iclsettings[ 'posts_slug_translation' ][ 'types' ][ $type ] ) ) {
							continue;
						}

						// assume it is already registered
						$post_type_obj = get_post_type_object( $type );
						$slug          = trim( $post_type_obj->rewrite[ 'slug' ], '/' );
						$string_id     = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_strings WHERE name = %s AND value = %s ",
						                                                 'URL slug: ' . $slug,
						                                                 $slug ) );
						if ( empty( $string_id ) ) {
							$string_id = icl_register_string( 'WordPress', 'URL slug: ' . $slug, $slug );
						}
						if ( $string_id ) {
							foreach ( $this->get_active_languages() as $lang ) {
								$string_translation_settings = $this->get_setting( 'st' );
								if ( $lang[ 'code' ] != $string_translation_settings[ 'strings_language' ] ) {
									$data[ 'langs' ][ $lang[ 'code' ] ] = join( '/',
									                                            array_map( 'sanitize_title_with_dashes',
									                                                       explode( '/',
									                                                                $data[ 'langs' ][ $lang[ 'code' ] ] ) ) );
									$data[ 'langs' ][ $lang[ 'code' ] ] = urldecode( $data[ 'langs' ][ $lang[ 'code' ] ] );
									icl_add_string_translation( $string_id,
									                            $lang[ 'code' ],
									                            $data[ 'langs' ][ $lang[ 'code' ] ],
									                            ICL_TM_COMPLETE );
								}
							}
							icl_update_string_status( $string_id );
						}
					}
				}
			}

			if ( isset( $iclsettings ) ) {
				$this->save_settings( $iclsettings );
			}
		}
        echo '1|';
        break;
	case 'copy_from_original':
		/*
		 * apply filtering as to add further elements
		 * filters will have to like as such
		 * add_filter('wpml_copy_from_original_fields', 'my_copy_from_original_fields');
		 *
		 * function my_copy_from_original_fields( $elements ) {
		 *  $custom_field = 'editor1';
		 *  $elements[ 'customfields' ][ $custom_fields ] = array(
		 *    'editor_name' => 'custom_editor_1',
		 *    'editor_type' => 'editor',
		 *    'value'       => 'test'
		 *  );
		 *
		 *  $custom_field = 'editor2';
		 *  $elements[ 'customfields' ][ $custom_fields ] = array(
		 *    'editor_name' => 'textbox1',
		 *    'editor_type' => 'text',
		 *    'value'       => 'testtext'
		 *  );
		 *
		 *  return $elements;
		 * }
		 * This filter would result in custom_editor_1 being populated with the value "test"
		 * and the textfield with id #textbox1 to be populated with "testtext".
		 * editor type is always either text when populating general fields or editor when populating
		 * a wp editor. The editor id can be either judged from the arguments used in the wp_editor() call
		 * or from looking at the tinyMCE.Editors object that the custom post type's editor sends to the browser.
		 */
		echo wp_json_encode( wpml_copy_from_original_fields() );
		break;
    case 'save_user_preferences':
        $user_preferences = $this->get_user_preferences();
		$this->set_user_preferences(array_merge_recursive( $user_preferences, $_POST['user_preferences']));
        $this->save_user_preferences();
        break;
    case 'wpml_cf_translation_preferences':
        if (empty($_POST['custom_field'])) {
            echo '<span style="color:#FF0000;">'
            . __('Error: No custom field', 'wpml') . '</span>';
            die();
        }
        $_POST['custom_field'] = @strval($_POST['custom_field']);
        if (!isset($_POST['translate_action'])) {
            echo '<span style="color:#FF0000;">'
            . __('Error: Please provide translation action', 'wpml') . '</span>';
            die();
        }
        $_POST['translate_action'] = @intval($_POST['translate_action']);
        if (defined('WPML_TM_VERSION')) {
            global $iclTranslationManagement;
            if (!empty($iclTranslationManagement)) {
                $iclTranslationManagement->settings['custom_fields_translation'][$_POST['custom_field']] = $_POST['translate_action'];
                $iclTranslationManagement->save_settings();
                echo '<strong><em>' . __('Settings updated', 'wpml') . '</em></strong>';
            } else {
                echo '<span style="color:#FF0000;">'
                . __('Error: WPML Translation Management plugin not initiated', 'wpml')
                . '</span>';
            }
        } else {
            echo '<span style="color:#FF0000;">'
            . __('Error: Please activate WPML Translation Management plugin', 'wpml')
                    . '</span>';
        }
        break;
    case 'icl_seo_options':
        $iclsettings['seo']['head_langs'] = isset($_POST['icl_seo_head_langs']) ? intval($_POST['icl_seo_head_langs']) : 0;
        $iclsettings['seo']['canonicalization_duplicates'] = isset($_POST['icl_seo_canonicalization_duplicates']) ? intval($_POST['icl_seo_canonicalization_duplicates']) : 0;
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'dismiss_object_cache_warning':
        $iclsettings['dismiss_object_cache_warning'] = true;
        $this->save_settings($iclsettings);
        echo '1|';
        break;
    case 'update_option':
        $iclsettings[$_REQUEST['option']] = $_REQUEST['value'];
        $this->save_settings($iclsettings);
        break;
	case 'connect_translations':
		$new_trid = $_POST['new_trid'];
		$post_type = $_POST['post_type'];
		$post_id = $_POST['post_id'];
		$set_as_source = $_POST['set_as_source'];

		$language_details = $sitepress->get_element_language_details($post_id, 'post_' . $post_type);

		if ( $set_as_source ) {
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'source_language_code' => $language_details->language_code ), array( 'trid' => $new_trid, 'element_type' => 'post_' . $post_type ) );
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'source_language_code' => null, 'trid' => $new_trid ), array( 'element_id' => $post_id, 'element_type' => 'post_' . $post_type ) );
		} else {
			$original_element_language = $sitepress->get_default_language();
			$trid_elements             = $sitepress->get_element_translations( $new_trid, 'post_' . $post_type );
			if($trid_elements) {
				foreach ( $trid_elements as $trid_element ) {
					if ( $trid_element->original ) {
						$original_element_language = $trid_element->language_code;
						break;
					}
				}
			}
			$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'source_language_code' => $original_element_language, 'trid' => $new_trid ), array( 'element_id' => $post_id, 'element_type' => 'post_' . $post_type ) );
		}
		echo wp_json_encode(true);
		break;
	case 'get_posts_from_trid':
		$trid = $_POST['trid'];
		$post_type = $_POST['post_type'];

		$translations = $sitepress->get_element_translations($trid, 'post_' . $post_type);

		$results = array();
		foreach($translations as $language_code => $translation) {
			$post = get_post($translation->element_id);
			$title = $post->post_title ? $post->post_title : strip_shortcodes(wp_trim_words( $post->post_content, 50 ));
			$source_language_code = $translation->source_language_code;
			$results[] = (object) array('language' => $language_code, 'title' => $title, 'source_language' => $source_language_code);
		}
		echo wp_json_encode($results);
		break;
	case 'get_orphan_posts':
		$trid = $_POST['trid'];
		$post_type = $_POST['post_type'];
		$source_language = $_POST['source_language'];

		$results = SitePress::get_orphan_translations($trid, $post_type, $source_language);

		echo wp_json_encode($results);

		break;
    default:
	    if(function_exists('ajax_' . $request)) {
		    $function_name = 'ajax_' . $request;
		    $function_name();
	    } else {
		    do_action('icl_ajx_custom_call', $request, $_REQUEST);
	    }
}
exit;

/**
 * wpml_copy_from_original_fields
 * Gets the content of a post, its excerpt as well as its title and returns it as an array
 *
 * @param
 *
 * @return array containing all the fields information
 */
function wpml_copy_from_original_fields() {
	global $wpdb;
	$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s", $_POST[ 'trid' ], $_POST[ 'lang' ] ) );
	$post    = get_post( $post_id );

	$fields_to_copy            = array( 'content' => 'post_content' );
	$fields_to_copy[ 'title' ] = 'post_title';

	$fields_contents = array();
	if ( ! empty( $post ) ) {
		foreach ( $fields_to_copy as $editor_key => $editor_field ) { //loops over the three fields to be inserted into the array
			if ( $editor_key == 'content' || $editor_key == 'excerpt' ) { //
				if ( $editor_key == 'content' ) {
					$editor_var = $_POST[ 'content_type' ]; //these variables are supplied by a javascript call in scripts.js icl_copy_from_original(lang, trid)
				} elseif ( $editor_key == 'excerpt' ) {
					$editor_var = $_POST[ 'excerpt_type' ];
				}
				if (isset($editor_var) && isset($_POST[ $editor_var ]) && $_POST[ $editor_var ] == 'rich' ) {
					$fields_contents[ $editor_key ] = htmlspecialchars_decode( wp_richedit_pre( $post->$editor_field ) );
				} else {
					$fields_contents[ $editor_key ] = htmlspecialchars_decode( wp_htmledit_pre( $post->$editor_field ) );
				}
			} elseif ( $editor_key == 'title' ) {
				$fields_contents[ $editor_key ] = strip_tags( $post->$editor_field );
			}
		}
		$fields_contents[ 'customfields' ] = apply_filters( 'wpml_copy_from_original_custom_fields', wpml_copy_from_original_custom_fields( $post ) );
	} else {
		$fields_contents[ 'error' ] = __( 'Post not found', 'sitepress' );
	}
	do_action( 'icl_copy_from_original', $post_id );

	return $fields_contents;
}

/**
 * wpml_copy_from_original_custom_fields
 * Gets the content of a custom posts custom field , its excerpt as well as its title and returns it as an array
 *
 * @param  (type) about this param
 *
 * @return array (type)
 */

function wpml_copy_from_original_custom_fields( $post ) {

	$elements                 = array();
	$elements [ 'post_type' ] = $post->post_type;
	$elements[ 'excerpt' ]    = array(
		'editor_name' => 'excerpt',
		'editor_type' => 'text',
		'value'       => $post->post_excerpt
	);

	return $elements;
}
