<?php

/*
 * Redirect away from the membership locked page if user isn't locked and
 * redirect to the membership locked page if user is trying to change a locked level.
 */
function pmprolml_template_redirect() {
	global $pmpro_pages, $current_user;
	
	if( empty( $pmpro_pages ) || empty( $current_user->ID ) ) {
		return;
	}
  
	// Redirect away from the membership locked page if user isn't locked.
	if( ! empty( $pmpro_pages['membership_locked'] ) && is_page( $pmpro_pages['membership_locked'] ) && ! pmprolml_is_level_locked_for_user( $current_user->ID, empty( $_REQUEST['pmprolml_locked_level'] ) ? 0 : (int)$_REQUEST['pmprolml_locked_level'] ) ) {
		if( ! empty( $pmpro_pages['account'] ) ) {
			wp_redirect( pmpro_url( 'account' ) );
			exit;
		} else {
			wp_redirect( home_url() );
			exit;
		}
	}

	// Detect if a user is trying to change their level when they can't.
	$locked_level = null;
	if ( is_page( $pmpro_pages['levels'] ) && pmprolml_is_level_locked_for_user( $current_user->ID, 0 ) ) {
		// The user has a lock on all levels. They cannot view the levels page.
		$locked_level = 0;
	}
	if ( is_page( $pmpro_pages['cancel'] ) ) {
		// If the user has a lock on all levels, they cannot cancel any memberships.
		if ( pmprolml_is_level_locked_for_user( $current_user->ID, 0 ) ) {
			$locked_level = 0;
		} else {
			// Get the level IDs they are requesting to cancel from the ?levelstocancel param.
			$levels_to_check = array();
			$user_levels     = pmpro_getMembershipLevelsForUser( $current_user->ID );
			$user_level_ids  = array_map( 'intval', wp_list_pluck( $user_levels, 'ID' ) );
			if ( ! empty( $_REQUEST['levelstocancel'] ) && $_REQUEST['levelstocancel'] === 'all' ) {
				$levels_to_check = $user_level_ids;
			} elseif ( ! empty( $_REQUEST['levelstocancel'] ) ) {		
				// A single ID could be passed, or a few like 1+2+3.
				$requested_ids = array_map( 'intval', explode( '+', $_REQUEST['levelstocancel'] ) );
				$levels_to_check = array_intersect( $requested_ids, $user_level_ids );
			}

			// Check if any of the levels are locked.
			foreach ( $levels_to_check as $level_id ) {
				if ( pmprolml_is_level_locked_for_user( $current_user->ID, $level_id ) ) {
					$locked_level = $level_id;
					break;
				}
			}
		}
	}
	if ( pmpro_is_checkout() ) { // Using pmpro_is_checkout() instead of is_page( $pmpro_pages['checkout'] ) in case there are multiple checkout pages on the site.
		if ( pmprolml_is_level_locked_for_user( $current_user->ID, 0 ) ) {
			$locked_level = 0;
		} else {
			$levels_to_check = array();
			$user_levels     = pmpro_getMembershipLevelsForUser( $current_user->ID );
			$user_level_ids  = array_map( 'intval', wp_list_pluck( $user_levels, 'ID' ) );
			if ( class_exists( 'PMPro_Member_Edit_Panel' ) ) {
				// For 3.0+, check if the user is going to lose any levels in the same group as the level being purchased.
				$checkout_level = pmpro_getLevelAtCheckout();
				$group_id = pmpro_get_group_id_for_level( $checkout_level->id );
				$group    = pmpro_get_level_group( $group_id );
				if ( ! empty( $group ) && empty( $group->allow_multiple_selections ) ) {
					// Loop through the levels and see if any are in the same group as the level being purchased.
					if ( ! empty( $user_levels ) ) {
						foreach ( $user_levels as $level ) {
							// If this is the level that the user is purchasing, continue.
							if ( $level->id == (int)$checkout_level->id ) {
								continue;
							}

							// If this level is not in the same group, continue.
							if ( pmpro_get_group_id_for_level( $level->id ) != $group_id ) {
								continue;
							}

							// If we made it this far, the user is going to lose this level after checkout.
							$levels_to_check[] = (int)$level->id;
						}
					}
				} else {
					// Just check that we are not purchsing a level the user already has.
					if ( in_array( (int)$checkout_level->id, $user_level_ids ) ) {
						$levels_to_check[] = (int)$checkout_level->id;
					}
				}
			} else {
				// For 2.x, just check if the user has all levels locked.
				$levels_to_check = array( 0 );
			}

			// Check if any of the levels are locked.
			foreach ( $levels_to_check as $level_id ) {
				if ( pmprolml_is_level_locked_for_user( $current_user->ID, $level_id ) ) {
					$locked_level = $level_id;
					break;
				}
			}
		}
	}

	// Redirect to the membership locked page if the user is trying to change a locked level.
	if( null !== $locked_level ) {
		if ( ! empty( $pmpro_pages['membership_locked'] ) ) {
			wp_redirect( add_query_arg( 'pmprolml_locked_level', (int)$locked_level, pmpro_url( 'membership_locked' ) ) );
			exit;
		} elseif ( ! empty( $pmpro_pages['account'] ) ) {
			wp_redirect(
				add_query_arg(
					array(
						'pmprolml_redirect'     => '1',
						'pmprolml_locked_level' => (int)$locked_level,
					),
					pmpro_url( 'account' )
				)
			);
			exit;
		} else {
			wp_redirect( home_url() );
			exit;
		}
	}
}
add_action('template_redirect', 'pmprolml_template_redirect');

