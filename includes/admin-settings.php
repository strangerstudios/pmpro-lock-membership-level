<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	$pages['membership_locked'] = array(
		'title'   => esc_html__( 'Membership Locked', 'pmpro-lock-membership-level' ),
		'content' => '[pmpro_membership_locked]',
		/* translators: %s: the [pmpro_membership_locked] shortcode. */
		'hint'    => sprintf( esc_html__( 'Include the shortcode %s.', 'pmpro-lock-membership-level' ), '[pmpro_membership_locked]' ),
	);
	return $pages;
}
add_action('pmpro_extra_page_settings', 'pmprolml_extra_page_settings');

/**
 * Get lock options for a membership level
 *
 * @param int $level_id The level ID to get lock options for.
 * @return array The lock options for the level.
 */
function pmprolml_getLevelOptions($level_id) {
	$options = get_option('pmprolml_level_' . intval($level_id) . '_settings', array('lock' => 0, 'expiration' => null,'expiration_number' => null, 'expiration_period' => null ) );

	// For backwards-compatibility, options saved before the payment-count option was added won't have this key set.
	if ( ! isset( $options['expiration_payments_count'] ) ) {
		$options['expiration_payments_count'] = null;
	}

	return $options;
}

/**
 * Add settings to the edit level page.
 *
 * @since 1.0
 *
 * @param object $level The level object being edited.
 */
