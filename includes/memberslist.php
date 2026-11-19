<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			$is_locked = pmprolml_is_level_locked_for_user( $user_id, $item['membership_id'] );
		} else {
			$is_locked = ! empty( pmprolml_get_locks_for_user( $user_id ) );
		}
		if ( $is_locked ) {
			echo '<span class="pmpro_tag pmpro_tag-has_icon pmpro_tag-alert">' . esc_html__( 'Locked', 'pmpro-lock-membership-level' ) . '</span>';
		} else {
			echo '&#8212;';
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
	// Read-only list filter parameters; nothing is processed or saved.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! empty( $_REQUEST['page'] ) && 'pmpro-memberslist' === sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) ) {
		?>
		<script>
			jQuery(document).ready(function() {
				jQuery('select[name=l]').append('<option value="locked"><?php esc_html_e( 'Locked', 'pmpro-lock-membership-level' ); ?></option>');
				<?php if ( ! empty( $_REQUEST['l'] ) && 'locked' === sanitize_text_field( wp_unslash( $_REQUEST['l'] ) ) ) { ?>
					jQuery('select[name=l]').val('locked');
				<?php } ?>
			});
		</script>
		<?php
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}
add_action('admin_footer', 'pmprolml_admin_footer_js', 99);

/**
 * Get the SQL condition that matches memberships that are currently locked.
 *
 * Used by the "Locked" option on the Members List and by the Members List export so that
 * both show the same members as the "Locked?" column. Locks that have expired or have had
 * their payment requirement met are left out.
 *
 * The query using this must alias the users table as `u` and the memberships users table as `mu`.
 *
 * @since TBD
 *
 * @return string The SQL condition, wrapped in parentheses.
 */
function pmprolml_get_locked_members_sql_condition() {
	global $wpdb;
	static $inactive_lock_ids = null;

	// Whether a lock has expired or has had enough payments can't be worked out in SQL.
	// Check the locks that can end here, once per request, and leave the ended ones out of the query by ID.
	if ( null === $inactive_lock_ids ) {
		$inactive_lock_ids = array();
		$locks = $wpdb->get_results( "SELECT umeta_id, user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = 'pmprolml_lock' AND ( meta_value NOT LIKE '%\"expiration\";i:0;%' OR ( meta_value LIKE '%\"payments_required\";%' AND meta_value NOT LIKE '%\"payments_required\";i:0;%' ) )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads lock records for many users at once; cached for the request above.
		foreach ( $locks as $lock_row ) {
			$lock = maybe_unserialize( $lock_row->meta_value );
			if ( ! is_array( $lock ) || ! isset( $lock['level_id'], $lock['expiration'] ) ) {
				continue;
			}

			$expired = (int)$lock['expiration'] !== 0 && (int)$lock['expiration'] < time();
			if ( $expired || pmprolml_is_lock_satisfied_by_payments( (int)$lock_row->user_id, $lock ) ) {
				$inactive_lock_ids[] = (int)$lock_row->umeta_id;
			}
		}
	}

	$exclude_inactive = empty( $inactive_lock_ids ) ? '' : ' AND umlml_lock.umeta_id NOT IN (' . implode( ',', $inactive_lock_ids ) . ')';

	// A lock for the membership's level or for all levels (level 0), or a lock saved before v1.0 that has not been converted yet.
	return "( EXISTS ( SELECT 1 FROM $wpdb->usermeta umlml_lock WHERE umlml_lock.user_id = u.ID AND umlml_lock.meta_key = 'pmprolml_lock' AND ( umlml_lock.meta_value LIKE '%\"level_id\";i:0;%' OR umlml_lock.meta_value LIKE CONCAT( '%\"level_id\";i:', mu.membership_id, ';%' ) )$exclude_inactive ) OR EXISTS ( SELECT 1 FROM $wpdb->usermeta umlml WHERE umlml.user_id = u.ID AND umlml.meta_key = 'pmprolml' AND umlml.meta_value = '1' ) )";
}

/*
	Filter Members List SQL to show only locked members if filtering that way
*/
function pmprolml_pmpro_members_list_sql($sql) {
	//only if the level param is passed in and set to locked
	if ( ! empty( $_REQUEST['l'] ) && 'locked' === sanitize_text_field( wp_unslash( $_REQUEST['l'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter parameter.
		//tweak SQL to only show locked members
		$sql = str_replace( "AND mu.membership_id = '0'", 'AND ' . pmprolml_get_locked_members_sql_condition(), $sql );
	}
	
	return $sql;
}
add_action('pmpro_members_list_sql', 'pmprolml_pmpro_members_list_sql');

/**
 * Only export locked members when the Members List is filtered to "Locked".
 *
 * @since TBD
 *
 * @param string $filter  The SQL that limits the export to the selected level or status.
 * @param array  $filters The filters that the export was started with.
 * @return string
 */
function pmprolml_pmpro_members_export_filter_sql( $filter, $filters ) {
	if ( ! empty( $filters['l'] ) && 'locked' === $filters['l'] ) {
		$filter .= ' AND ' . pmprolml_get_locked_members_sql_condition() . ' ';
	}

	return $filter;
}
add_filter( 'pmpro_members_export_filter_sql', 'pmprolml_pmpro_members_export_filter_sql', 10, 2 );

/**
 * Add "Locked Member" column to CSV export
 *
 * @param array $columns The columns to be exported with their callbacks.
 * @return array
 */
function pmprolml_pmpro_members_list_csv_extra_columns($columns) {
	$new_columns = array(
		"lockedmember" => "pmprolml_extra_column_lockedmember",
		"lockedmemberexpiration" => "pmprolml_extra_column_lockedmemberexpiration",
		"lockedmemberpayments" => "pmprolml_extra_column_lockedmemberpayments",
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
 * Get the active lock that applies to the membership level being exported for a user.
 *
 * @since 1.1
 *
 * @param object $user The user object for the row with some additional membership data.
 * @return array|null The lock record, or null if the level is not locked.
 */
function pmprolml_get_export_lock_for_user( $user ) {
	foreach ( pmprolml_get_locks_for_user( $user->ID ) as $lock ) {
		if ( (int)$lock['level_id'] === (int)$user->membership_id || (int)$lock['level_id'] === 0 ) {
			return $lock;
		}
	}

	return null;
}

/**
 * Callback for "Locked Member Expiration" column in CSV export
 *
 * @param object $user The user object for the row with some additional membership data.
 * @return string
 */
function pmprolml_extra_column_lockedmemberexpiration($user) {
	$lock = pmprolml_get_export_lock_for_user( $user );

	if ( empty( $lock ) || empty( $lock['expiration'] ) ) {
		return '';
	}

	// Convert the stored GMT timestamp back to the site's local time for the CSV.
	return get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $lock['expiration'] ), 'Y-m-d H:i:s' );
}

/**
 * Callback for "Locked Member Payments" column in CSV export.
 *
 * The number of successful payments required to unlock, or blank if the lock is not payment-based.
 *
 * @since 1.1
 *
 * @param object $user The user object for the row with some additional membership data.
 * @return string
 */
function pmprolml_extra_column_lockedmemberpayments($user) {
	$lock = pmprolml_get_export_lock_for_user( $user );

	if ( empty( $lock ) || empty( $lock['payments_required'] ) ) {
		return '';
	}

	return (string)(int)$lock['payments_required'];
}

/**
 * Function to add links to the plugin action links.
 *
 * @param array $links The current links array.
 * @return array
 */
function pmprolml_add_action_links($links) {	
	$cap = apply_filters( 'pmpro_add_member_cap', 'edit_users' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- PMPro core hook.
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