/**
 * Hide the "Cancel", "Change", and "Renew" links on the account page if the user's membership is locked.
 *
 * @param array $links   Array of action links.
 * @param int   $level_id The ID of the membership level.
 * @return array
 */
function pmprolml_hide_account_page_action_links( $links, $level_id ) {
	global $current_user;
	if ( pmprolml_is_level_locked_for_user( $current_user->ID, $level_id ) ) {
		unset( $links['cancel'] );
		unset( $links['change'] );
		unset( $links['renew'] );
		?>
		<style>
			#pmpro_actionlink-levels {
				display: none;
			}
		</style>
		<?php
	}
	return $links;
}
add_filter( 'pmpro_member_action_links', 'pmprolml_hide_account_page_action_links', 10, 2 );

/**
 * Add the [pmpro_membership_locked] shortcode.
 *
 * @param array       $atts          Array of shortcode attributes.
 * @param string|null $content       Shortcode contents.
 * @param string      $shortcode_tag Shortcode tag.
 */
function pmpro_shortcode_membership_locked($atts, $content=null, $shortcode_tag="") {
	global $current_user;

	extract(shortcode_atts(array(
		'message'      => '', // Message to display. If empty, will generate a message.
		'account_link' => '1' // Whether to show a link to the account page.
	), $atts));

	// Get the message.
	if ( empty( $message ) ) {
		$locked_level = empty( $_REQUEST['pmprolml_locked_level'] ) ? '' : intval( $_REQUEST['pmprolml_locked_level'] );
		if ( ! empty( $locked_level ) && ! empty( $current_user->ID ) && pmprolml_is_level_locked_for_user( $current_user->ID, $locked_level ) ) {
			$level = pmpro_getLevel( $locked_level );
			$message = sprintf( esc_html__( 'Your %s membership level is locked. You are not allowed to change that membership level.', 'pmpro-lock-membership-level' ), $level->name );
		} else {
			$message = esc_html__( 'Your membership levels are locked. You are not allowed to change your membership levels.', 'pmpro-lock-membership-level' );
		}
	}
	$r = '<div class="pmpro_message pmpro_error">' . $message . '</div>';

	// Show a link to the account page if needed.
	if ( $current_user->membership_level->ID && ! empty( $account_link ) ) {
		$r .= '<p><a href="' . pmpro_url("account") . '"> &larr; ' . esc_html__("Return to Your Account", "pmpro-lock-membership-level") . '</a></p>';
	}

	return $r;
}
add_shortcode("pmpro_membership_locked", "pmpro_shortcode_membership_locked");

/**
 * Show a message on the account page if the user was redirected here because of a locked membership.
 *
 * @param string $content The content of the page.
 */
function pmprolml_show_account_page_error( $content ) {
	global $pmpro_pages;

	// Check that we are on the PMPro Account page.
	if ( empty( $pmpro_pages ) || empty( $pmpro_pages['account'] ) || ! is_page( $pmpro_pages['account'] ) ) {
		return $content;
	}

	if ( isset( $_REQUEST['pmprolml_redirect'] ) ) {
		// User has locked membership and was redirected here.
		echo pmpro_shortcode_membership_locked( array(
			'account_link' => '0',
		) );
	}
		
	return $content;
}
add_filter( 'the_content', 'pmprolml_show_account_page_error' );