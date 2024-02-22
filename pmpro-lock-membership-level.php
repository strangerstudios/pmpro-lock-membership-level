<?php
/*
Plugin Name: Paid Memberships Pro - Lock Membership Level
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-lock-membership-level/
Description: Lock membership level changes for specific users or by level.
Version: 0.4
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-lock-membership-level
Domain Path: /languages
*/

require_once( plugin_dir_path( __FILE__ ) . 'includes/lock-functions.php' );   // Functions to lock/unlock membership levels for a user.
require_once( plugin_dir_path( __FILE__ ) . 'includes/frontend.php' );       // Enforce locks on the frontend.
require_once( plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php' ); // Admin page settings and level settings.
require_once( plugin_dir_path( __FILE__ ) . 'includes/memberslist.php' );    // Showing locked members in the members list.
require_once( plugin_dir_path( __FILE__ ) . 'includes/deprecated.php' );     // Include legacy functions for PMPro v2.x.

/**
 * pmprolml_load_plugin_text_domain
 *
 * @since 0.4
 */
function pmprolml_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-lock-membership-level', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'pmprolml_load_plugin_text_domain' );

/*
 *	Function to add links to the plugin row meta
 */
function pmprolml_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-lock-membership-level.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/pmpro-lock-membership-level/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-lock-membership-level' ) ) . '">' . esc_html__( 'Docs', 'pmpro-lock-membership-level' ) . '</a>',
			'<a href="' . esc_url('https://www.paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-lock-membership-level' ) ) . '">' . esc_html__( 'Support', 'pmpro-lock-membership-level' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprolml_plugin_row_meta', 10, 2);
