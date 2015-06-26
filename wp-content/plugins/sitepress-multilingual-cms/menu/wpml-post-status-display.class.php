<?php

class WPML_Post_Status_Display {
	private $posts = array();
	private $active_langs;
	private $trids = array();
	private $userID = false;
	private $lang_pairs = array();
	private $use_editor = false;

	public function __construct( $post_ids ) {
		global $sitepress;

		$this->active_langs = $sitepress->get_active_languages();
		if ( ! empty( $post_ids ) ) {
			$data = $this->post_status_information( $post_ids );
		} else {
			$data = array();
		}
		$this->format_data( $data );
		$this->userID = get_current_user_id();
		$tm_method    = $sitepress->get_setting( 'doc_translation_method' );

		if ( $tm_method ) {
			$this->use_editor = true;
		}
	}


	/**
	 * Returns the html of a status icon.
	 *
	 * @param $link string Link the status icon is to point to.
	 * @param $text string Hover text for the status icon.
	 * @param $img string Name of the icon image file to be used.
	 *
	 * @return string
	 */
	private function render_status_icon( $link, $text, $img ) {

		$icon_html = '<a href="' . $link . '" title="' . $text . '">';
		$icon_html .= '<img style="padding:1px;margin:2px;" border="0" src="' . ICL_PLUGIN_URL . '/res/img/' . $img . '" alt="' . $text . '" width="16" height="16"/>';
		$icon_html .= '</a>';

		return $icon_html;
	}

	/**
	 * Runs a combined database query fetching all translation status data, necessary for rendering the admin post list, for an array of input post ids.
	 *
	 * @param $post_ids array
	 *
	 * @return array
	 */
	private function post_status_information( $post_ids ) {
		global $wpdb;

		$i             = $wpdb->prefix . "icl_translations";
		$l             = $wpdb->prefix . "icl_languages";
		$j             = $wpdb->prefix . "icl_translate_job";
		$s             = $wpdb->prefix . "icl_translation_status";
		$p             = $wpdb->posts;
		$post_id_in    = 'IN (' . implode( ',', $post_ids ) . ')';
		$in_updateable = 'IN (' . ICL_TM_COMPLETE . ', ' . ICL_TM_NOT_TRANSLATED . ')';

		$status_query = "SELECT
								l.code,
								i.trid,
								(SELECT element_id FROM {$i} WHERE trid = i.trid AND language_code = l.code LIMIT 1)
								ID,
								(SELECT source_language_code FROM {$i} WHERE trid = i.trid AND language_code = l.code LIMIT 1)
								src_lang,
								(SELECT SUBSTRING(element_type,  6 ) FROM {$i} WHERE trid = i.trid AND element_id IS NOT NULL AND source_language_code IS NULL LIMIT 1 )
									post_type,
								( CASE
									WHEN sta.translation_id = i.translation_id
										THEN
											CASE
												WHEN sta.status NOT {$in_updateable}
													THEN sta.status
												WHEN
													(SELECT {$j}.translated FROM {$j} WHERE sta.rid = {$j}.rid  AND sta.status != " . ICL_TM_COMPLETE . " AND {$j}.revision IS NULL LIMIT 1 )
														THEN " . ICL_TM_WAITING_FOR_TRANSLATOR . "
												WHEN (SELECT needs_update FROM {$s} WHERE translation_id = i.translation_id LIMIT 1) = 1
													THEN " . ICL_TM_NEEDS_UPDATE . "
											    ELSE
		                                            " . ICL_TM_COMPLETE . "
											END
									ELSE
										" . ICL_TM_COMPLETE . "
									END)
									status,
									(SELECT {$j}.job_id FROM {$j} WHERE sta.rid = {$j}.rid AND {$j}.revision IS NULL )
									job_id,
    								(SELECT element_id FROM {$i} WHERE trid = i.trid AND (language_code = src_lang OR source_language_code IS NULL AND src_lang IS NULL ) LIMIT 1)
										original,
									(SELECT translator_id FROM {$s} WHERE translation_id = i.translation_id AND i.language_code = l.code LIMIT 1)
										translator_id,
									(SELECT translation_service FROM {$s} WHERE translation_id = i.translation_id AND i.language_code = l.code LIMIT 1)
										service
									FROM {$l} AS l
										LEFT OUTER JOIN {$i} AS i
											ON i.language_code = l.code
										LEFT OUTER JOIN {$i} AS t ON t.trid = i.trid
										LEFT OUTER JOIN {$s} AS sta
											ON i.translation_id = sta.translation_id
										LEFT OUTER JOIN {$p} AS p
											ON p.ID = i.element_id AND CONCAT('post_',p.post_type) = i.element_type  AND (l.code = i.language_code OR l.code = t.language_code)
									WHERE l.active = 1
									AND i.trid IN (
													SELECT {$i}.trid FROM {$i} JOIN {$p} ON {$i}.element_type = CONCAT('post_',{$p}.post_type)
																							AND {$i}.element_id = {$p}.ID
																							AND {$p}.ID {$post_id_in}
												  )
									GROUP BY ID, code, trid";

		$data = $wpdb->get_results( $status_query, ARRAY_A );

		return $data;
	}

