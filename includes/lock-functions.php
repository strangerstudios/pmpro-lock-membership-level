<?php

/**
 * Get all locks for a user.
 *
 * @since 1.0
 *
 * @param int $user_id The user ID to get locks for.
 * @return array An array of locks for the user.
 */
function pmprolml_get_locks_for_user( $user_id ) {
	// Make sure we have an int.
	$user_id = (int)$user_id;

	// For backwards-compatibility, check old user meta values.
	if ( ! empty( get_user_meta( $user_id, 'pmprolml', true ) ) ) {
		// The user was locked with the old method. Convert it to the new method.
		$expiration = get_user_meta( $user_id, 'pmprolml_expiration', true );

		// Delete the old user meta.
		delete_user_meta( $user_id, 'pmprolml' );
		delete_user_meta( $user_id, 'pmprolml_expiration' );

		// Add the lock to the new method.
		if ( ! empty( $expiration ) ) {
			// Add the lock to the new method with expiration.
			pmprolml_add_lock_for_user( $user_id, 0, strtotime( get_gmt_from_date( $expiration ) ) );
		} else {
			// Add the lock to the new method without expiration.
			pmprolml_add_lock_for_user( $user_id, 0, 0 );
		}
	}

	// Get all locks from the database.
	$user_locks = get_user_meta( $user_id, 'pmprolml_lock' );

	// If any lock is expired, delete it.
	$user_locks_to_return = array();
	foreach( $user_locks as $lock ) {
		// If the lock is expired, delete it.
		if ( (int)$lock['expiration'] !== 0 && (int)$lock['expiration'] < time() ) {
			delete_user_meta( $user_id, 'pmprolml_lock', $lock );
			continue;
		}

		// If the lock requires a number of successful payments and that many have been made, delete it.
		if ( ! empty( $lock['payments_required'] ) && pmprolml_count_successful_payments_for_user( $user_id, $lock['level_id'] ) >= (int)$lock['payments_required'] ) {
			delete_user_meta( $user_id, 'pmprolml_lock', $lock );
			continue;
		}

		// Add the lock to the array to return.
		$user_locks_to_return[] = $lock;
	}

	// If using PMPro v2.x, we only want to consider "all" locks (level_id = 0).
	if ( ! class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		foreach ( $user_locks_to_return as $key => $lock ) {
			if ( (int)$lock['level_id'] !== 0 ) {
				unset( $user_locks_to_return[ $key ] );
			}
		}
	}

	// Return the active locks.
	return $user_locks_to_return;
}

/**
 * Check if a specific level is locked for a user.
 *
 * @since 1.0
 *
 * @param int $user_id The user ID to check.
 * @param int $level_id The level ID to check.
 * @return bool True if the level is locked for the user, false otherwise.
 */
function pmprolml_is_level_locked_for_user( $user_id, $level_id ) {
	// Make sure we have all ints.
	$user_id = (int)$user_id;
	$level_id = (int)$level_id;

	// If using PMPro v2.x, we only want to consider "all" locks (level_id = 0).
	if ( ! class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		$level_id = 0;
	}

	// Get all locks for the user.
	$user_locks = pmprolml_get_locks_for_user( $user_id );

	// If one of the locks has the same level ID or is locking all levels, return true.
	foreach ( $user_locks as $lock ) {
		if ( (int)$lock['level_id'] === $level_id || (int)$lock['level_id'] === 0 ) {
			return true;
		}
	}

	return false;
}

/**
 * Count the number of successful payments a user has made for a membership level.
 *
 * @since TBD
 *
 * @param int $user_id The user ID to check.
 * @param int $level_id The level ID to check, or 0 to count successful payments for all levels.
 * @return int The number of successful payments.
 */
function pmprolml_count_successful_payments_for_user( $user_id, $level_id ) {
	global $wpdb;

	// Make sure we have all ints.
	$user_id = (int)$user_id;
	$level_id = (int)$level_id;

	if ( empty( $level_id ) ) {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders WHERE user_id = %d AND status = 'success'", $user_id ) );
	} else {
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders WHERE user_id = %d AND membership_id = %d AND status = 'success'", $user_id, $level_id ) );
	}

	return (int)$count;
}

