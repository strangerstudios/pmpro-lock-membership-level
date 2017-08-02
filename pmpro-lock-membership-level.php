<?php
/*
Plugin Name: Paid Memberships Pro - Lock Membership Level
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-lock-membership-level/
Description: Lock membership level changes for specific users or by level.
Version: .2
Author: Stranger Studios
Author URI: https://www.paidmembershipspro.com
*/

/*
	Get lock options for a membership level
*/
function pmprolml_getLevelOptions($level_id) {
	return get_option('pmprolml_level_' . intval($level_id) . '_settings', array('lock' => 0, 'expiration' => null,'expiration_number' => null, 'expiration_period' => null ) );
}

/*
	Get lock options for a user.
	Note: We save the expiration as a separate user meta field because it allows us to do SQL queries against it if needed.
*/
function pmprolml_getUserOptions($user_id = NULL) {
	global $current_user;
	if(empty($user_id))
		$user_id = $current_user->ID;
	
	if(empty($user_id))
		return false;
	
	return array('locked'=>get_user_meta($user_id, 'pmprolml', true),
				 'expiration'=>get_user_meta($user_id, 'pmprolml_expiration', true));
}


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

	wp_get_current_user();
	
	if(!empty($_REQUEST['user_id'])) 
		$user_ID = intval($_REQUEST['user_id']);

	$membership_level_capability = apply_filters("pmpro_edit_member_capability", "manage_options");

	if(!current_user_can($membership_level_capability))
		return false;
	
	//is there an end date?
	$lml_expiration = get_user_meta($user->ID, 'pmprolml_expiration', true);
		
	//some vars for the dates
	$current_day = date("j", current_time('timestamp'));			
	if(!empty($lml_expiration))
		$selected_expires_day = date("j", strtotime($lml_expiration, current_time('timestamp')));
	else
		$selected_expires_day = $current_day;
		
	$current_month = date("M", current_time('timestamp'));			
	if(!empty($lml_expiration))
		$selected_expires_month = date("m", strtotime($lml_expiration, current_time('timestamp')));
	else
		$selected_expires_month = date("m", current_time('timestamp'));
		
	$current_year = date("Y", current_time('timestamp'));									
	if(!empty($lml_expiration))
		$selected_expires_year = date("Y", strtotime($lml_expiration, current_time('timestamp')));
	else
		$selected_expires_year = (int)$current_year + 1;
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
		<tr class="lml_expiration">
			<th scope="row" valign="top"><label for="lml_expiration"><?php _e('Unlock When?', 'pmprolml');?></label></th>
			<td>
				<select id="lml_expiration" name="lml_expiration">
					<option value="" <?php selected($lml_expiration, '');?>><?php _e('Never', 'pmprolml');?></option>
					<option value="date" <?php selected(!empty($lml_expiration), true);?>><?php _e('Specific Date', 'pmprolml');?></option>
				</select>
				<span id="lml_expiration_date" <?php if(!$lml_expiration) { ?>style="display: none;"<?php } ?>>
					on
					<select id="lml_expiration_month" id="lml_expiration_month" name="lml_expiration_month">
						<?php																
							for($i = 1; $i < 13; $i++)
							{
							?>
							<option value="<?php echo $i?>" <?php if($i == $selected_expires_month) { ?>selected="selected"<?php } ?>><?php echo date("M", strtotime($i . "/1/" . $current_year, current_time("timestamp")))?></option>
							<?php
							}
						?>
					</select>
					<input id="lml_expiration_day" name="lml_expiration_day" type="text" size="2" value="<?php echo $selected_expires_day?>" />
					<input id="lml_expiration_year" name="lml_expiration_year" type="text" size="4" value="<?php echo $selected_expires_year?>" />
				</span>
			</td>
		</tr>	
		<script>
			function toggleLMLOptions() {
				if(jQuery('#pmprolml').is(':checked')) { 
					jQuery('tr.lml_expiration').show();
					if(jQuery('#lml_expiration').val() == 'date') {
						jQuery('#lml_expiration_date').show();
					} else {
						jQuery('#lml_expiration_date').hide();
					}
				} else {
					jQuery('tr.lml_expiration').hide();
					jQuery('#lml_expiration_date').hide();
				}
			}
			
			jQuery(document).ready(function(){
				//hide/show recurring fields on page load
				toggleLMLOptions();
				
				//hide/show recurring fields when pbc or recurring settings change
				jQuery('#pmprolml').change(function() { toggleLMLOptions() });			
				jQuery('#lml_expiration').change(function() { toggleLMLOptions() });
			});
		</script>
	</table>
	<?php
}
add_action( 'show_user_profile', 'pmprolml_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'pmprolml_show_extra_profile_fields' );
 
function pmprolml_save_extra_profile_fields( $user_id ) {
	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;
 
	//figure out expiration//update the expiration date
	if(!empty($_POST['lml_expiration']))
		$lml_expiration = intval($_REQUEST['lml_expiration_year']) . "-" . str_pad(intval($_REQUEST['lml_expiration_month']), 2, "0", STR_PAD_LEFT) . "-" . str_pad(intval($_REQUEST['lml_expiration_day']), 2, "0", STR_PAD_LEFT);
	else
		$lml_expiration = '';
 
	update_user_meta( $user_id, 'pmprolml', $_POST['pmprolml'] );
	update_user_meta( $user_id, 'pmprolml_expiration', $lml_expiration);
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

	$user_lock_options = pmprolml_getUserOptions($current_user->ID);
		
	//Redirect away from the membership locked page if user isn't locked.
	if( is_user_logged_in() && is_page($pmpro_pages['membership_locked']) && (empty($user_lock_options) || empty($user_lock_options['locked']))) {
		if(pmpro_hasMembershipLevel()) {
			wp_redirect(pmpro_url('account'));
			exit;
		} else {
			wp_redirect(home_url());
			exit;
		}
	}

	//Redirect to the membership locked page if user is locked.
	$locked_pages = array(
			$pmpro_pages['levels'],
			$pmpro_pages['cancel'],
			$pmpro_pages['checkout']);
	if(is_user_logged_in() && is_page($locked_pages)) {
		if(!empty($user_lock_options) && !empty($user_lock_options['locked'])) {
			if(!empty($pmpro_pages['membership_locked'])) {
				wp_redirect(pmpro_url('membership_locked'));
				exit;
			} else {
				wp_redirect(home_url());
				exit;
			}
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
			echo __( 'Yes', 'pmprolml' );
		else
			echo __( 'No', 'pmprolml' );
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
	Add settings to the edit levels page
*/
//show the checkbox on the edit level page
function pmprolml_pmpro_membership_level_after_other_settings()
{	
	$level_id = intval($_REQUEST['edit']);
	$options = pmprolml_getLevelOptions($level_id);
?>
<h3 class="topborder"><?php _e('Lock Membership Level Settings', 'pmprolml');?></h3>
<p><?php _e('Use these settings to keep members from cancelling or changing levels after getting this level.', 'pmprolml');?></p>
<table>
<tbody class="form-table">
	<tr>
		<th scope="row" valign="top"><label for="lml_lock"><?php _e('Lock This Level?', 'pmprolml');?></label></th>
		<td>
			<input type="checkbox" id="lml_lock" name="lml_lock" <?php checked($options['lock'], 1);?>><label for="lml_lock"><?php _e('Check to lock users from cancelling or changing levels after they get this level.', 'pmprolml');?></label>
		</td>
	</tr>
	<tr class="lml_expiration">
		<th scope="row" valign="top"><label for="lml_expiration"><?php _e('Unlock When?', 'pmprolml');?></label></th>
		<td>
			<select id="lml_expiration" name="lml_expiration">
				<option value="" <?php selected($options['expiration'], '');?>><?php _e('Never', 'pmprolml');?></option>
				<option value="period" <?php selected($options['expiration'], 'period');?>><?php _e('Time Period', 'pmprolml');?></option>				
			</select>
			<input id="lml_expiration_number" name="lml_expiration_number" type="text" size="10" value="<?php echo esc_attr($options['expiration_number']);?>" />
			<select id="lml_expiration_period" name="lml_expiration_period">
			  <?php
				$cycles = array( __('Day(s)', 'pmpro') => 'Day', __('Week(s)', 'pmpro') => 'Week', __('Month(s)', 'pmpro') => 'Month', __('Year(s)', 'pmpro') => 'Year' );
				foreach ( $cycles as $name => $value ) {
				  echo "<option value='$value'";
				  if ( $options['expiration_period'] == $value ) echo " selected='selected'";
				  echo ">$name</option>";
				}
			  ?>
			</select>
		</td>
	</tr>	
	<script>
		function toggleLMLOptions() {
			if(jQuery('#lml_lock').is(':checked')) { 
				jQuery('tr.lml_expiration').show();
				if(jQuery('#lml_expiration').val() == 'period') {
					jQuery('#lml_expiration_number, #lml_expiration_period').show();
				} else {
					jQuery('#lml_expiration_number, #lml_expiration_period').hide();
				}
			} else {
				jQuery('tr.lml_expiration').hide();
				jQuery('#lml_expiration_number, #lml_expiration_period').hide();
			}
		}
		
		jQuery(document).ready(function(){
			//hide/show recurring fields on page load
			toggleLMLOptions();
			
			//hide/show recurring fields when pbc or recurring settings change
			jQuery('#lml_lock').change(function() { toggleLMLOptions() });			
			jQuery('#lml_expiration').change(function() { toggleLMLOptions() });
		});
	</script>
</tbody>
</table>
<?php
}
add_action('pmpro_membership_level_after_other_settings', 'pmprolml_pmpro_membership_level_after_other_settings');
//save pay by check settings when the level is saved/added
function pmprolml_pmpro_save_membership_level($level_id)
{
	//get values
	$lml_lock = isset( $_REQUEST['lml_lock'] ) ? true : false;
	
	if(!empty($lml_lock) && isset($_REQUEST['lml_expiration'])) {
		$lml_expiration = sanitize_text_field($_REQUEST['lml_expiration']);
		if(!in_array($lml_expiration, array('period')))
			$lml_expiration = '';
		
		$lml_expiration_number = intval($_REQUEST['lml_expiration_number']);
		
		$lml_expiration_period = sanitize_text_field($_REQUEST['lml_expiration_period']);
		if(!in_array($lml_expiration_period, array('Day', 'Week', 'Month', 'Year')))
			$lml_expiration_period = '';
	} else {
		$lml_expiration = '';
		$lml_expiration_number = '';
		$lml_expiration_period = '';
	}
	
	//build array
	$options = array(
		'lock' => $lml_lock,
		'expiration' => $lml_expiration,		
		'expiration_number' => $lml_expiration_number,
		'expiration_period' => $lml_expiration_period,
	);
	
	//save
	delete_option('pmprolml_level_' . $level_id . '_settings');
	add_option('pmprolml_level_' . $level_id . '_settings', $options, "", "no");
	update_option('pmprolml_level_' .$level_id. '_settings', $options, "no");
}
add_action("pmpro_save_membership_level", "pmprolml_pmpro_save_membership_level");

/*
	Hook into pmpro_after_change_membership_level
	- Remove any previous lml_expiration, pmprolml user meta.
	- Calculate a new lml_expiration based on the level they are changing to.
	- Save in user meta.
*/
function pmprolml_pmpro_after_change_membership_level($level_id, $user_id) {
	//delete any existing lml user meta
	delete_user_meta($user_id, 'pmprolml');
	delete_user_meta($user_id, 'pmprolml_expiration');
	
	//check if this level should lock
	$options = pmprolml_getLevelOptions($level_id);
	
	if(!empty($options) && $options['lock'] == 1) {
		//lock em
		update_user_meta($user_id, 'pmprolml', 1, 'no');
		
		//set expiration
		if(!empty($options['expiration'])) {
			$expiration = date( "Y-m-d", strtotime( "+ " . $options['expiration_number'] . " " . $options['expiration_period'], current_time( "timestamp" ) ) );
			update_user_meta($user_id, 'pmprolml_expiration', $expiration, 'no');
		}
	}
}
add_action('pmpro_after_change_membership_level', 'pmprolml_pmpro_after_change_membership_level', 10, 2);

/*
	Whenever you get the pmprolml user meta and it's locked, check if it has expired
*/
function pmprolml_get_user_metadata( $null, $object_id, $meta_key, $single ) {	
	if($meta_key == 'pmprolml') {
		$expiration = get_user_meta($object_id, 'pmprolml_expiration', true);
		if(!empty($expiration) && $expiration < date('Y-m-d', current_time('timestamp'))) {
			//they expired
			delete_user_meta($object_id, 'pmprolml');
			delete_user_meta($object_id, 'pmprolml_expiration');
			
			return false;
		}
	}
	
	return $null;		
}
add_action('get_user_metadata', 'pmprolml_get_user_metadata', 10, 4);

/*
	Function to add links to the plugin action links
*/
function pmprolml_add_action_links($links) {	
	$cap = apply_filters('pmpro_add_member_cap', 'edit_users');	
	if(current_user_can($cap))
	{
		$new_links = array(
			'<a href="' . get_admin_url(NULL, 'admin.php?page=pmpro-memberslist&l=locked') . '">' . __('View Locked Members', 'pmprolml') . '</a>',
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
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/pmpro-lock-membership-level/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('https://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmprolml_plugin_row_meta', 10, 2);
