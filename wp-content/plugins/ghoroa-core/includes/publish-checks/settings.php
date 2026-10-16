<?php
/**
 * Posts → নিরাময়-দাবির শব্দ: the editable list of cure-claim phrases (§7), one per line.
 * Editors and administrators can change it.
 *
 * @package GhoroaCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php',
			'নিরাময়-দাবির শব্দ',
			'নিরাময়-দাবির শব্দ',
			'edit_others_posts',
			'gn-cure-phrases',
			static function () {
				$phrases = implode( "\n", gn_cure_phrases() );
				?>
				<div class="wrap">
					<h1>নিরাময়-দাবির শব্দ</h1>
					<p>প্রকাশের আগে শিরোনাম, সারাংশ, ডেক আর লেখায় এই শব্দগুলো থাকলে সতর্কবার্তা দেখানো হয় (প্রকাশ আটকায় না)। প্রতি লাইনে একটি।</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="gn_cure_phrases">
						<?php wp_nonce_field( 'gn_cure_phrases' ); ?>
						<textarea name="phrases" rows="12" class="large-text code"><?php echo esc_textarea( $phrases ); ?></textarea>
						<?php submit_button( 'সংরক্ষণ করুন' ); ?>
					</form>
				</div>
				<?php
			}
		);
	}
);

add_action(
	'admin_post_gn_cure_phrases',
	static function () {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'gn_cure_phrases' );
		$lines = preg_split( '/\R/u', (string) wp_unslash( $_POST['phrases'] ?? '' ) );
		$lines = array_values( array_unique( array_filter( array_map( static fn( $l ) => trim( sanitize_text_field( $l ) ), $lines ) ) ) );
		update_option( 'gn_cure_phrases', $lines ?: gn_default_cure_phrases(), false );
		wp_safe_redirect( admin_url( 'edit.php?page=gn-cure-phrases&updated=1' ) );
		exit;
	}
);