	/**
	 * Takes the data returned from the SQL call in \self::post_status_information
	 * and saves into in this object.
	 *
	 * @param $data array
	 */
	private function format_data( $data ) {
		foreach ( $data as $entry ) {

			$trid = $entry[ 'trid' ];
			$lang = $entry[ 'code' ];
			$ID   = $entry[ 'ID' ];

			if ( !isset( $this->trids[ $trid ] ) ) {
				$this->trids[ $trid ] = array_fill_keys( array_keys( $this->active_langs ), false );
			}
			if ( $ID ) {
				$this->trids[ $trid ] [ $lang ] = $ID;
			} else {
				$this->trids[ $trid ] [ $lang ] = $entry;
			}

			if ( $ID ) {
				$this->posts[ $ID ] = $entry;
			}
		}

	}

	/**
	 * Fetches an attribute of a post from the information cached in this object.
	 *
	 * @param $post_id int
	 * @param $attr_name string
	 *
	 * @return bool | string
	 */
	private function get_attr( $post_id, $attr_name ) {

		$attr = false;

		if ( $post_id && isset( $this->posts[ $post_id ] ) && isset( $this->posts[ $post_id ][ $attr_name ] ) ) {
			$attr = $this->posts[ $post_id ][ $attr_name ];
		}

		return $attr;
	}

	/**
	 * Fetches an attribute of a post from the information cached in this object.
	 *
	 * @param $lang string
	 * @param $trid int
	 * @param $attr_name string
	 *
	 * @return bool|string
	 */
	public function get_attr_by_lang_and_trid( $lang, $trid, $attr_name ) {
		$attr = false;

		if ( isset( $this->trids[ $trid ] ) && isset( $this->trids[ $trid ][ $lang ] ) ) {
			if ( ! is_array( $this->trids[ $trid ][ $lang ] ) ) {
				$id   = $this->trids[ $trid ][ $lang ];
				$attr = $this->get_attr( $id, $attr_name );
			} elseif ( isset( $this->trids[ $trid ][ $lang ] [ $attr_name ] ) ) {
				$attr = $this->trids[ $trid ][ $lang ] [ $attr_name ];
			}
		}

		return $attr;
	}

	/**
	 * @param int $post_id ID of a post in an arbitrary language
	 * @param string $lang language_code of the desired post_id
	 *
	 * @return int post_id of the translation in $lang or 0 if none was found
	 */
	private function get_correct_id_in_lang( $post_id, $lang ) {

		$trid = $this->get_attr( $post_id, 'trid' );

		if ( isset( $this->trids[ $trid ] )
		     && isset( $this->trids[ $trid ][ $lang ] )
		     && ! is_array( $this->trids[ $trid ][ $lang ] )
		) {
			$correct_id = $this->trids[ $trid ] [ $lang ];
		} else {
			$correct_id = 0;
		}

		return $correct_id;
	}

