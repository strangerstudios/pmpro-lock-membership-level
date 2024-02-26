<?php

/**
 * Add columns to Members List.
 *
 * @param  array $columns for table.
 * @return array
 */
function pmprolml_manage_memberslist_columns( $columns ) {
	$columns[ 'pmprolml' ] = esc_html__('Locked?', 'pmpro-lock-membership-level');
	return $columns;
}
add_filter( 'pmpro_manage_memberslist_columns', 'pmprolml_manage_memberslist_columns' );

/**
 * Fills the columns of the Members List.
 *
 * @param string $colname column being filled.
 * @param string $user_id to get information for.
 * @param array|null $item The membership information being shown or null.
 */
function pmprolml_manage_memberslist_custom_column( $colname, $user_id, $item = null ) {
	if ( 'pmprolml' === $colname ) {
		if ( ! empty( $item ) && ! empty( $item['membership_id'] ) ) {
			echo pmprolml_is_level_locked_for_user( $user_id, $item['membership_id'] ) ? esc_html__( 'Yes', 'pmpro-lock-membership-level' ) : esc_html__( 'No', 'pmpro-lock-membership-level' );
		} else {
			echo empty( pmprolml_get_locks_for_user( $user_id ) ) ? esc_html__( 'No', 'pmpro-lock-membership-level' ) : esc_html__( 'Yes', 'pmpro-lock-membership-level' );
		}
	}
}

/**
 * Hooks the pmprolml_manage_memberslist_custom_column() function
 * with 2 parameters if using PMPro v2.x or 3 parameters if using PMPro v3.0+.
 */
function pmprolml_hook_pmprolml_manage_memberslist_custom_column() {
	if ( class_exists( 'PMPro_Subscription' ) ) {
		add_action( 'pmpro_manage_memberslist_custom_column', 'pmprolml_manage_memberslist_custom_column', 10, 3 );
	} else {
		add_action( 'pmpro_manage_memberslist_custom_column', 'pmprolml_manage_memberslist_custom_column', 10, 2 );
	}
}
add_action( 'admin_init', 'pmprolml_hook_pmprolml_manage_memberslist_custom_column' );

/**
 *	Insert "Locked" option into Members List dropdown via JS.
 */
function pmprolml_admin_footer_js() {
	if(!empty($_REQUEST['page']) && $_REQUEST['page'] == 'pmpro-memberslist') {
		?>
		<script>
			jQuery(document).ready(function() {
				jQuery('select[name=l]').append('<option value="locked"><?php esc_html_e( 'Locked', 'pmpro-lock-membership-level' ); ?></option>');
				<?php if( !empty($_REQUEST['l']) && $_REQUEST['l'] == 'locked' ) { ?>
					jQuery('select[name=l]').val('locked');
				<?php } ?>
			});
		</script>
		<?php
	}
}
add_action('admin_footer', 'pmprolml_admin_footer_js', 99);

/*
	Filter Members List SQL to show only locked members if filtering that way
*/
function pmprolml_pmpro_members_list_sql($sql) {
	//only if the level param is passed in and set to locked
	if(!empty($_REQUEST['l']) && $_REQUEST['l'] == 'locked') {
		global $wpdb;
		
		//tweak SQL to only show locked members
		$sql = str_replace(
			"ON mu.membership_id = m.id",
			"ON mu.membership_id = m.id LEFT JOIN $wpdb->usermeta umlml ON u.ID = umlml.user_id AND umlml.meta_key='pmprolml' LEFT JOIN $wpdb->usermeta umlml_lock ON u.ID = umlml_lock.user_id AND umlml_lock.meta_key='pmprolml_lock' AND ( umlml_lock.meta_value LIKE '%\"level_id\";i:0;%' OR umlml_lock.meta_value LIKE CONCAT( '%\"level_id\";i:', m.id, ';%' ) )",
			$sql
		);
		$sql = str_replace("AND mu.membership_id = '0'", "AND ( umlml.meta_value='1' OR ( umlml_lock.meta_key <> '' AND umlml_lock.meta_key IS NOT NULL ) )", $sql);		
	}
	
	return $sql;
}
add_action('pmpro_members_list_sql', 'pmprolml_pmpro_members_list_sql');

/**
 * Add "Locked Member" column to CSV export
 *
 * @param array $columns The columns to be exported with their callbacks.
 * @return array
 */
function pmprolml_pmpro_members_list_csv_extra_columns($columns) {
	$new_columns = array(
		"lockedmember" => "pmprolml_extra_column_lockedmember",
	);
	
	$columns = array_merge($columns, $new_columns);
	
	return $columns;
}
add_filter('pmpro_members_list_csv_extra_columns', 'pmprolml_pmpro_members_list_csv_extra_columns');

/**
 * Callback for "Locked Member" column in CSV export
 *
 * @param object $user The user object for the row with some additional membership data.
 * @return string
 */
function pmprolml_extra_column_lockedmember($user) {
	return pmprolml_is_level_locked_for_user( $user->ID, $user->membership_id ) ? '1' : '';
}

/**
 * Function to add links to the plugin action links.
 *
 * @param array $links The current links array.
 * @return array
 */
function pmprolml_add_action_links($links) {	
	$cap = apply_filters('pmpro_add_member_cap', 'edit_users');	
	if( current_user_can( $cap ) ) {
		$new_links = array(
			'<a href="' . get_admin_url(NULL, 'admin.php?page=pmpro-memberslist&l=locked') . '">' . esc_html__('View Locked Members', 'pmpro-lock-membership-level') . '</a>',
		);
		return array_merge($new_links, $links);
	}
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pmprolml_add_action_links');

/**
 * Add a panel to the Edit Member dashboard page.
 *
 * @since 1.0
 *
 * @param array $panels Array of panels.
 * @return array
 */
function pmprolml_member_edit_panels( $panels ) {
	// If the class doesn't exist and the abstract class does, require the class.
	if ( ! class_exists( 'PMProlml_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		require_once( dirname( __FILE__ ) . '/../classes/pmprolml-class-member-edit-panel.php' );
	}

	// If the class exists, add a panel.
	if ( class_exists( 'PMProlml_Member_Edit_Panel' ) ) {
		$panels[] = new PMProlml_Member_Edit_Panel();
	}

	return $panels;
}
add_filter( 'pmpro_member_edit_panels', 'pmprolml_member_edit_panels' );