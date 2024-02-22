<?php

class PMProlml_Member_Edit_Panel extends PMPro_Member_Edit_Panel {
	/**
	 * Set up the panel.
	 */
	public function __construct() {
		$this->slug = 'pmprolml';
		$this->title = __( 'Membership Locks', 'pmpro-lock-membership-level' );
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

		// If there are no reasons, display a message and return.
		if ( empty( $locks ) ) {
			?>
			<p><?php esc_html_e( 'This user does not have any membership locks.', 'pmpro-lock-membership-level' ); ?></p>
			<?php
		} else {
			// Order locks by level ID.
			usort( $locks, function( $a, $b ) {
				return $a['level_id'] - $b['level_id'];
			} );

			// Display the locks in a table.
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Level', 'pmpro-lock-membership-level' ); ?></th>
						<th><?php esc_html_e( 'Lock Expiration', 'pmpro-lock-membership-level' ); ?></th>
						<th><?php esc_html_e( 'Delete', 'pmpro-lock-membership-level' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $locks as $lock ) {
						?>
						<tr>
							<td>
								<?php
								echo esc_html( empty( $lock['level_id'] ) ? __( 'All', 'pmpro-lock-membership-level' ) : pmpro_getLevel( $lock['level_id'] )->name );
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
					?>
				</tbody>
			</table>
			<?php
		}
		?>

		<h3><?php esc_html_e( 'Add a Lock', 'pmpro-lock-membership-level' ); ?></h3>
		<p><?php esc_html_e( 'Use the form below to add or update a membership lock for this user.', 'pmpro-lock-membership-level' ); ?></p>
		<select name="pmprolml_level_id">
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
		<select name="pmprolml_expiration">
			<option value="0"><?php esc_html_e( 'Never', 'pmpro-lock-membership-level' ); ?></option>
			<option value="1"><?php esc_html_e( 'Specific Date', 'pmpro-lock-membership-level' ); ?></option>
		</select>
		<input type="datetime-local" name="pmprolml_expiration_date" style="display: none;" value="<?php echo esc_attr( current_time( 'Y-m-d H:i' ) ); ?>"/>
		<input type="submit" name="pmprolml_add_lock" value="<?php esc_html_e( 'Add Lock', 'pmpro-lock-membership-level' ); ?>" class="button" />

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
