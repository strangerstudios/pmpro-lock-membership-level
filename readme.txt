=== Paid Memberships Pro - Lock Membership Level Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, restrict, lock, block, admin
Requires at least: 4.0
Tested up to: 4.6.1
Stable tag: .1.1

Lock membership level changes for specific users or by level.

== Description ==

This add on allows you to lock a specific user from making changes to their membership level (including selecting a new level if they currently do not have a level). This may be useful for problem members that repeatedly purchase and cancel membership.

Additionally, you can use this add on to lock a membership level for a specific term.  This is useful if you want to restrict members from canceling membership until a required number of installment payments are made.

== Installation ==

1. Make sure you have Paid Memberships Pro installed and activated.
1. Upload the `pmpro-lock-membership-level` directory to the `/wp-content/plugins/` directory of your site.
1. Add a new page for the "Membership Locked" message and assign it under Memberships > Page settings. This is the page that members will be redirected to when attempting to cancel or change membership.
1. Add the shortcode [pmpro_membership_locked] to that page. The shortcode's only attribute "message" can be used to customize the default message shown.

== How to Lock a Member ==
1. Locate the member in the WordPress Users dashboard.
1. Edit the user profile.
1. Under "Lock Membership" check the box to restrict the member from changing their membership level.
1. To view a list of all locked members, navigate to Memberships > Locked Members in the WordPress admin.

Note that a locked member will still "expire" if there is an expiration date set on their membership account. The member will change to "no level" or a downgrade level you have specified via custom code upon expiration.

== How to Lock a Membership Level for a Specific Term ==
1. Edit the membership level on the Memberships > Membership Levels > Edit Membership Level page.
1. Under "Lock Membership Level Settings", check the box to lock the membership level.
1. The level can be set to unlock "Never" or after a specified time period (X days, weeks, months, or years).

== How to View Locked Members ==
1. Navigate to the Memberships > Members List page in the WordPress admin.
1. Select "Locked" from the "Show" dropdown.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the GitHub issue tracker here: https://github.com/strangerstudios/pmpro-lock-membership-level/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==

= .1.1 =
* BUG: Switched get_usermeta and update_usermeta calls to get_user_meta and update_user_meta.

= .1 =
* Initial version.
