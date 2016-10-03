=== Paid Memberships Pro - Lock Membership Level Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, pmpro, restrict, lock, block, admin
Requires at least: 4.0
Tested up to: 4.6.1
Stable tag: .1

Lock membership level changes for specific users.

== Description ==

This add on allows you to specify members that cannot make changes to their membership level. This may be useful for problem members that repeatedly purchase and cancel membership. Additionally, you can use this plugin to lock members who cannot cancel membership until a required number of installment payments are made. 

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

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the GitHub issue tracker here: https://github.com/strangerstudios/pmpro-lock-membership0level/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at http://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==

= .1 =
* Initial version.
