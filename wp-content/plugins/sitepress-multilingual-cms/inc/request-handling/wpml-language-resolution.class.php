<?php

class WPML_Language_Resolution {

    private $active_language_codes = array();
    private $current_request_lang  = null;
    private $default_lang  = null;
    private $hidden_lang_codes  = null;

    public function __construct( $active_language_codes, $default_lang ) {
        add_filter ( 'icl_set_current_language', array( $this, 'current_lang_filter' ), 10, 2 );
        add_filter ( 'icl_current_language', array( $this, 'current_lang_filter' ), 10, 2 );
        $this->active_language_codes = array_fill_keys ( $active_language_codes, 1 );
        $this->default_lang          = $default_lang;
        $this->hidden_lang_codes     = array_fill_keys ( icl_get_setting ( 'hidden_languages', array() ), 1 );
    }

    /**
     * Returns the language_code of the http referrer's location from which a request originated.
     * Used to correctly determine the language code on ajax link lists for the post edit screen or
     * the flat taxonomy auto-suggest.
     * @param bool $return_default
     * @return String
     */
    public function get_referrer_language_code( $return_default = false ) {
        $language_code = null;
        if ( !empty( $_SERVER[ 'HTTP_REFERER' ] ) ) {
            $query_string = parse_url ( $_SERVER[ 'HTTP_REFERER' ], PHP_URL_QUERY );
            $query        = array();
            parse_str ( strval ( $query_string ), $query );
            $language_code = isset( $query[ 'lang' ] ) ? $query[ 'lang' ] : $language_code;
        }

        return (bool) $language_code === false and $return_default === true ? $this->default_lang : $language_code;
    }

    private function use_referrer_language() {
        $get_action = filter_input ( INPUT_GET, 'action' );
        $post_action = filter_input ( INPUT_POST, 'action' );

        return $get_action === 'ajax-tag-search'
               || $post_action === 'get-tagcloud'
               || $post_action === 'wp-link-ajax';
    }

    public function current_lang_filter( $lang_code_before ) {

        if ( $this->current_request_lang === $lang_code_before ) {
            return $lang_code_before;
        }

        $lang = null;
        $lang = $lang !== null ? $lang : $this->filter_preview_language_code ();
        $lang = $lang === null && $this->use_referrer_language()
            ? $this->get_referrer_language_code () : $lang;
        $lang = $lang !== null ? $lang : $lang_code_before;
        $lang = $this->filter_for_legal_langs( $lang );

        $this->current_request_lang = $lang;

        return $lang;
    }

    private function filter_preview_language_code() {
        $preview_id   = filter_input ( INPUT_GET, 'preview_id' );
        $preview_flag = filter_input ( INPUT_GET, 'preview' );
        $preview_id   = $preview_id ? $preview_id : filter_input ( INPUT_GET, 'p' );
        $preview_id   = $preview_id ? $preview_id : filter_input ( INPUT_GET, 'page_id' );
        $lang         = null;

        if ( $preview_id || $preview_flag || $preview_id ) {
            global $wpml_post_translations;
            $lang = $wpml_post_translations->get_element_lang_code ( $preview_id );
        }

        return $lang;
    }

    public function get_active_language_codes() {

        return array_keys ( $this->active_language_codes );
    }

    public function is_language_hidden( $lang_code ) {

        return isset( $this->hidden_lang_codes[ $lang_code ] );
    }

    public function is_language_active( $lang_code ) {

        return isset( $this->active_language_codes[ $lang_code ] );
    }

    /**
     *
     * Sets the language of frontend requests to false, if they are not for
     * a hidden or active language code. The handling of permissions in case of
     * hidden languages is done in \SitePress::init.
     *
     * @param string $lang
     * @return bool|string
     */
    private function filter_for_legal_langs( $lang ) {

        if ( $lang === 'all' && is_admin () ) {
            return 'all';
        }

        if ( !isset( $lang, $this->hidden_lang_codes ) && !isset( $lang, $this->active_language_codes ) ) {
            $lang = $this->default_lang;
        }

        return $lang;
    }
}