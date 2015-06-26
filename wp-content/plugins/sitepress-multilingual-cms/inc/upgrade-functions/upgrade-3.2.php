<?php
/**
 * @package wpml-core
 */

global $wpdb;

global $sitepress_settings;

if ( ! isset( $sitepress_settings ) ) {
	$sitepress_settings = get_option( 'icl_sitepress_settings' );
}

// change icl_translate.field_type size to 160
$sql = "ALTER TABLE {$wpdb->prefix}icl_translate MODIFY COLUMN field_type VARCHAR( 160 ) NOT NULL";
$wpdb->query( $sql );

// Add 'batch_id' column to icl_translation_status
$sql             = $wpdb->prepare( "SELECT count(*) FROM information_schema.COLUMNS
     WHERE COLUMN_NAME = 'batch_id'
     and TABLE_NAME = '{$wpdb->prefix}icl_translation_status'AND TABLE_SCHEMA = %s",
                                   DB_NAME );
$batch_id_exists = $wpdb->get_var( $sql );
if ( ! $batch_id_exists || ! (int) $batch_id_exists ) {
	$sql = "ALTER TABLE `{$wpdb->prefix}icl_translation_status` ADD batch_id int DEFAULT 0 NOT NULL;";
	$wpdb->query( $sql );
}

// Add 'batch_id' column to icl_string_translations
$sql             = $wpdb->prepare( "SELECT count(*) FROM information_schema.COLUMNS
     WHERE COLUMN_NAME = 'batch_id'
     and TABLE_NAME = '{$wpdb->prefix}icl_string_translations' AND TABLE_SCHEMA = %s",
                                   DB_NAME );
$batch_id_exists = $wpdb->get_var( $sql );
if ( ! $batch_id_exists || ! (int) $batch_id_exists ) {
	$sql = "ALTER TABLE `{$wpdb->prefix}icl_string_translations` ADD batch_id int DEFAULT -1 NOT NULL;";
	$wpdb->query( $sql );
	require '3.2/wpml-upgrade-string-statuses.php';
	update_string_statuses();
	fix_icl_string_status();
}

// Add 'translation_service' column to icl_string_translations
$sql             = $wpdb->prepare( "SELECT count(*) FROM information_schema.COLUMNS
     WHERE COLUMN_NAME = 'translation_service'
     and TABLE_NAME = '{$wpdb->prefix}icl_string_translations' AND TABLE_SCHEMA = %s",
                                   DB_NAME );
$batch_id_exists = $wpdb->get_var( $sql );
if ( ! $batch_id_exists || ! (int) $batch_id_exists ) {
	$sql = "ALTER TABLE `{$wpdb->prefix}icl_string_translations` ADD translation_service varchar(16) DEFAULT '' NOT NULL;";
	$wpdb->query( $sql );
}

// Add 'icl_translation_batches' table
$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}icl_translation_batches (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `batch_name` text NOT NULL,
				  `tp_id` int NULL,
				  `ts_url` text NULL,
				  `last_update` DATETIME NULL,
				  PRIMARY KEY (`id`)
				);";
$wpdb->query( $sql );


$res                 = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}icl_strings" );
$icl_strings_columns = array();
foreach ( $res as $row ) {
	$icl_strings_columns[ ] = $row->Field;
}
if ( ! in_array( 'string_package_id', $icl_strings_columns ) ) {
	$wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_strings
        ADD `string_package_id` BIGINT unsigned NULL AFTER value,
        ADD `type` VARCHAR(40) NOT NULL DEFAULT 'LINE' AFTER string_package_id,
        ADD `title` VARCHAR(160) NULL AFTER type,
        ADD INDEX (`string_package_id`)
    " );
}