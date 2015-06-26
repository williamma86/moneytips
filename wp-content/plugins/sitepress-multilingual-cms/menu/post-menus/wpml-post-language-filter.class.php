<?php
require ICL_PLUGIN_PATH . '/menu/wpml-language-filter-bar.class.php';

class WPML_Post_Language_Filter extends WPML_Language_Filter_Bar {

	private $post_status;
	private $post_type;

	protected function sanitize_request() {

		$request_data    = parent::sanitize_request ();
		$this->post_type = $request_data[ 'req_ptype' ] ? $request_data[ 'req_ptype' ] : 'post';
		$post_statuses   = array_keys ( get_post_stati () );
		$post_status     = get_query_var ( 'post_status' );
		if ( is_string ( $post_status ) ) {
			$post_status = $post_status ? array( $post_status ) : array();
		}
		$illegal_status = array_diff ( $post_status, $post_statuses );

		$this->post_status = array_diff ( $post_status, $illegal_status );
	}

	function post_language_filter() {
		global $sitepress;

		$this->sanitize_request();
		$this->init();
		$type = $this->post_type;

		if ( !$sitepress->is_translated_post_type ( $type ) ) {
			return;
		}

		$as = array();
		$languages = $this->get_counts($type);
		$post_status = $this->post_status;
		foreach ( $this->active_languages as $code => $lang ) {
			$count = isset( $languages[ $code ] ) ? $languages[ $code ] : 0;
			if ( $code === $this->current_language ) {
				$px = '<strong>';
				$sx = ' <span class="count">(' . $count . ')<\/span><\/strong>';
			} elseif ( !isset( $languages[ $code ] ) ) {
				$px = '<span>';
				$sx = '<\/span>';
			} else {
				if ( $post_status ) {
					$px = '<a href="?post_type=' . $type . '&post_status=' . join (
							',',
							$post_status
						) . '&lang=' . $count . '">';
				} else {
					$px = '<a href="?post_type=' . $type . '&lang=' . $code . '">';
				}
				$sx = '<\/a> <span class="count">(' . $count . ')<\/span>';
			}
			$as[ ] = $px . $lang[ 'display_name' ] . $sx;
		}
		$allas = join ( ' | ', $as );
		if ( !$sitepress->get_setting( 'hide_how_to_translate') && $type === 'page' ) {
			$prot_link = '<span id="icl_how_to_translate_link" class="button" style="padding-right:3px;" ><img align="baseline" src="' . ICL_PLUGIN_URL . '/res/img/icon16.png" width="16" height="16" style="margin-bottom:-4px" /> <a href="https://wpml.org/?page_id=3416">' . __ (
					'How to translate',
					'sitepress'
				) . '</a><a href="#" title="' . esc_attr__ (
				             'hide this',
				             'sitepress'
			             ) . '" onclick=" if(confirm(\\\'' . __ (
				             'Are you sure you want to remove this button?',
				             'sitepress'
			             ) . '\\\')) jQuery.ajax({url:icl_ajx_url,type:\\\'POST\\\',data:{icl_ajx_action:\\\'update_option\\\', option:\\\'hide_how_to_translate\\\',value:1,_icl_nonce:\\\'' . wp_create_nonce (
				             'update_option_nonce'
			             ) . '\\\'},success:function(){jQuery(\\\'#icl_how_to_translate_link\\\').fadeOut()}});return false;" style="outline:none;"><img src="' . ICL_PLUGIN_URL . '/res/img/close2.png" width="10" height="10" style="border:none" alt="' . esc_attr__ (
				             'hide',
				             'sitepress'
			             ) . '" /><\/a>' . '<\/span>';
		} else {
			$prot_link = '';
		}
		?>
		<script type="text/javascript">
			jQuery(".subsubsub").append('<br /><span id="icl_subsubsub"><?php echo $allas ?></span><br /><?php echo $prot_link ?>');
		</script>
		<?php

	}

	protected function extra_conditions_snippet(){


		$extra_conditions = "";
		if ( !empty( $this->post_status ) ) {
			$status_snippet  = " AND post_status IN (" .wpml_prepare_in($this->post_status) . ") ";
			$extra_conditions .= apply_filters( '_icl_posts_language_count_status', $status_snippet );
		}

		$extra_conditions .= $this->post_status != array( 'trash' ) ? " AND post_status <> 'trash'" : '';
		$extra_conditions .= " AND post_status <> 'auto-draft' ";
		$extra_conditions .= parent::extra_conditions_snippet();

		return $extra_conditions;
	}

	protected function get_count_data( $type ) {
		global $wpdb;

		$extra_conditions = $this->extra_conditions_snippet();

		return $wpdb->get_results( $wpdb->prepare("
				SELECT language_code, COUNT(p.ID) AS c
				FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->posts} p
					ON t.element_id=p.ID
						AND t.element_type = CONCAT('post_', p.post_type)
				WHERE p.post_type=%s {$extra_conditions}
				", $type, 'post_' . $type ) );
	}
}