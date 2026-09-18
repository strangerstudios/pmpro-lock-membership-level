<?php

/**
 * Get all lock records for a user, including payment-based locks whose payment
 * requirement is currently satisfied.
 *
 * Time-based locks that have expired are deleted here since they can never become
 * active again. Payment-based locks are always kept so that they become active again
 * if a counted order is later deleted or refunded.
 *
 * @since TBD
 *
 * @param int $user_id The user ID to get locks for.
 * @return array An array of lock records for the user.
 */
function pmprolml_get_all_locks_for_user( $user_id ) {
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

	// If any time-based lock is expired, delete it.
	$user_locks_to_return = array();
	foreach( $user_locks as $lock ) {
		if ( (int)$lock['expiration'] !== 0 && (int)$lock['expiration'] < time() ) {
			delete_user_meta( $user_id, 'pmprolml_lock', $lock );
			continue;
		}

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

	return array_values( $user_locks_to_return );
}

/**
 * Check whether a payment-based lock has had its payment requirement met.
 *
 * @since TBD
 *
 * @param int   $user_id The user ID the lock belongs to.
 * @param array $lock    The lock record.
 * @return bool True if the lock requires payments and enough have been made, false otherwise.
 */
function pmprolml_is_lock_satisfied_by_payments( $user_id, $lock ) {
	if ( empty( $lock['payments_required'] ) ) {
		return false;
	}

	return pmprolml_count_successful_payments_for_user( $user_id, $lock['level_id'], $lock ) >= (int)$lock['payments_required'];
}

/**
 * Get all active locks for a user.
 *
 * @since 1.0
 *
 * @param int $user_id The user ID to get locks for.
 * @return array An array of active locks for the user.
 */
function pmprolml_get_locks_for_user( $user_id ) {
	$user_id = (int)$user_id;

	$active_locks = array();
	foreach ( pmprolml_get_all_locks_for_user( $user_id ) as $lock ) {
		// Payment-based locks are evaluated on every read rather than deleted, so that
		// deleting or refunding a counted order re-activates the lock.
		if ( pmprolml_is_lock_satisfied_by_payments( $user_id, $lock ) ) {
			continue;
		}

		$active_locks[] = $lock;
	}

	return $active_locks;
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
 * Track orders that reach the "success" status during the current request.
 *
 * At checkout, the order is marked successful in the same request in which the level
 * is changed, and the lock is created at the end of that request. Remembering the
 * order lets the lock count the checkout payment even though the order was created
 * (and timestamped) before the lock.
 *
 * @since TBD
 *
 * @param MemberOrder $order The order that was added or updated.
 */
function pmprolml_track_successful_order( $order ) {
	global $pmprolml_successful_orders_this_request;

	if ( empty( $order->id ) || empty( $order->status ) || 'success' !== $order->status ) {
		return;
	}

	if ( ! is_array( $pmprolml_successful_orders_this_request ) ) {
		$pmprolml_successful_orders_this_request = array();
	}

	$pmprolml_successful_orders_this_request[ (int)$order->id ] = array(
		'user_id'       => (int)$order->user_id,
		'membership_id' => (int)$order->membership_id,
	);
}
add_action( 'pmpro_added_order', 'pmprolml_track_successful_order' );
add_action( 'pmpro_updated_order', 'pmprolml_track_successful_order' );

/**
 * Get the ID of the order that completed during this request for a user and level, if any.
 *
 * @since TBD
 *
 * @param int $user_id  The user ID.
 * @param int $level_id The level ID.
 * @return int The order ID, or 0 if no successful order for this user and level was saved during this request.
 */
function pmprolml_get_checkout_order_id_for_lock( $user_id, $level_id ) {
	global $pmprolml_successful_orders_this_request;

	if ( empty( $pmprolml_successful_orders_this_request ) ) {
		return 0;
	}

	$user_id = (int)$user_id;
	$level_id = (int)$level_id;

	$matching_ids = array();
	foreach ( $pmprolml_successful_orders_this_request as $order_id => $order_info ) {
		if ( $order_info['user_id'] === $user_id && ( 0 === $level_id || $order_info['membership_id'] === $level_id ) ) {
			$matching_ids[] = (int)$order_id;
		}
	}

	return empty( $matching_ids ) ? 0 : min( $matching_ids );
}

/**
 * Count the number of successful payments a user has made for a membership level.
 *
 * Only orders with a status of "success" and a total greater than zero are counted.
 * When a lock is passed, only payments belonging to the member's current membership are
 * counted: the order that completed when the lock was created (the checkout order) plus
 * any successful orders dated on or after the membership start date. Payments dated
 * before the membership started are not counted.
 *
 * @since TBD
 *
 * @param int        $user_id  The user ID to check.
 * @param int        $level_id The level ID to check, or 0 to count successful payments for all levels.
 * @param array|null $lock     Optional. The lock record to scope the count to.
 * @return int The number of successful payments.
 */
function pmprolml_count_successful_payments_for_user( $user_id, $level_id, $lock = null ) {
	global $wpdb;

	// Make sure we have all ints.
	$user_id = (int)$user_id;
	$level_id = (int)$level_id;

	$where = array( 'user_id = %d', "status = 'success'", 'total > 0' );
	$values = array( $user_id );

	if ( ! empty( $level_id ) ) {
		$where[] = 'membership_id = %d';
		$values[] = $level_id;
	}

	// Scope the count to the current membership when we know when the lock was created.
	// Locks saved before this data was stored count all payments, as they always did.
	if ( is_array( $lock ) && ! empty( $lock['created'] ) ) {
		// Both membership start dates and order timestamps are stored in UTC.
		if ( ! empty( $level_id ) ) {
			$startdate = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(startdate) FROM $wpdb->pmpro_memberships_users WHERE user_id = %d AND membership_id = %d AND status = 'active'", $user_id, $level_id ) );
		} else {
			$startdate = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(startdate) FROM $wpdb->pmpro_memberships_users WHERE user_id = %d AND status = 'active'", $user_id ) );
		}

		// Fall back to the lock creation time if there is no active membership to compare against.
		if ( empty( $startdate ) || '0000-00-00 00:00:00' === $startdate ) {
			$since = gmdate( 'Y-m-d H:i:s', (int)$lock['created'] );
		} else {
			$since = $startdate;
		}

		// The checkout order is created before the membership starts, so include it by ID.
		if ( ! empty( $lock['first_order_id'] ) ) {
			$where[] = '( timestamp >= %s OR id = %d )';
			$values[] = $since;
			$values[] = (int)$lock['first_order_id'];
		} else {
			$where[] = 'timestamp >= %s';
			$values[] = $since;
		}
	}

	$sql = "SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders WHERE " . implode( ' AND ', $where );
	$count = $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

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
 *                               Only payments dated on or after the membership start are counted, plus the checkout order that completed in the same request.
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

	// Payment-based locks only count payments made during the current membership.
	if ( ! empty( $payments_required ) ) {
		$lock_data['created'] = time();
		$lock_data['first_order_id'] = pmprolml_get_checkout_order_id_for_lock( $user_id, $level_id );
	}

	// Check if the user already has a lock for the same level.
	$user_locks = pmprolml_get_all_locks_for_user( $user_id );
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

	// Get all locks for the user, including payment-based locks that are currently satisfied.
	$user_locks = pmprolml_get_all_locks_for_user( $user_id );

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