    public function get_post_status( $trid, $lang ) {

        if ( !$trid ) {
            return ICL_TM_NOT_TRANSLATED;
        }

        $status  = $this->get_attr_by_lang_and_trid( $lang, $trid, 'status' );

        return apply_filters(
            'wpml_translation_status',
            $status,
            $trid,
            $lang,
            'post'
        );
    }

	/**
	 * This function takes a post ID and a language as input.
	 * It will always return the status icon,
	 * of the version of the input post ID in the language given as the second parameter.
	 *
	 * @param $post_id int original post ID
	 * @param $lang string language of the translation
	 *
	 * @return string
	 */
	public function get_status_html( $post_id, $lang ) {
		$trid = $this->get_attr( $post_id, 'trid' );
		$correct_id = $this->get_correct_id_in_lang( $post_id, $lang );
        $job_id = $this->get_attr_by_lang_and_trid( $lang, $trid, 'job_id' );
		if ( $correct_id ) {
			$service       = $this->get_attr( $correct_id, 'service' );
			$translator_id = $this->get_attr( $correct_id, 'translator_id' );
		} else {
			$service       = $this->get_attr_by_lang_and_trid( $lang, $trid, 'service' );
			$translator_id = $this->get_attr_by_lang_and_trid( $lang, $trid, 'translator_id' );
		}

		$status = $this->get_post_status($trid, $lang);
		$src_lang  = $this->get_attr( $post_id, 'code' );
		$post_type = $this->get_attr( $post_id, 'post_type' );
		$update = ICL_TM_NEEDS_UPDATE == $status;

		if ( $status == ICL_TM_IN_BASKET ) {
			list( $icon, $text, $link ) = $this->generate_in_basket_data();
		} elseif (  defined( 'WPML_TM_FOLDER' ) && $service === 'local' && $status != ICL_TM_NOT_TRANSLATED  ) {
			if ( $translator_id
                 && $this->use_editor
                 && $status == ICL_TM_IN_PROGRESS
                 && $this->userID != $translator_id ) {
                    list( $icon, $text, $link ) = $this->generate_in_progress_data (
                                                        $lang,
                                                        !$src_lang,
                                                        $job_id,
                                                        $update,
                                                        true
                                                    );
            } else {
                if ( ( !$this->use_editor
                       || $this->langs_compatible ( $lang, $src_lang )
                       || current_user_can ( 'manage_options' ) )
                ) {
                    list( $icon, $text, $link ) = $correct_id
                        ? $this->generate_edit_allowed_data ( $correct_id, $update )
                        : $this->generate_add_tm_data ( $lang, $job_id );
                } else {
                    list( $icon, $text, $link ) = $this->generate_wrong_translator_data (
                        $correct_id,
                        $status,
                        $lang
                    );
                }
            }

		} elseif ( defined( 'WPML_TM_FOLDER' )
		           && $service !== 'local'
		           && ( $status == ICL_TM_IN_PROGRESS || $status == ICL_TM_WAITING_FOR_TRANSLATOR )
		) {
			list( $icon, $text, $link ) = $this->generate_in_progress_data( $lang, ! $src_lang, $job_id, $update );
		} elseif (
			$status == ICL_TM_COMPLETE
			|| $status == ICL_TM_DUPLICATE
			|| $status == ICL_TM_NEEDS_UPDATE
			|| ( $status == ICL_TM_NOT_TRANSLATED && $correct_id && ! $service )
		) {
			list( $icon, $text, $link ) = $this->generate_edit_allowed_data( $correct_id, $update );
		} else {
			list( $icon, $text, $link ) = $this->generate_add_data( $trid,
			                                                        $lang,
			                                                        $src_lang,
			                                                        $post_type );
		}

        $link = apply_filters('wpml_link_to_translation', $link);
		$html = $this->render_status_icon( $link, $text, $icon );

		return $html;
	}