/**
 * Add a lock for a user.
 *
 * @since 1.0
 *
 * @param int $user_id The user ID to add the lock for.
 * @param int $level_id The level ID to lock or 0 to lock all levels.
 * @param int $expiration The expiration timestamp or 0 for no expiration.
 * @param int $payments_required The number of successful payments required to unlock, or 0 to not use this criteria.
 */
function pmprolml_add_lock_for_user( $user_id, $level_id, $expiration, $payments_required = 0 ) {
	// Make sure we have all ints.
	$user_id = (int)$user_id;
	$level_id = (int)$level_id;
	$expiration = (int)$expiration;
	$payments_required = (int)$payments_required;

	// If using PMPro v2.x, we only want to consider "all" locks (level_id = 0).
	if ( ! class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		$level_id = 0;
	}

	// Build the lock data to save.
	$lock_data = array(
		'level_id' => $level_id,
		'expiration' => $expiration,
		'payments_required' => $payments_required,
	);

	// Check if the user already has a lock for the same level.
	$user_locks = pmprolml_get_locks_for_user( $user_id );
	foreach( $user_locks as $lock ) {
		// If the lock has the same level ID, update this lock instead of adding a new one.
		if ( (int)$lock['level_id'] === $level_id ) {
			// Update the lock in the database.
			update_user_meta( $user_id, 'pmprolml_lock', $lock_data, $lock );
			return;
		}
	}

	// Add the lock to the database.
	add_user_meta( $user_id, 'pmprolml_lock', $lock_data );
}

/**
 * Delete a lock for a user.
 *
 * @since 1.0
 *
 * @param int $user_id The user ID to delete the lock for.
 * @param int $level_id The level ID to delete the lock for.
 */
function pmprolml_delete_lock_for_user( $user_id, $level_id ) {
	// Make sure we have all ints.
	$user_id = (int)$user_id;
	$level_id = (int)$level_id;

	// If using PMPro v2.x, we only want to consider "all" locks (level_id = 0).
	if ( ! class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		$level_id = 0;
	}

	// Get all locks for the user.
	$user_locks = pmprolml_get_locks_for_user( $user_id );

	// Delete the locks from the database.
	foreach( $user_locks as $lock ) {
		// If the lock has the same level ID, delete it.
		if ( (int)$lock['level_id'] === $level_id ) {
			// Delete the lock from the database.
			delete_user_meta( $user_id, 'pmprolml_lock', $lock );
		}
	}
}

/**
 * When users change levels, add/remove locks as needed.
 *
 * @since 1.0
 *
 * @param array $pmpro_old_user_levels An array of old user levels ($user_id => $old_levels[]).
 */
function pmprolml_after_all_membership_level_changes( $pmpro_old_user_levels ) {		
	foreach ( $pmpro_old_user_levels as $user_id => $old_levels ) {
		// Get current level IDs.
		$current_levels = pmpro_getMembershipLevelsForUser( $user_id );
		if ( ! empty( $current_levels ) ) {
			$current_levels = wp_list_pluck( $current_levels, 'ID' );
		} else {
			$current_levels = array();
		}
		
		// Get old level IDs.
		$old_levels = wp_list_pluck( $old_levels, 'ID' );
		
		// Get all levels that were added.
		$added_levels = array_diff( $current_levels, $old_levels );

		// Get all levels that were removed.
		$removed_levels = array_diff( $old_levels, $current_levels );

		// Remove locks for all removed levels.
		foreach ( $removed_levels as $level_id ) {
			pmprolml_delete_lock_for_user( $user_id, $level_id );
		}

		// Add locks for all added levels.
		foreach ( $added_levels as $level_id ) {
			$options = pmprolml_getLevelOptions( $level_id );
			if ( ! empty( $options ) && $options['lock'] == 1 ) {
				$expiration = 0;
				$payments_required = 0;

				if ( 'period' === $options['expiration'] && ! empty( $options['expiration_number'] ) ) {
					$expiration = strtotime( '+' . $options['expiration_number'] . ' ' . $options['expiration_period'] );
				} elseif ( 'payments' === $options['expiration'] && ! empty( $options['expiration_payments_count'] ) ) {
					$payments_required = (int)$options['expiration_payments_count'];
				}

				pmprolml_add_lock_for_user( $user_id, $level_id, $expiration, $payments_required );
			}
		}

	}
}
add_action( 'pmpro_after_all_membership_level_changes', 'pmprolml_after_all_membership_level_changes' );
