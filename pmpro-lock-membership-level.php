<?php
/*
Plugin Name: Paid Memberships Pro - Lock Membership Level
Plugin URI: http://www.paidmembershipspro.com/wp/lock-membership-level/
Description: Lock membership level changes for specific users.
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

/*
	Add a page and assign it under Memberships > Page settings that will redirect locked members to 
	using the shortcode [pmpro_membership_locked]. Add shortcode attribute "message" to customize the message shown.
	
	To lock a member from changing their membership level, edit the user and check the box labeled 
	"Lock Membership Level Changes".
*/

function pmprolml_extra_page_settings($pages) {
   $pages['membership_locked'] = array('title'=>__('Membership Locked', 'pmprolml'), 'content'=>'[pmpro_membership_locked]', 'hint'=>__('Include the shortcode [pmpro_membership_locked].', 'pmprolml'));
   return $pages;
}
add_action('pmpro_extra_page_settings', 'pmprolml_extra_page_settings');

/*
	Add "Lock Membership" field in the profile
*/
function pmprolml_show_extra_profile_fields($user) {
	?>
	<h3><?php _e('Lock Membership', 'pmpro');?></h3>
	<table class="form-table">
		<tr>
			<th scope="row"><?php _e('Lock Membership Level', 'pmpro');?></th>			
			<td>
				<label for="pmprolml">
					<input id="pmprolml" name="pmprolml" type="checkbox" value="1"<?php checked( get_user_meta($user->ID, 'pmprolml', true)); ?> />
					<?php _e('Lock membership level changes for this user.', 'pmprolml'); ?>
				</label>
			</td>
		</tr>	
	</table>
	<?php
}
add_action( 'show_user_profile', 'pmprolml_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'pmprolml_show_extra_profile_fields' );
 
function pmprolml_save_extra_profile_fields( $user_id ) {
	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;
 
	update_usermeta( $user_id, 'pmprolml', $_POST['pmprolml'] );
}
add_action( 'personal_options_update', 'pmprolml_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'pmprolml_save_extra_profile_fields' );

/*
	Redirect away from pages if membership is locked.
*/
function pmprolml_template_redirect() {
	global $pmpro_pages, $current_user;
	
	if(empty($pmpro_pages))
		return;
	
	//Redirect away from the membership locked page if user isn't locked.
	if(is_page($pmpro_pages['membership_locked']) && !in_array($current_user->ID, $locked_members)) {
		wp_redirect(pmpro_url('account'));
		exit;
	}

	//Redirect to the membership locked page if user is locked.
	if(
		is_page(array(
			$pmpro_pages['levels'],
			$pmpro_pages['cancel'],
			$pmpro_pages['checkout']
		)) 
		&& !empty($pmpro_pages['membership_locked'])		
	) {
		$locked = get_user_meta($current_user->ID, 'pmprolml', true);
		if(!empty($locked)) {
			wp_redirect(pmpro_url('membership_locked'));
			exit;
		}
	}
}
add_action('template_redirect', 'pmprolml_template_redirect');

function pmpro_shortcode_membership_locked($atts, $content=null, $code="") {
	global $current_user;
	
	// $atts    ::= array of attributes
	// $content ::= text within enclosing form of shortcode element
	// $code    ::= the shortcode found, when == callback name
	// examples: [pmpro_membership_locked message="You cannot do this."]
	
	extract(shortcode_atts(array(
		'message' => __('An administrator has locked changes to your membership account.', 'pmprolml'),
	), $atts));
	
	$r = '<div class="pmpro_message pmpro_error">' . $message . '</div>';
	if($current_user->membership_level->ID)
		$r .= '<p><a href="' . pmpro_url("account") . '">' . __("&larr; Return to Your Account", "pmpro") . '</a></p>';

	return $r;
}
add_shortcode("pmpro_membership_locked", "pmpro_shortcode_membership_locked");

/*
	Add Locked Member Column to Members List
*/
function pmprolml_pmpro_memberslist_extra_cols_header() {
?>
<th><?php _e('Locked?', 'pmpro');?></th>
<?php
}
add_action("pmpro_memberslist_extra_cols_header", "pmprolml_pmpro_memberslist_extra_cols_header");
//columns
function pmprolml_pmpro_memberslist_extra_cols_body($theuser) {
?>
<td>
	<?php 
		if(!empty($theuser->pmprolml))
			echo 'Yes';
		else
			echo 'No';
	?>
</td>
<?php
}
add_action("pmpro_memberslist_extra_cols_body", "pmprolml_pmpro_memberslist_extra_cols_body");

/*
	Insert "Locked" option into Members List dropdown via JS
*/
function pmprolml_admin_footer_js() {
	if(!empty($_REQUEST['page']) && $_REQUEST['page'] == 'pmpro-memberslist') {
		if(!empty($_REQUEST['l']) && $_REQUEST['l'] == 'locked')
			$checked = 'checked="checked"';
		else
			$checked = '';
		?>
		<script>
			jQuery(document).ready(function() {
				jQuery('select[name=l]').append('<option value="locked" <?php echo $checked;?>>Locked</option>');
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
		$sql = str_replace("FROM $wpdb->users u", "FROM $wpdb->users u LEFT JOIN $wpdb->usermeta umlml ON u.ID = umlml.user_id AND umlml.meta_key='pmprolml'", $sql);
		$sql = str_replace("AND mu.membership_id = 'locked'", "AND umlml.meta_value='1'", $sql);		
	}
	
	return $sql;
}
add_action('pmpro_members_list_sql', 'pmprolml_pmpro_members_list_sql');

/*
	add column to export
*/
//columns
function pmprolml_pmpro_members_list_csv_extra_columns($columns) {
	$new_columns = array(
		"lockedmember" => "pmprolml_extra_column_lockedmember",
	);
	
	$columns = array_merge($columns, $new_columns);
	
	return $columns;
}
add_filter('pmpro_members_list_csv_extra_columns', 'pmprolml_pmpro_members_list_csv_extra_columns');

//call backs
function pmprolml_extra_column_lockedmember($user) {
	if(!empty($user->metavalues->pmprolml))
	{
			return $user->metavalues->pmprolml;
	}
	else
	{
			return "";
	}
}

/*
Function to add links to the plugin action links
*/
function pmprolml_add_action_links($links) {	
	$cap = apply_filters('pmpro_add_member_cap', 'edit_users');	
	if(current_user_can($cap))
	{
		$new_links = array(
			'<a href="' . get_admin_url(NULL, 'admin.php?page=pmpro-lockedmemberslist') . '">' . __('View Locked Members', 'pmprolml') . '</a>',
		);
	}
	return array_merge($new_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pmprolml_add_action_links');

/*
Function to add links to the plugin row meta
*/
function pmprolml_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-lock-membership-level.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/pmpro-lock-membership-level/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprolml_plugin_row_meta', 10, 2);