	/**
	 * Checks whether the current user is a translator in a given language pair.
	 *
	 * @param $to string
	 * @param $from string
	 *
	 * @return bool
	 */
	private function langs_compatible( $to, $from ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$this->userID = $user_id;
		$this->lang_pairs = get_user_meta( $user_id, $wpdb->prefix . 'language_pairs', true );

		$res = false;
		$this->lang_pairs = $this->lang_pairs ? $this->lang_pairs : get_user_meta( $user_id,
		                                                                           $wpdb->prefix . 'language_pairs',
		                                                                           true );

		if ( !empty( $this->lang_pairs ) && isset( $this->lang_pairs[ $from ][ $to ] ) ) {
			$res = true;
		}

		return $res;
	}

	/**
	 * @param $lang string language of the translation
	 * @param bool $original bool true if the post in question is the original
	 * @param $job_id int Job ID for the translation.
	 * @param bool $update True if a post ID already exists for the translation, false otherwise.
	 * @param bool $user_is_translator true if the current user is the translator of the post.
	 *
	 * @return array
	 */
	private function generate_in_progress_data( $lang, $original = false, $job_id, $update = false, $user_is_translator = false ) {

		$link = '###';
		$icon = 'in-progress.png';

		if ( $original ) {
			$text = sprintf( __( "You can't edit this document, because translations of this document are currently in progress.",
			                     'sitepress' ),
			                 $this->active_langs[ $lang ][ 'display_name' ] );
		} elseif ( $user_is_translator ) {
			if ( $job_id ) {
				if ( $update ) {
					$icon = 'needs-update.png';
					$text = sprintf( __( 'Update %s translation', 'sitepress' ),
					                 $this->active_langs[ $lang ][ 'display_name' ] );
				} else {
					$icon = 'edit_translation.png';
					$text = sprintf( __( 'Edit the %s translation', 'sitepress' ),
					                 $this->active_langs[ $lang ][ 'display_name' ] );
				}
                $link = $this->tm_editor_link($job_id);
			} else {
				$text = sprintf( __( "You can't edit this translation, because this translation to %s is already in progress.",
				                     'sitepress' ),
				                 $this->active_langs[ $lang ][ 'display_name' ] );
			}

		} else {
			$text = sprintf( __( "You can't edit this translation, because this translation to %s is already in progress.",
			                     'sitepress' ),
			                 $this->active_langs[ $lang ][ 'display_name' ] );
		}

		return array( $icon, $text, $link );
	}

    private function tm_editor_link( $job_id ) {
        $link_page = 'admin.php?page=' . WPML_TM_FOLDER . '/menu/' . 'translations-queue.php';
        $identifier = '&job_id=' . $job_id;
        $link = $link_page . $identifier;

        return $link;
    }

	/**
	 * Returns the data about the link pointing to an existing or potential translation, when viewed by a user
	 * not allowed to edit it.
	 *
	 * @param $exists bool true if the translation exists already
	 * @param $status int status of the translation
	 * @param $lang string language of the translation
	 *
	 * @return array
	 */
	private function generate_wrong_translator_data( $exists, $status, $lang ) {
		$lang_display_name = $this->active_langs[ $lang ][ 'display_name' ];

		if ( $status == ICL_TM_WAITING_FOR_TRANSLATOR ) {
			if ( $exists ) {
				$icon = 'edit_translation_disabled.png';
				$text = sprintf( __( "You can't edit this translation because you're not a translator to %s",
				                     'sitepress' ),
				                 $lang_display_name );
			} else {
				$icon = 'add_translation_disabled.png';
				$text = sprintf( __( "You can't add this translation because you're not a translator to %s",
				                     'sitepress' ),
				                 $lang_display_name );
			}
		} else {
			$icon = 'edit_translation_disabled.png';
			$text = sprintf( __( "You can't edit this translation, because the translation to %s is maintained by a different translator",
			                     'sitepress' ),
			                 $lang_display_name );
		}

		$link = '###';

		return array( $icon, $text, $link );
	}

