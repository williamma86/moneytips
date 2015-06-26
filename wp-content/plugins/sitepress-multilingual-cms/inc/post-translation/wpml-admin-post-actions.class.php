<?php
require 'wpml-post-synchronization.class.php';

class WPML_Admin_Post_Actions extends  WPML_Post_Translation{

	private $settings;
	private $post_translation_sync;

	public function __construct(&$settings){
		parent::__construct();
		$this->settings = $settings;
	}

	public function get_sync_helper(){
		$this->post_translation_sync = $this->post_translation_sync
			? $this->post_translation_sync : new WPML_Post_Synchronization($this->settings, $this);

		return $this->post_translation_sync;
	}

	public function init() {
		add_action( 'save_post', array( $this, 'save_post_actions' ), 100, 2 );
		add_action( 'deleted_post', array( $this, 'deleted_post_actions' ) );
		add_action( 'wp_trash_post', array( $this, 'trashed_post_actions' ) );
		add_action( 'untrashed_post', array( $this, 'untrashed_post_actions' ) );
	}

	/**
	 * This function holds all actions to be run after deleting a post.
	 * 1. Delete the posts entry in icl_translations.
	 * 2. Set one of the posts translations or delete all translations of the post, depending on sitepress settings.
	 *
	 * @param Integer $post_id
	 * @param bool $keep_db_entries Sets whether icl_translations entries are to be deleted or kept, when hooking this to
	 * post trashing we want them to be kept.
	 */
	public function deleted_post_actions( $post_id, $keep_db_entries = false ) {
		$translation_sync = $this->get_sync_helper ();

		$translation_sync->deleted_post_actions ( $post_id, $keep_db_entries );
	}

	function untrashed_post_actions( $post_id ) {
		$translation_sync = $this->get_sync_helper ();

		$translation_sync->untrashed_post_actions ( $post_id );
	}

	private function has_save_post_action( $post ) {

		return ! (!$this->is_translated_type ( $post->post_type )
		       || ( isset( $post->post_status ) && $post->post_status === "auto-draft" )
		       || isset( $_POST[ 'autosave' ] )
		       || isset( $_POST[ 'skip_sitepress_actions' ] )
		       || ( isset( $_POST[ 'post_ID' ] )
		            && $_POST[ 'post_ID' ] != $post->ID )
		       || ( isset( $_POST[ 'post_type' ] )
		            && $_POST[ 'post_type' ] === 'revision' )
		       || $post->post_type === 'revision'
		       || get_post_meta ( $post->ID, '_wp_trash_meta_status', true )
		       || ( isset( $_GET[ 'action' ] )
		            && $_GET[ 'action' ] === 'untrash' ));
	}

	private function is_inline_action( $post_vars ) {

		return isset( $post_vars[ 'action' ] )
		       && $post_vars[ 'action' ] == 'inline-save'
		       || isset( $_GET[ 'bulk_edit' ] )
		       || isset( $_GET[ 'doing_wp_cron' ] )
		       || ( isset( $_GET[ 'action' ] )
		            && $_GET[ 'action' ] == 'untrash' );
	}