function pmprolml_membership_level_before_content_settings( $level ) {
	// Nonce is verified by PMPro core before rendering the edit level page.
	$level_id = isset( $_REQUEST['edit'] ) ? intval( $_REQUEST['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
			<p>
				<?php
				$lock_membership_link = '<a title="' . esc_attr__( 'Lock Membership Level Add On Documentation', 'pmpro-lock-membership-level' ) . '" target="_blank" rel="nofollow noopener" href="https://www.paidmembershipspro.com/add-ons/pmpro-lock-membership-level/?utm_source=plugin&utm_medium=pmpro-lock-membership-level&utm_campaign=add-ons">' . esc_html__( 'Lock Membership Level Add On', 'pmpro-lock-membership-level' ) . '</a>';
				printf(
					/* translators: %s: link to the Lock Membership Level Add On documentation. */
					esc_html__( 'Learn more about the %s.', 'pmpro-lock-membership-level' ),
					$lock_membership_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
				);
				?>
			</p>
			<style>
				#pmpro-lock-membership-level .form-table td input[type="number"],
				#pmpro-lock-membership-level .form-table td select {
					vertical-align: middle;
				}
			</style>
			<table class="form-table">
				<tbody>
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
								<option value="period" <?php selected($options['expiration'], 'period');?>><?php esc_html_e('After a Time Period', 'pmpro-lock-membership-level');?></option>
								<option value="payments" <?php selected($options['expiration'], 'payments');?>><?php esc_html_e('After a Number of Successful Payments', 'pmpro-lock-membership-level');?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose when members of this level should be allowed to cancel or change levels again.', 'pmpro-lock-membership-level' ); ?></p>
						</td>
					</tr>
					<tr class="lml_expiration_period_fields">
						<th scope="row" valign="top"><label for="lml_expiration_number"><?php esc_html_e('Unlock After', 'pmpro-lock-membership-level');?></label></th>
						<td>
							<input id="lml_expiration_number" name="lml_expiration_number" type="number" min="1" step="1" class="small-text" value="<?php echo esc_attr( $options['expiration_number'] ); ?>" />
							<label for="lml_expiration_period" class="screen-reader-text"><?php esc_html_e( 'Time Period', 'pmpro-lock-membership-level' ); ?></label>
							<select id="lml_expiration_period" name="lml_expiration_period">
								<?php
								$cycles = array(
									'Day'   => __( 'Day(s)',   'pmpro-lock-membership-level' ),
									'Week'  => __( 'Week(s)',  'pmpro-lock-membership-level' ),
									'Month' => __( 'Month(s)', 'pmpro-lock-membership-level' ),
									'Year'  => __( 'Year(s)',  'pmpro-lock-membership-level' ),
								);
								foreach ( $cycles as $value => $name ) {
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $options['expiration_period'], $value ); ?>><?php echo esc_html( $name ); ?></option>
									<?php
								}
								?>
							</select>
							<p class="description"><?php esc_html_e( 'The lock is removed once this much time has passed since the member received this level.', 'pmpro-lock-membership-level' ); ?></p>
						</td>
					</tr>
					<tr class="lml_expiration_payments_fields">
						<th scope="row" valign="top"><label for="lml_expiration_payments_count"><?php esc_html_e('Successful Payments Required', 'pmpro-lock-membership-level');?></label></th>
						<td>
							<input id="lml_expiration_payments_count" name="lml_expiration_payments_count" type="number" min="1" step="1" class="small-text" value="<?php echo esc_attr( $options['expiration_payments_count'] ); ?>" />
							<p class="description"><?php esc_html_e( 'The lock is removed once the member has made this many successful payments for this level, including the initial payment.', 'pmpro-lock-membership-level' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<script>
				function toggleLMLOptions() {
					var locked = jQuery('#lml_lock').is(':checked');
					var expiration = jQuery('#lml_expiration').val();
					var showPeriod = locked && expiration == 'period';
					var showPayments = locked && expiration == 'payments';

					// Disable fields while their row is hidden so the browser excludes them from
					// HTML5 constraint validation (an invalid field inside a hidden row can't be
					// focused, which silently blocks form submission).
					jQuery('tr.lml_expiration').toggle(locked).find(':input').prop('disabled', !locked);
					jQuery('tr.lml_expiration_period_fields').toggle(showPeriod).find(':input').prop('disabled', !showPeriod);
					jQuery('tr.lml_expiration_payments_fields').toggle(showPayments).find(':input').prop('disabled', !showPayments);
				}

				jQuery(document).ready(function(){
					//hide/show unlock fields on page load
					toggleLMLOptions();

					//hide/show unlock fields when the lock or unlock settings change
					jQuery('#lml_lock').change(function() { toggleLMLOptions() });
					jQuery('#lml_expiration').change(function() { toggleLMLOptions() });
				});
			</script>
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
	// Nonce is verified by PMPro core before the pmpro_save_membership_level action fires.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended

	//get values
	$lml_lock = isset( $_REQUEST['lml_lock'] ) ? true : false;
	
	if(!empty($lml_lock) && isset($_REQUEST['lml_expiration'])) {
		$lml_expiration = sanitize_text_field( wp_unslash( $_REQUEST['lml_expiration'] ) );
		if(!in_array($lml_expiration, array('period', 'payments')))
			$lml_expiration = '';

		$lml_expiration_number = isset( $_REQUEST['lml_expiration_number'] ) ? intval( $_REQUEST['lml_expiration_number'] ) : 0;

		$lml_expiration_period = isset( $_REQUEST['lml_expiration_period'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['lml_expiration_period'] ) ) : '';
		if(!in_array($lml_expiration_period, array('Day', 'Week', 'Month', 'Year')))
			$lml_expiration_period = '';

		$lml_expiration_payments_count = isset( $_REQUEST['lml_expiration_payments_count'] ) ? intval( $_REQUEST['lml_expiration_payments_count'] ) : 0;

		// If the chosen unlock method has no usable value, treat the lock as never unlocking.
		if ( ( 'period' === $lml_expiration && ( $lml_expiration_number < 1 || empty( $lml_expiration_period ) ) ) || ( 'payments' === $lml_expiration && $lml_expiration_payments_count < 1 ) ) {
			$lml_expiration = '';
		}
	} else {
		$lml_expiration = '';
		$lml_expiration_number = '';
		$lml_expiration_period = '';
		$lml_expiration_payments_count = '';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	//build array
	$options = array(
		'lock' => $lml_lock, // true or false.
		'expiration' => $lml_expiration, // Empty string if locks do not expire, 'period' if they expire after a set period, 'payments' if they expire after a number of successful payments.
		'expiration_number' => $lml_expiration_number, // Number of periods until locks expire.
		'expiration_period' => $lml_expiration_period, // Length of a single period.
		'expiration_payments_count' => $lml_expiration_payments_count, // Number of successful payments until locks expire.
	);
	
	//save
	delete_option('pmprolml_level_' . $level_id . '_settings');
	add_option('pmprolml_level_' . $level_id . '_settings', $options, "", "no");
	update_option('pmprolml_level_' .$level_id. '_settings', $options, "no");
}
add_action("pmpro_save_membership_level", "pmprolml_pmpro_save_membership_level");