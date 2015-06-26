<?php
define('ICL_DISABLE_CACHE', false);

if ( !defined( 'ICL_CACHE_TRANSLATIONS' ) ) {
	/**
	 * Constant used for narrowing the cache scope to translations
	 *
	 * @name ICL_TRANSIENT_EXPIRATION
	 * @param string='translations'
	 */
	define( 'ICL_CACHE_TRANSLATIONS', 'translations' );
}
$icl_cache_scopes[ ] = ICL_CACHE_TRANSLATIONS;

if ( !defined( 'ICL_CACHE_LOCALE' ) ) {
	/**
	 * Constant used for narrowing the cache scope to locales
	 *
	 * @name ICL_CACHE_LOCALE
	 * @param string='locale'
	 */
	define( 'ICL_CACHE_LOCALE', 'locale' );
}
$icl_cache_scopes[ ] = ICL_CACHE_LOCALE;

if ( !defined( 'ICL_CACHE_FLAGS' ) ) {
	/**
	 * Constant used for narrowing the cache scope to flags
	 *
	 * @name ICL_CACHE_FLAGS
	 * @param string='flags'
	 */
	define( 'ICL_CACHE_FLAGS', 'flags' );
}
$icl_cache_scopes[ ] = ICL_CACHE_FLAGS;

if ( !defined( 'ICL_CACHE_LANGUAGE_NAME' ) ) {
	/**
	 * Constant used for narrowing the cache scope to language names
	 *
	 * @name ICL_CACHE_LANGUAGE_NAME
	 * @param string='language_name'
	 */
	define( 'ICL_CACHE_LANGUAGE_NAME', 'language_name' );
}
$icl_cache_scopes[ ] = ICL_CACHE_LANGUAGE_NAME;

if ( !defined( 'ICL_CACHE_TERM_TAXONOMY' ) ) {
	/**
	 * Constant used for narrowing the cache scope to term taxonomies
	 *
	 * @name ICL_CACHE_TERM_TAXONOMY
	 * @param string='term_taxonomy'
	 */
	define( 'ICL_CACHE_TERM_TAXONOMY', 'term_taxonomy' );
}
$icl_cache_scopes[ ] = ICL_CACHE_TERM_TAXONOMY;

if ( !defined( 'ICL_CACHE_COMMENT_COUNT' ) ) {
	/**
	 * Constant used for narrowing the cache scope to comments count
	 *
	 * @name ICL_CACHE_COMMENT_COUNT
	 * @param string='comment_count'
	 */
	define( 'ICL_CACHE_COMMENT_COUNT', 'comment_count' );
}
$icl_cache_scopes[ ] = ICL_CACHE_COMMENT_COUNT;

function icl_cache_get($key){
    $icl_cache = get_option('_icl_cache');
    if(isset($icl_cache[$key])){
        return $icl_cache[$key];
    }else{
        return false;
    }
}  

function icl_cache_set($key, $value=null){
    
    global $switched;
    if(!empty($switched)) return; 
    
    $icl_cache = get_option('_icl_cache');
    if(false === $icl_cache){
        delete_option('_icl_cache');
    }
        
	if(!isset($icl_cache[$key]) || $icl_cache[$key] != $value){		
		if(!is_null($value)){
			$icl_cache[$key] = $value;    
		}else{
			if(isset($icl_cache[$key])){
				unset($icl_cache[$key]);
			}        
		}
		
		
		update_option('_icl_cache', $icl_cache);
	}
}

function icl_cache_clear($key = false, $key_as_prefix = false){
    if($key === false){
        delete_option('_icl_cache');    
    }else{
        $icl_cache = get_option('_icl_cache');

		if(is_array($icl_cache)) {
			if(isset($icl_cache[$key])){
				unset($icl_cache[$key]);
			}

			if($key_as_prefix) {
				$cache_keys = array_keys($icl_cache);
				foreach($cache_keys as $cache_key) {
					if(strpos($cache_key, $key)===0) {
						unset($icl_cache[$key]);
					}
				}
			}

			// special cache of 'per language' - clear different statuses
			if(false !== strpos($key, '_per_language')){
				foreach($icl_cache as $k => $v){
					if(false !== strpos($k, $key . '#')){
						unset($icl_cache[$k]);
					}
				}
			}

			update_option('_icl_cache', $icl_cache);
		}
    }
}

class icl_cache{
   
    private $data;
    
    function __construct($name = "", $cache_to_option = false){
        $this->data = array();
        $this->name = $name;
        $this->cache_to_option = $cache_to_option;
        
        if ($cache_to_option) {
            $this->data = icl_cache_get($name.'_cache_class');
            if ($this->data == false){
                $this->data = array();
            }
        }
    }
    
    function get($key) {
        if(ICL_DISABLE_CACHE){
            return null;
        }
        return isset($this->data[$key]) ? $this->data[$key] : false;
    }
    
    function has_key($key){
        if(ICL_DISABLE_CACHE){
            return false;
        }
        return array_key_exists($key, (array)$this->data);
    }
    
    function set($key, $value) {
        if(ICL_DISABLE_CACHE){
            return;
        }
        $this->data[$key] = $value;
        if ($this->cache_to_option) {
            icl_cache_set($this->name.'_cache_class', $this->data);
        }
    }
    
    function clear() {
        $this->data = array();
        if ($this->cache_to_option) {
            icl_cache_clear($this->name.'_cache_class');
        }
    }
}

function w3tc_translate_cache_key_filter( $key ) {
	global $sitepress;

	return $sitepress->get_current_language() . $key;
}
