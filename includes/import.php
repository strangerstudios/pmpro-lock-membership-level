<?php

/**
 * Register mapping fields so locked member data can be assigned to a CSV
 * column when using the Import Users from CSV add on.
 *
 * @since 1.1
 *
 * @param array $fields Existing mapping fields, grouped by add on.
 * @return array
 */
function pmprolml_pmproiucsv_mapping_fields( $fields ) {
	$fields['pmprolml'] = array(
		'label'  => __( 'Lock Membership Level', 'pmpro-lock-membership-level' ),
		'fields' => array(
			'pmprolml_lockedmember'             => __( 'Locked Member', 'pmpro-lock-membership-level' ),
			'pmprolml_lockedmember_expiration'  => __( 'Locked Member Expiration', 'pmpro-lock-membership-level' ),
		),
	);

	return $fields;
}
add_filter( 'pmproiucsv_mapping_fields', 'pmprolml_pmproiucsv_mapping_fields' );

/**
 * Register CSV column header aliases so the columns exported by this add on
 * are auto-detected on the mapping screen.
 *
 * @since 1.1
 *
 * @param array $aliases Existing header-to-field-key aliases.
 * @return array
 */
function pmprolml_pmproiucsv_field_aliases( $aliases ) {
	$aliases['lockedmember'] = 'pmprolml_lockedmember';
	$aliases['lockedmemberexpiration'] = 'pmprolml_lockedmember_expiration';

	return $aliases;
}
add_filter( 'pmproiucsv_field_aliases', 'pmprolml_pmproiucsv_field_aliases' );

/**
 * Lock the imported membership level for the user when using the Import
 * Users from CSV add on.
 *
 * @since 1.1
 *
 * @param WP_User $user The user object that was imported.
 * @param int $membership_id The membership level ID that was imported.
 * @param MemberOrder|null $order The order object created during import.
 */
function pmprolml_pmproiucsv_after_member_import( $user, $membership_id, $order ) {
	// Nothing to lock if the row wasn't marked as locked.
	if ( empty( $user->pmprolml_lockedmember ) ) {
		return;
	}

	// Nothing to lock if we don't know which level was imported.
	if ( ! isset( $membership_id ) || '' === $membership_id ) {
		return;
	}

	$expiration = 0;
	if ( ! empty( $user->pmprolml_lockedmember_expiration ) ) {
		// The CSV value is in the site's local time, matching the export format. Convert to a GMT timestamp for storage.
		$expiration = (int)strtotime( get_gmt_from_date( $user->pmprolml_lockedmember_expiration ) );
	}

	pmprolml_add_lock_for_user( $user->ID, (int)$membership_id, $expiration );
}
add_action( 'pmproiucsv_after_member_import', 'pmprolml_pmproiucsv_after_member_import', 10, 3 );
