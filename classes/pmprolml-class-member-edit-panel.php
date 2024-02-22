<?php

class PMProlml_Member_Edit_Panel extends PMPro_Member_Edit_Panel {
	/**
	 * Set up the panel.
	 */
	public function __construct() {
		$this->slug = 'lock-membership-level';
		$this->title = __( 'Locked Memberships', 'pmpro-lock-membership-level' );
	}

	/**
	 * Display the panel contents.
	 */
	protected function display_panel_contents() {
		// Get the user being edited.
		$user = self::get_user();

		// Get all levels for this user.
		$user_levels = pmpro_getMembershipLevelsForUser( $user->ID );

		// Get all locks for this user.
		$locks = pmprolml_get_locks_for_user( $user->ID );

		// Are any locks for all levels?
		$all_levels_locked = false;
		foreach ( $locks as $lock ) {
			if ( empty( $lock['level_id'] ) ) {
				$all_levels_locked = true;
				break;
			}
		}

		// Display the locks in a table.
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Level', 'pmpro-lock-membership-level' ); ?></th>
					<th><?php esc_html_e( 'Lock Expiration', 'pmpro-lock-membership-level' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'pmpro-lock-membership-level' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					// If there are no locks, display a message.
					if ( empty( $locks ) ) {
						?>
						<tr>
							<td colspan="3">
								<p><?php esc_html_e( 'This user does not have any locked memberships.', 'pmpro-lock-membership-level' ); ?></p>
							</td>
						</tr>
						<?php
					} else {
						// Order locks by level ID.
						usort( $locks, function( $a, $b ) {
							return $a['level_id'] - $b['level_id'];
						} );
						foreach ( $locks as $lock ) {
							?>
							<tr>
								<td>
									<?php
									echo esc_html( empty( $lock['level_id'] ) ? __( 'All Memberships', 'pmpro-lock-membership-level' ) : pmpro_getLevel( $lock['level_id'] )->name );
									// If the level is not '0' and the user doesn't have this level, show an error.
									if ( ! empty( $lock['level_id'] ) && ! in_array( $lock['level_id'], wp_list_pluck( $user_levels, 'ID' ) ) ) {
										?>
										<span class="pmpro_tag pmpro_tag-has_icon pmpro_tag-error">
											<?php esc_html_e( 'Membership Ended', 'pmpro-lock-membership-level' ); ?>
										</span>
										<?php
									}
									?>
								</td>
								<td>
									<?php
									// Get expiration in local time.
									$expiration = empty( $lock['expiration'] ) ? __( 'Never', 'pmpro-lock-membership-level' ) : get_date_from_gmt( date( 'Y-m-d H:i:s', $lock['expiration'] ), get_option( 'date_format' ) ) . ' at ' . get_date_from_gmt( date( 'Y-m-d H:i:s', $lock['expiration'] ), get_option( 'time_format' ) );
									echo esc_html( $expiration );
									?>
								</td>
								<td><input type="submit" name="pmprolml_delete_lock_<?php echo (int)$lock['level_id'] ?>" value="<?php esc_html_e( 'Delete', 'pmpro-lock-membership-level' ); ?>" class="button" /></td>
							</tr>
							<?php
						}
					}
				?>
				<tr class="pmpro-level_change" style="display: none;">
					<td colspan="3">
						<div class="pmpro-level_change-actions">
							<div class="pmpro-level_change-action-header">
								<h4><?php esc_html_e( 'Add a Membership Lock', 'pmpro-lock-membership-level' ); ?></h4>
								<p><?php esc_html_e( 'Use the form below to add a new locked membership or update an existing locked membership for this user.', 'pmpro-lock-membership-level' ); ?></p>
							</div>
							<div class="pmpro-level_change-action">
								<span class="pmpro-level_change-action-label">
									<?php esc_html_e( 'Membership Level', 'pmpro-lock-membership-level' ); ?>
								</span>
								<span class="pmpro-level_change-action-field">
									<select id="pmprolml_level_id" name="pmprolml_level_id">
										<option value="0"><?php esc_html_e( 'All Levels', 'pmpro-lock-membership-level' ); ?></option>
										<?php
										foreach ( $user_levels as $user_level ) {
											$level = pmpro_getLevel( $user_level->ID );
											?>
											<option value="<?php echo esc_attr( $level->id ); ?>"><?php echo esc_html( $level->name ); ?></option>
											<?php
										}
										?>
									</select>
								</span>
							</div>
							<div class="pmpro-level_change-action">
								<span class="pmpro-level_change-action-label">
									<label for="pmprolml_expiration"><?php esc_html_e( 'Lock Expiration', 'pmpro-lock-membership-level' ); ?></label>
								</span>
								<span class="pmpro-level_change-action-field">
									<select id="pmprolml_expiration" name="pmprolml_expiration">
										<option value="0"><?php esc_html_e( 'Never', 'pmpro-lock-membership-level' ); ?></option>
										<option value="1"><?php esc_html_e( 'Specific Date', 'pmpro-lock-membership-level' ); ?></option>
									</select>
									<input type="datetime-local" name="pmprolml_expiration_date" style="display: none;" value="<?php echo esc_attr( date( 'Y-m-d H:i', strtotime( '+1 year' ) ) ); ?>"/>
								</span>
							</div>
							<div class="pmpro-level_change-action-footer">
								<input type="submit" name="pmprolml_add_lock" value="<?php esc_html_e( 'Add Lock', 'pmpro-lock-membership-level' ); ?>" class="button button-primary">
								<input type="button" name="cancel-add-lock" value="<?php esc_attr_e( 'Close', 'pmpro-lock-membership-level' ); ?>" class="button button-secondary">
							</div>
						</div> <!-- end pmpro-level_change-actions -->
					</td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="3">
						<a class="button-secondary pmpro-has-icon pmpro-has-icon-plus pmpro-add-membership-lock" href="#" ><?php esc_html_e( 'Add Lock', 'pmpro-lock-membership-level' ); ?></a>
					</td>
				</tr>
			</tfoot>
		</table>

		<script>
			jQuery(document).ready(function() {
				// Show/hide the expiration date field.
				jQuery('select[name=pmprolml_expiration]').change(function() {
					if ( jQuery(this).val() === '1' ) {
						jQuery('input[name=pmprolml_expiration_date]').show();
					} else {
						jQuery('input[name=pmprolml_expiration_date]').hide();
					}
				});

				// Button to show add lock.
				jQuery('#pmpro-member-edit-lock-membership-level-panel a.pmpro-add-membership-lock').on('click', function (event) {
					event.preventDefault();
					var currentRow = jQuery(this).closest('tr');
					var formRow = jQuery('.pmpro-level_change');
					formRow.show();
					currentRow.closest('table').find('tfoot').hide();

					// Add muted class to all other <tr> elements in the same table.
					currentRow.closest('table').find('tr:not(.pmpro-level_change)').addClass('pmpro_opaque');
				});

				// Button to cancel adding the lock.
				jQuery('#pmpro-member-edit-lock-membership-level-panel input[name=cancel-add-lock]').on('click', function (event) {
					event.preventDefault();
					var currentRow = jQuery(this).closest('tr');
					var formRow = jQuery('.pmpro-level_change');
					currentRow.closest('table').find('tfoot').show();
					formRow.hide();
					currentRow.closest('table').find('tr:not(.pmpro-level_change)').removeClass('pmpro_opaque');
				});
			});
		</script>
		<?php
	}

	/**
	 * Process the form submission.
	 */
	public function save() {
		// Check for deletes.
		if ( ! empty( $_POST ) ) {
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'pmprolml_delete_lock_' ) === 0 ) {
					$level_id = (int)str_replace( 'pmprolml_delete_lock_', '', $key );
					pmprolml_delete_lock_for_user( self::get_user()->ID, $level_id );
				}
			}
		}

		// Check for adds/updates.
		if ( ! empty( $_POST['pmprolml_add_lock'] ) ) {
			$level_id = (int)$_POST['pmprolml_level_id'];
			$expiration = (int)$_POST['pmprolml_expiration'] === 1 ? strtotime( get_gmt_from_date( $_POST['pmprolml_expiration_date'] ) ) : 0;
			pmprolml_add_lock_for_user( self::get_user()->ID, $level_id, $expiration );
		}
	}
}
