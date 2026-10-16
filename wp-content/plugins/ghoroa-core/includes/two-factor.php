<?php
/**
 * Two-factor login (spec §3, §8), with the Two Factor plugin.
 *
 * - Authenticator-app (TOTP) codes only, plus one-time backup codes for a lost phone. Email codes
 *   are off: v1 may have no email, and they're weaker.
 * - Required for administrators and editors when GN_REQUIRE_2FA is true (production wp-config).
 *   Until they set it up, the admin only lets them reach their profile to do so. Local development
 *   leaves the constant unset.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'two_factor_providers',
	static fn( array $providers ): array => array_intersect_key( $providers, array_flip( array( 'Two_Factor_Totp', 'Two_Factor_Backup_Codes' ) ) )
);

/**
 * True when this user must have two-factor login but hasn't set it up.
 */
function gn_user_needs_2fa( WP_User $user ): bool {
	if ( ! defined( 'GN_REQUIRE_2FA' ) || ! GN_REQUIRE_2FA || ! class_exists( 'Two_Factor_Core' ) ) {
		return false;
	}
	$required = array_intersect( (array) $user->roles, array( 'administrator', 'editor' ) );
	return $required && ! Two_Factor_Core::is_user_using_two_factor( $user->ID );
}

add_action(
	'admin_init',
	static function () {
		global $pagenow;
		if ( wp_doing_ajax() || in_array( $pagenow, array( 'profile.php', 'admin-post.php' ), true ) || ! gn_user_needs_2fa( wp_get_current_user() ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'profile.php#two-factor-options' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( gn_user_needs_2fa( wp_get_current_user() ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( 'চালিয়ে যেতে টু-ফ্যাক্টর লগইন চালু করুন: নিচে “Two-Factor Options”-এ Authenticator App বেছে নিয়ে কোডটি স্ক্যান করুন, তারপর Backup Codes তৈরি করে রাখুন।' ) . '</p></div>';
		}
	}
);