	private function get_save_post_source_lang( $trid, $language_code, $default_language ) {
		$source_language = filter_input ( INPUT_GET, 'source_lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$source_language = $source_language ? $source_language : SitePress::get_source_language_from_referer ();
		$source_language = $source_language ? $source_language : SitePress::get_source_language_by_trid ( $trid );
		$source_language = $source_language === 'all' ? $default_language : $source_language;
		$source_language = $source_language !== $language_code ? $source_language : null;

		return $source_language;
	}

	private function get_trid_from_referer() {
		if ( isset( $_SERVER[ 'HTTP_REFERER' ] ) ) {
			$query = parse_url ( $_SERVER[ 'HTTP_REFERER' ], PHP_URL_QUERY );
			parse_str ( $query, $vars );
		}

		return isset( $var[ 'trid' ] ) ? filter_var($var[ 'trid' ], FILTER_SANITIZE_NUMBER_INT ) : false;
	}

	/**
	 * @param Integer $post_id
	 * @param SitePress $sitepress
	 * @return bool|mixed|null|string|void
	 */
	private function get_save_post_lang($post_id, $sitepress){
		$language_code = isset( $post_vars[ 'icl_post_language' ] ) ? $post_vars[ 'icl_post_language' ] : null;
		$language_code = $language_code ? $language_code : filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
		$language_code = $language_code ? $language_code : $this->get_element_lang_code( $post_id );
		$language_code = $language_code ? $language_code : $sitepress->get_current_language();
		$language_code = $sitepress->is_active_language ( $language_code ) ? $language_code : $sitepress->get_default_language();
		$language_code = apply_filters( 'wpml_save_post_lang', $language_code );

		return $language_code;
	}

	/**
	 * @param Integer $post_id
	 * @param String $post_status
	 * @return bool|int
	 */
	private function get_save_post_trid( $post_id, $post_status ) {
		$trid = $this->get_element_trid ( $post_id );
		$trid = !$trid && isset( $post_vars[ 'icl_trid' ] ) ? $post_vars[ 'icl_trid' ] : $trid;
		$trid = $trid ? $trid : filter_input ( INPUT_GET, 'trid' );
		$trid = $trid ? $trid : $this->get_trid_from_referer ();
		$trid = apply_filters ( 'wpml_save_post_trid_value', $trid, $post_status );

		return $trid;
	}

	public function save_post_actions( $pidd, $post ) {
		global $sitepress, $wpdb;

		wp_defer_term_counting( true );
		$post = isset( $post ) ? $post : get_post( $pidd );
		// exceptions
		if ( !$this->has_save_post_action ( $post ) ) {
			wp_defer_term_counting ( false );
			return;
		}

		$default_language = $sitepress->get_default_language();

		// allow post arguments to be passed via wp_insert_post directly and not be expected on $_POST exclusively
		$post_vars = (array) $_POST;
		foreach ( (array) $post as $k => $v ) {
			$post_vars[ $k ] = $v;
		}

		$post_vars[ 'post_type' ] = isset( $post_vars[ 'post_type' ]) ? $post_vars[ 'post_type'] : $post->post_type;
		$post_id = $pidd;
		if ( isset( $post_vars[ 'action' ] ) && $post_vars[ 'action' ] === 'post-quickpress-publish' ) {
			$language_code = $default_language;
			$quick_press = true;
		} else {
			$post_id = isset( $post_vars[ 'post_ID' ] ) ? $post_vars[ 'post_ID' ] : $pidd; //latter case for XML-RPC publishing
			$language_code = $this->get_save_post_lang($post_id, $sitepress);
		}

		if ( $this->is_inline_action ( $post_vars ) && !($language_code = $this->get_element_lang_code ( $post_id )) ) {
			return;
		}

		if ( isset( $post_vars[ 'icl_translation_of' ] ) && is_numeric ( $post_vars[ 'icl_translation_of' ] ) ) {
			$translation_of_data_prepared = $wpdb->prepare (
				"SELECT trid, language_code
				 FROM {$wpdb->prefix}icl_translations
				 WHERE element_id=%d
				  AND element_type=%s",
				$post_vars[ 'icl_translation_of' ],
				'post_' . $post->post_type
			);
			list( $trid, $source_language ) = $wpdb->get_row ( $translation_of_data_prepared, 'ARRAY_N' );
		}
		$trid = isset($trid) && $trid ? $trid : $this->get_save_post_trid( $post_id, $post->post_status );
		// after getting the right trid set the source language from it by referring to the root translation
		// of this trid, in case no proper source language has been set yet
		$source_language = isset( $source_language )
			? $source_language : $this->get_save_post_source_lang ( $trid, $language_code, $default_language );

		$this->maybe_set_elid( $trid, $post->post_type, $language_code, $post_id, $source_language );

		$translation_sync = $this->get_sync_helper();
		if ( !empty( $quick_press ) ) {
			$translation_sync->sync_sticky_flag ( $trid, $post_vars );
		}

		$original_id = $this->get_original_element ( $post_id );
		if ( $original_id ) {
			$translation_sync->sync_with_translations ( $original_id );
		}
		if ( isset( $post_vars[ 'icl_tn_note' ] ) ) {
			update_post_meta( $post_id, '_icl_translator_note', $post_vars[ 'icl_tn_note' ] );
		}

		require_once ICL_PLUGIN_PATH . '/inc/cache.php';
		icl_cache_clear( $post_vars[ 'post_type' ] . 's_per_language', true );
		wp_defer_term_counting( false );
	}

	/** Before setting the language of the post to be saved, check if a translation in this language already exists
	 * This check is necessary, so that synchronization actions like thrashing or un-trashing of posts, do not lead to
	 * database corruption, due to erroneously changing a posts language into a state,
	 * where it collides with an existing translation. While the UI prevents this sort of action for the most part,
	 * this is not necessarily the case for other plugins like TM.
	 * The logic here first of all checks if an existing translation id is present in the desired language_code.
	 * If so but this translation is actually not the currently to be saved post,
	 * then this post will be saved to its current language. If the translation already exists,
	 * the existing translation id will be used. In all other cases a new entry in icl_translations will be created.
	 * @param Integer $trid
	 * @param String $post_type
	 * @param String $language_code
	 * @param Integer $post_id
	 * @param String $source_language
	 */
	private function maybe_set_elid( $trid, $post_type, $language_code, $post_id, $source_language ) {
		global $sitepress;

		$element_type = 'post_' . $post_type;
		$sitepress->set_element_language_details (
			$post_id,
			$element_type,
			$trid,
			$language_code,
			$source_language
		);

	}

	public function delete_post_translation_entry( $post_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( " DELETE FROM {$wpdb->prefix}icl_translations
										WHERE element_id = %d
											AND element_type LIKE 'post%%'
										LIMIT 1",
		                       $post_id );
		$res = $wpdb->query( $sql );

		return $res;
	}

	public function trashed_post_actions( $post_id ) {
		$this->deleted_post_actions( $post_id, true );
	}
}