	/**
	 * Returns the data for the anchor pointing towards those elements that can currently not be edited,
	 * for being in the translation basket.
	 *
	 * @return array
	 */
	private function generate_in_basket_data() {

		$icon = 'edit_translation_disabled.png';
		$text = __( 'Cannot edit this item, because it is currently in the translation basket.',
		            'sitepress' );
		$link = '###';

		return array( $icon, $text, $link );
	}

	/**
	 * @param $post_id int
	 * @param bool $update true if the translation in questions is in need of an update,
	 *                       false otherwise.
	 *
	 * @return array
	 */
	private function generate_edit_allowed_data( $post_id, $update = false ) {

		$lang_code = $this->get_attr( $post_id, 'code' );
		$icon      = 'edit_translation.png';

		$post_type = $this->get_attr( $post_id, 'post_type' );
		$original  = $this->get_attr( $post_id, 'original' ) == $post_id;
		if ( $original ) {
			$text           = __( 'Edit this document', 'sitepress' );
			$language_param = '';
		} else {
			if ( $update ) {
				$icon = 'needs-update.png';
				$text = sprintf( __( 'Update %s translation', 'sitepress' ),
				                 $this->active_langs[ $lang_code ] [ 'display_name' ] );
			} else {
				$icon = 'edit_translation.png';
				$text = sprintf( __( 'Edit the %s translation', 'sitepress' ),
				                 $this->active_langs[ $lang_code ][ 'display_name' ] );
			}

			$language_param = '&lang=' . $lang_code;
		}
        if ( $this->use_editor && defined ( 'WPML_TM_FOLDER' ) ) {
            $job_id = $this->get_attr ( $post_id, 'job_id' );
            $link = $this->tm_editor_link ( $job_id );
        } else {
            $identifier = '?post_type=' . $post_type . '&action=edit&post=' . $post_id . $language_param;
            $link = 'post.php' . $identifier;
        }
		return array( $icon, $text, $link );
	}

	/**
	 * Generates the data for displaying a link element pointing towards a translation, that the current user can create.
	 *
	 * @param $trid int
	 * @param $language string
	 * @param $source_language string
	 * @param $post_type string
	 *
	 * @return array
	 */
	private function generate_add_data( $trid, $language, $source_language, $post_type ) {

		$icon = 'add_translation.png';
		$text = sprintf( __( 'Add translation to %s', 'sitepress' ),
		                 $this->active_langs[ $language ][ 'display_name' ] );
		if ( $this->use_editor && ( $this->langs_compatible( $language, $source_language )
		                            || current_user_can( 'manage_options' ) )
		) {
			$link = 'admin.php?page='
			        . WPML_TM_FOLDER . '/menu/translations-queue.php&trid=' . $trid
			        . '&language_code=' . $language . '&source_language_code=' . $source_language;
		} else {
			$link_page = 'post-new.php';
			$identifier = '?post_type=' . $post_type . '&trid=' . $trid . '&lang=' . $language . '&source_lang=' . $source_language;
			$link = $link_page . $identifier;
		}

		return array( $icon, $text, $link );
	}

	/**
	 * Generates the data for the link element pointing to the translation queue, in case a user can legally create
	 * a translation there, by taking a proposed translation job.
	 *
	 * @param $lang string
	 * @param $job_id int
	 *
	 * @return array
	 */
	private function generate_add_tm_data( $lang, $job_id ) {

		$icon = 'add_translation.png';
		$text = sprintf( __( 'Take this job and start translating to %s from the Translation Management Translations menu.',
		                     'sitepress' ),
		                 $this->active_langs[ $lang ][ 'display_name' ] );
		$link = 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . $job_id;

		return array( $icon, $text, $link );
	}

}
