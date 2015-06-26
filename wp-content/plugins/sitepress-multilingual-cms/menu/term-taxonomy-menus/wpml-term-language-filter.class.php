<?php
require ICL_PLUGIN_PATH . '/menu/wpml-language-filter-bar.class.php';

class WPML_Term_Language_Filter extends WPML_Language_Filter_Bar{

	function terms_language_filter() {
		$this->init();
		$requested_data = $this->sanitize_request();
		$taxonomy        = $requested_data['req_tax'] !== '' ? $requested_data['req_tax'] : 'post_tag';
		$languages       = $this->get_counts ( $taxonomy );
		$languages_links = array();
		foreach ( $this->active_languages as $code => $lang ) {
			$count = isset( $languages[ $code ] ) ? $languages[ $code ] : 0;
			if ( $code === $this->current_language ) {
				$px = '<strong>';
				$sx = ' (' . $count . ')<\/strong>';
			} else {
				$px        = '<a href="?taxonomy=' . $taxonomy . '&amp;lang=' . $code;
				$post_type = $requested_data['req_ptype'];
				$px .= $post_type !== '' ? '&amp;post_type=' . $post_type : '';
				$px .= '">';
				$sx = '<\/a> (' . $count . ')';
			}
			$languages_links[ ] = $px . $lang[ 'display_name' ] . $sx;
		}
		$all_languages_links = join ( ' | ', $languages_links );
		?>
		<script type="text/javascript">
			jQuery('table.widefat').before('<span id="icl_subsubsub"><?php echo $all_languages_links ?><\/span>');
			<?php if($this->current_language !== $this->default_language): ?>
			jQuery('.search-form').append('<input type="hidden" name="lang" value="<?php echo  $this->current_language ?>" />');
			<?php endif; ?>
		</script>
		<?php
	}

	protected function get_count_data( $taxonomy ) {
		global $wpdb;

		$res_query = "	SELECT language_code, COUNT(tm.term_id) AS c
						FROM {$wpdb->prefix}icl_translations t
						JOIN {$wpdb->term_taxonomy} tt
							ON t.element_id = tt.term_taxonomy_id
							AND t.element_type = CONCAT('tax_', tt.taxonomy)
						JOIN {$wpdb->terms} tm
							ON tt.term_id = tm.term_id
						WHERE tt.taxonomy = %s
						" . $this->extra_conditions_snippet();

		return $wpdb->get_results ( $wpdb->prepare ( $res_query, $taxonomy ) );
	}
}