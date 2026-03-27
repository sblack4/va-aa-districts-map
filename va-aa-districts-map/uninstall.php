<?php
/**
 * Fired when the plugin is uninstalled (deleted via wp-admin).
 * Cleans up all plugin options from the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'vaaa_districts' );
delete_option( 'vaaa_fips_map' );
delete_option( 'vaaa_shared_counties' );
