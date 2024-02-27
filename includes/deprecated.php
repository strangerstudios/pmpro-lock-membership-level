<?php

/*
 * Add "Lock Membership" field in the profile for PMPro v2.x.
 * Only edits the lock for all level (ie level_id = 0).
 *
 * @param WP_User $user The user object.
 */
function pmprolml_show_extra_profile_fields($user) {
	if ( class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		return;
	}

	wp_get_current_user();

	$membership_level_capability = apply_filters("pmpro_edit_member_capability", "manage_options");

	if(!current_user_can($membership_level_capability))
		return false;

	// Get the "all" lock for the user if there is one.
	$locks = pmprolml_get_locks_for_user( $user->ID );
	$all_lock = null;
	foreach( $locks as $lock ) {
		if ( empty( $lock['level_id'] ) ) {
			$all_lock = $lock;
			break;
		}
	}

	$lml_expiration = ( empty( $all_lock ) || empty( $all_lock['expiration'] ) ) ? '' : date_i18n( 'Y-m-d 12:00:00', $all_lock['expiration'] );
		
	//some vars for the dates
	$current_day = date("j", current_time('timestamp'));			
	if(!empty($lml_expiration))
		$selected_expires_day = date("j", strtotime($lml_expiration, current_time('timestamp')));
	else
		$selected_expires_day = $current_day;
		
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
	<h2><?php esc_html_e('Lock Membership', 'pmpro-lock-membership-level');?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e('Lock Membership Level', 'pmpro-lock-membership-level');?></th>			
			<td>
				<label for="pmprolml">
					<input id="pmprolml" name="pmprolml" type="checkbox" value="1"<?php checked( ! empty( $all_lock ) ); ?> />
					<?php esc_html_e('Lock membership level changes for this user.', 'pmpro-lock-membership-level'); ?>
				</label>
			</td>
		</tr>
		<tr class="lml_expiration">
			<th scope="row" valign="top"><label for="lml_expiration"><?php esc_html_e('Unlock When?', 'pmpro-lock-membership-level');?></label></th>
			<td>
				<select id="lml_expiration" name="lml_expiration">
					<option value="" <?php selected($lml_expiration, '');?>><?php esc_html_e('Never', 'pmpro-lock-membership-level');?></option>
					<option value="date" <?php selected(!empty($lml_expiration), true);?>><?php esc_html_e('Specific Date', 'pmpro-lock-membership-level');?></option>
				</select>
				<span id="lml_expiration_date" <?php if(!$lml_expiration) { ?>style="display: none;"<?php } ?>>
					on
					<select id="lml_expiration_month" id="lml_expiration_month" name="lml_expiration_month">
						<?php																
							for($i = 1; $i < 13; $i++)
							{
							?>
							<option value="<?php echo $i?>" <?php if($i == $selected_expires_month) { ?>selected="selected"<?php } ?>><?php echo date("M", strtotime($i . "/15/" . $current_year, current_time("timestamp")))?></option>
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

/**
 * Save the "Lock Membership" field in the profile for PMPro v2.x.
 * Only edits the lock for all level (ie level_id = 0).
 */
function pmprolml_save_extra_profile_fields( $user_id ) {
	if ( class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		return;
	}

	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;

	if ( empty( sanitize_text_field( $_POST['pmprolml'] ) ) ) {
		// Delete the "all" lock for the user.
		pmprolml_delete_lock_for_user( $user_id, 0 );
	} else {
		// Update the "all" lock for the user.
		$expiration = empty( $_POST['lml_expiration'] ) ? 0 : strtotime( $_POST['lml_expiration_year'] . '-' . $_POST['lml_expiration_month'] . '-' . $_POST['lml_expiration_day'] . ' 12:00:00' );
		pmprolml_add_lock_for_user( $user_id, 0, $expiration );
	}
}
add_action( 'personal_options_update', 'pmprolml_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'pmprolml_save_extra_profile_fields' );
