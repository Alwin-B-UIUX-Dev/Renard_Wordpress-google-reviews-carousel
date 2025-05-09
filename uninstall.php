<?php
if ( !defined('WP_UNINSTALL_PLUGIN') ) exit;
delete_option('grc_options');
// On supprime aussi les transients (voir plus haut).
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_grc_reviews_cache_%'");
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_grc_reviews_cache_%'");
