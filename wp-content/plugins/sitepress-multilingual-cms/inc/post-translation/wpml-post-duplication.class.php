<?php

class WPML_Post_Duplication{

    function get_duplicates( $master_post_id ) {
        global $wpdb, $wpml_post_translations;
        $duplicates = array();

        $post_ids_query = "SELECT post_id
								FROM {$wpdb->postmeta}
			                    WHERE meta_key='_icl_lang_duplicate_of'
			                        AND meta_value=%d";
        $post_ids_prepare = $wpdb->prepare ( $post_ids_query, $master_post_id );
        $post_ids = $wpdb->get_col ( $post_ids_prepare );

        foreach ( $post_ids as $post_id ) {
            $language_code = $wpml_post_translations->get_element_lang_code ( $post_id );
            $duplicates[ $language_code ] = $post_id;
        }

        return $duplicates;
    }

    function make_duplicate( $master_post_id, $lang ) {
        global $iclTranslationManagement, $wpml_post_translations;

        static $duplicated_post_ids;
        if(!isset($duplicated_post_ids)) {
            $duplicated_post_ids = array();
        }

        //It is already done? (avoid infinite recursions)
        if(in_array($master_post_id . '|' . $lang, $duplicated_post_ids)) {
            return true;
        }
        $duplicated_post_ids[] = $master_post_id . '|' . $lang;

        global $sitepress, $sitepress_settings, $wpdb;

        do_action( 'icl_before_make_duplicate', $master_post_id, $lang );

        $master_post = get_post( $master_post_id );

        $is_duplicated = false;
        $wpml_post_translations->get_element_translations($master_post_id, false, false);

        if ( isset( $translations[ $lang ] ) ) {
            $post_array[ 'ID' ] = $translations[ $lang ];
            $is_duplicated      = get_post_meta( $translations[ $lang ]->element_id, '_icl_lang_duplicate_of', true );
        }

        // covers the case when deleting in bulk from all languages
        // setting post_status to trash before wp_trash_post runs issues an WP error
        $posts_to_delete_or_restore_in_bulk = array();
        if ( isset( $_GET[ 'action' ] ) && ( $_GET[ 'action' ] == 'trash' || $_GET[ 'action' ] == 'untrash' ) && isset( $_GET[ 'lang' ] ) && $_GET[ 'lang' ] == 'all' ) {
            static $posts_to_delete_or_restore_in_bulk;
            if ( is_null( $posts_to_delete_or_restore_in_bulk ) ) {
                $posts_to_delete_or_restore_in_bulk = isset( $_GET[ 'post' ] ) && is_array( $_GET[ 'post' ] ) ? $_GET[ 'post' ] : array();
            }
        }

        $post_array[ 'post_author' ]   = $master_post->post_author;
        $post_array[ 'post_date' ]     = $master_post->post_date;
        $post_array[ 'post_date_gmt' ] = $master_post->post_date_gmt;
        $post_array[ 'post_content' ]  = addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_post->post_content, $lang, array( 'context' => 'post', 'attribute' => 'content', 'key' => $master_post->ID ) ));
        $post_array[ 'post_title' ]    = addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_post->post_title, $lang, array( 'context' => 'post', 'attribute' => 'title', 'key' => $master_post->ID ) ));
        $post_array[ 'post_excerpt' ]  = addslashes_gpc(apply_filters( 'icl_duplicate_generic_string', $master_post->post_excerpt, $lang, array( 'context' => 'post', 'attribute' => 'excerpt', 'key' => $master_post->ID ) ));

        if ( isset( $sitepress_settings[ 'sync_post_status' ] ) && $sitepress_settings[ 'sync_post_status' ] ) {
            $sync_post_status = true;
        } else {
            $sync_post_status = ( !isset( $post_array[ 'ID' ] ) || ( $sitepress_settings[ 'sync_delete' ] && $master_post->post_status == 'trash' ) || $is_duplicated );
            $sync_post_status &= ( !$posts_to_delete_or_restore_in_bulk || ( !isset( $post_array[ 'ID' ] ) || !in_array( $post_array[ 'ID' ], $posts_to_delete_or_restore_in_bulk ) ) );
        }

        if ( $sync_post_status || get_post_status ( $post_array[ 'ID' ] ) === 'auto-draft' ) {
            $post_array[ 'post_status' ] = $master_post->post_status;
        }

        $post_array[ 'comment_status' ] = $master_post->comment_status;
        $post_array[ 'ping_status' ]    = $master_post->ping_status;
        $post_array[ 'post_name' ]      = $master_post->post_name;

        if ( $master_post->post_parent ) {
            $parent                      = icl_object_id( $master_post->post_parent, $master_post->post_type, false, $lang );
            $post_array[ 'post_parent' ] = $parent;
        }

        $post_array[ 'menu_order' ]     = $master_post->menu_order;
        $post_array[ 'post_type' ]      = $master_post->post_type;
        $post_array[ 'post_mime_type' ] = $master_post->post_mime_type;


        $trid = $sitepress->get_element_trid( $master_post->ID, 'post_' . $master_post->post_type );

        if ( isset( $post_array[ 'ID' ] ) ) {
            $id = wp_update_post( $post_array );
        } else {
            $id = $iclTranslationManagement->icl_insert_post( $post_array, $lang );
        }

        require_once ICL_PLUGIN_PATH . '/inc/cache.php';
        icl_cache_clear( $post_array[ 'post_type' ] . 's_per_language' );

        global $ICL_Pro_Translation;
        /** @var WPML_Pro_Translation $ICL_Pro_Translation */
        if ( $ICL_Pro_Translation ) {
            $ICL_Pro_Translation->_content_fix_links_to_translated_content( $id, $lang );
        }

        if ( !is_wp_error( $id ) ) {

            $sitepress->set_element_language_details( $id, 'post_' . $master_post->post_type, $trid, $lang );

            $iclTranslationManagement->save_post_actions( $id, get_post( $id ), ICL_TM_DUPLICATE );

            $this->duplicate_fix_children( $master_post_id, $lang );

            // dup comments
            if ( $sitepress->get_option( 'sync_comments_on_duplicates' ) ) {
                $this->duplicate_comments( $id, $master_post_id );
            }

            // make sure post name is copied
            $wpdb->update( $wpdb->posts, array( 'post_name' => $master_post->post_name ), array( 'ID' => $id ) );

            update_post_meta( $id, '_icl_lang_duplicate_of', $master_post->ID );

            if ( $sitepress->get_option( 'sync_post_taxonomies' ) ) {
                $this->duplicate_taxonomies( $master_post_id, $lang );
            }
            $this->duplicate_custom_fields( $master_post_id, $lang );

            // Duplicate post format after the taxonomies because post format is stored
            // as a taxonomy by WP.
            if ( $sitepress->get_setting('sync_post_format' ) ) {
                $_wp_post_format = get_post_format( $master_post_id );
                set_post_format( $id, $_wp_post_format );
            }

            $ret = $id;
            do_action( 'icl_make_duplicate', $master_post_id, $lang, $post_array, $id );

        } else {
            $ret = false;
        }

        return $ret;
    }

    private function duplicate_fix_children( $master_post_id, $lang ) {
        global $wpdb;

        $post_type = $wpdb->get_var (
            $wpdb->prepare ( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $master_post_id )
        );
        $master_children = $wpdb->get_col (
            $wpdb->prepare (
                "SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type != 'revision'",
                $master_post_id
            )
        );
        $dup_parent = icl_object_id ( $master_post_id, $post_type, false, $lang );

        if ( $master_children ) {
            foreach ( $master_children as $master_child ) {
                $dup_child = icl_object_id ( $master_child, $post_type, false, $lang );
                if ( $dup_child ) {
                    $wpdb->update ( $wpdb->posts, array( 'post_parent' => $dup_parent ), array( 'ID' => $dup_child ) );
                }
                $this->duplicate_fix_children ( $master_child, $lang );
            }
        }
    }

    private function duplicate_comments( $post_id, $master_post_id ) {
        global $wpdb;

        // delete existing comments
        $current_comments = $wpdb->get_results( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID = %d", $post_id ) );
        foreach ( $current_comments as $current_comment ) {
            if ( isset( $current_comment->comment_ID ) && is_numeric( $current_comment->comment_ID ) ) {
                wp_delete_comment( $current_comment->comment_ID );
            }
        }


        $original_comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_post_id = %d", $master_post_id ), ARRAY_A );

        $post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $post_id ) );
        $language  = $wpdb->get_var( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $post_id, 'post_' . $post_type ) );

        $wpdb->update( $wpdb->posts, array( 'comment_count' => count( $original_comments ) ), array( 'ID' => $post_id ) );

        foreach ( $original_comments as $comment ) {

            $original_comment_id = $comment[ 'comment_ID' ];
            unset( $comment[ 'comment_ID' ] );

            $comment[ 'comment_post_ID' ] = $post_id;
            $wpdb->insert( $wpdb->comments, $comment );
            $comment_id = $wpdb->insert_id;

            update_comment_meta( $comment_id, '_icl_duplicate_of', $original_comment_id );

            // comment meta
            $meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id=%d", $original_comment_id ) );
            foreach ( $meta as $meta_data ) {
                if ( is_object( $meta_data ) && isset( $meta_data->meta_key ) && isset( $meta_data->meta_value ) ) {
                    $wpdb->insert( $wpdb->commentmeta, array(
                        'comment_id' => $comment_id,
                        'meta_key'   => $meta_data->meta_key,
                        'meta_value' => $meta_data->meta_value
                    ) );
                }
            }

            $original_comment_tr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $original_comment_id, 'comment' ) );

            if ( $original_comment_tr && isset( $original_comment_tr->trid ) ) {
                $comment_translation = array(
                    'element_type'  => 'comment',
                    'element_id'    => $comment_id,
                    'trid'          => $original_comment_tr->trid,
                    'language_code' => $language,
                    /*'source_language_code'  => $original_comment_tr->language_code */
                );

                $comments_map[ $original_comment_id ] = array( 'trid' => $original_comment_tr->trid, 'comment' => $comment_id );

                $existing_translation_tr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND element_type=%s AND language_code=%s", $original_comment_tr->trid, 'comment', $language ) );

                if ( $existing_translation_tr ) {
                    $wpdb->update( $wpdb->prefix . 'icl_translations', $comment_translation, array( 'trid' => $comment_id, 'element_type' => 'comment', 'language_code' => $language ) );
                } else {
                    $wpdb->insert( $wpdb->prefix . 'icl_translations', $comment_translation );
                }
            }
        }

        // sync parents
        if(isset($comments_map)) {
            foreach ( $original_comments as $comment ) {
                if ( $comment[ 'comment_parent' ] ) {

                    $tr_comment_id = $comments_map[ $comment[ 'comment_ID' ] ][ 'comment' ];
                    $tr_parent     = icl_object_id( $comment[ 'comment_parent' ], 'comment', false, $language );
                    if ( $tr_parent ) {
                        $wpdb->update( $wpdb->comments, array( 'comment_parent' => $tr_parent ), array( 'comment_ID' => $tr_comment_id ) );
                    }

                }
            }
        }
    }

    private function duplicate_taxonomies( $master_post_id, $lang ) {
        global $sitepress;

        $post_type = get_post_field ( 'post_type', $master_post_id );
        $taxonomies = get_object_taxonomies ( $post_type );
        $trid = $sitepress->get_element_trid ( $master_post_id, 'post_' . $post_type );
        if ( $trid ) {
            $translations = $sitepress->get_element_translations ( $trid, 'post_' . $post_type, false, false, true );
            if ( isset( $translations[ $lang ] ) ) {
                $duplicate_post_id = $translations[ $lang ]->element_id;
                /* If we have an existing post, we first of all remove all terms currently attached to it.
                 * The main reason behind is the removal of the potentially present default category on the post.
                 */
                wp_delete_object_term_relationships ( $duplicate_post_id, $taxonomies );
            } else {
                return false; // translation not found!
            }
        }

        foreach ( $taxonomies as $taxonomy ) {
            if ( $sitepress->is_translated_taxonomy ( $taxonomy ) ) {
                WPML_Terms_Translations::sync_post_and_taxonomy_terms_language ( $master_post_id, $taxonomy, true );
            }
        }
        return true;
    }

    private function duplicate_custom_fields( $master_post_id, $lang ) {
        /** @var wpdb $wpdb */
        /** @var SitePress $sitepress */
        global $wpdb, $sitepress;

        $duplicate_post_id = false;
        $post_type         = get_post_field( 'post_type', $master_post_id );

        $trid = $sitepress->get_element_trid( $master_post_id, 'post_' . $post_type );
        if ( $trid ) {
            $translations = $sitepress->get_element_translations( $trid, 'post_' . $post_type );
            if ( isset( $translations[ $lang ] ) ) {
                $duplicate_post_id = $translations[ $lang ]->element_id;
            } else {
                return false; // translation not found!
            }
        }

        $exceptions         = apply_filters( 'wpml_duplicate_custom_fields_exceptions', array() );
        $default_exceptions = array(
            '_wp_old_slug',
            '_edit_last',
            '_edit_lock',
            '_icl_translator_note',
            '_icl_lang_duplicate_of',
            '_wpml_media_duplicate',
            '_wpml_media_featured'
        );
        $exceptions         = array_merge( $exceptions, $default_exceptions );
        $exceptions         = array_unique( $exceptions );

        $exceptions_in = ' IN ( ' . wpml_prepare_in( $exceptions ) . ') ';

        $post_meta_master = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value
                 FROM {$wpdb->postmeta}
                 WHERE post_id = %d
                    AND meta_key NOT " . $exceptions_in,
                $master_post_id
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta}
                 WHERE post_id = %d
                  AND meta_key NOT " . $exceptions_in,
                $duplicate_post_id
            )
        );

        foreach ( $post_meta_master as $post_meta ) {
	        $icl_duplicate_generic_string = apply_filters( 'icl_duplicate_generic_string', $post_meta->meta_value, $lang, array(
		                                                                                     'context'   => 'custom_field',
		                                                                                     'attribute' => 'value',
		                                                                                     'key'       => $post_meta->meta_key
	                                                                                     ) );
	        $wpml_duplicate_generic_string = apply_filters( 'wpml_duplicate_generic_string', $icl_duplicate_generic_string, $lang, array(
		                                                                                     'context'   => 'custom_field',
		                                                                                     'attribute' => 'value',
		                                                                                     'key'       => $post_meta->meta_key
	                                                                                     ) );
	        $post_meta->meta_value = $wpml_duplicate_generic_string;

            $wpdb->query(
                $wpdb->prepare(
                    " INSERT INTO {$wpdb->postmeta} (post_id,meta_key, meta_value)
                      VALUES (%d, %s, %s)",
                    $duplicate_post_id,
                    $post_meta->meta_key,
                    $post_meta->meta_value
                )
            );

        }

        return true;
    }
}