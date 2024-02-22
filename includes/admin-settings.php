<?php

/**
 * Add a page and assign it under Memberships > Page settings that will redirect locked members to 
 * using the shortcode [pmpro_membership_locked]. Add shortcode attribute "message" to customize the message shown.
 *
 * To lock a member from changing their membership level, edit the user and check the box labeled 
 * "Lock Membership Level Changes".
 *
 * @param array $pages Array of pages and their settings.
 * @return array $pages Array of pages and their settings.
 */
function pmprolml_extra_page_settings($pages) {
	$pages['membership_locked'] = array('title'=> esc_html__('Membership Locked', 'pmpro-lock-membership-level'), 'content'=>'[pmpro_membership_locked]', 'hint'=> sprintf( esc_html__('Include the shortcode %s.', 'pmpro-lock-membership-level'), '[pmpro_membership_locked]' ) );
	return $pages;
}
add_action('pmpro_extra_page_settings', 'pmprolml_extra_page_settings');

/**
 * Add settings to the edit level page.
 *
 * @since 1.0
 *
 * @param object $level The level object being edited.
 */
function pmprolml_membership_level_before_content_settings( $level ) {
	$level_id = intval($_REQUEST['edit']);
	$options = pmprolml_getLevelOptions($level_id);

	// Build the settings UI.
	if ( empty( $options['lock'] ) ) {
		$section_visibility = 'hidden';
		$section_activated = 'false';
	} else {
		$section_visibility = 'shown';
		$section_activated = 'true';
	}
	?>
	<div id="pmpro-lock-membership-level" class="pmpro_section" data-visibility="<?php echo esc_attr( $section_visibility ); ?>" data-activated="<?php echo esc_attr( $section_activated ); ?>">
		<div class="pmpro_section_toggle">
			<button class="pmpro_section-toggle-button" type="button" aria-expanded="<?php echo $section_visibility === 'hidden' ? 'false' : 'true'; ?>">
				<span class="dashicons dashicons-arrow-<?php echo $section_visibility === 'hidden' ? 'down' : 'up'; ?>-alt2"></span>
				<?php esc_html_e('Lock Membership Level Settings', 'pmpro-lock-membership-level'); ?>
			</button>
		</div>
		<div class="pmpro_section_inside" <?php echo $section_visibility === 'hidden' ? 'style="display: none"' : ''; ?>>
			<table>
				<tbody class="form-table">
					<tr>
						<th scope="row" valign="top"><label for="lml_lock"><?php esc_html_e('Lock This Level?', 'pmpro-lock-membership-level');?></label></th>
						<td>
							<input type="checkbox" id="lml_lock" name="lml_lock" <?php checked($options['lock'], 1);?>><label for="lml_lock"><?php esc_html_e('Check to lock users from cancelling or changing levels after they get this level.', 'pmpro-lock-membership-level');?></label>
						</td>
					</tr>
					<tr class="lml_expiration">
						<th scope="row" valign="top"><label for="lml_expiration"><?php esc_html_e('Unlock When?', 'pmpro-lock-membership-level');?></label></th>
						<td>
							<select id="lml_expiration" name="lml_expiration">
								<option value="" <?php selected($options['expiration'], '');?>><?php esc_html_e('Never', 'pmpro-lock-membership-level');?></option>
								<option value="period" <?php selected($options['expiration'], 'period');?>><?php esc_html_e('Time Period', 'pmpro-lock-membership-level');?></option>				
							</select>
							<br /><br />
							<input id="lml_expiration_number" name="lml_expiration_number" type="text" size="10" value="<?php echo esc_attr($options['expiration_number']);?>" />
							<select id="lml_expiration_period" name="lml_expiration_period">
							<?php
								$cycles = array( 
									esc_html__('Day(s)', 'pmpro-lock-membership-level') => 'Day', 
									esc_html__('Week(s)', 'pmpro-lock-membership-level') => 'Week', 
									esc_html__('Month(s)', 'pmpro-lock-membership-level') => 'Month', 
									esc_html__('Year(s)', 'pmpro-lock-membership-level') => 'Year' );
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
		</div> <!-- end .pmpro_section_inside -->
	</div> <!-- end .pmpro_section -->
	<?php
}
add_action( 'pmpro_membership_level_before_content_settings', 'pmprolml_membership_level_before_content_settings' );

/**
 * Save pay by check settings when the level is saved/added.
 *
 * @param int $level_id The ID of the membership level.
 */
function pmprolml_pmpro_save_membership_level($level_id) {
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
		'lock' => $lml_lock, // true or false.
		'expiration' => $lml_expiration, // Empty string if locks do not expire, 'period' if they expire after a set period.	
		'expiration_number' => $lml_expiration_number, // Number of periods until locks expire.
		'expiration_period' => $lml_expiration_period, // Length of a single period.
	);
	
	//save
	delete_option('pmprolml_level_' . $level_id . '_settings');
	add_option('pmprolml_level_' . $level_id . '_settings', $options, "", "no");
	update_option('pmprolml_level_' .$level_id. '_settings', $options, "no");
}
add_action("pmpro_save_membership_level", "pmprolml_pmpro_save_membership_level");