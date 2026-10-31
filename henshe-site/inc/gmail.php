<?php
/**
 * Gmail sending: all site email (contact form, password resets, update notices) goes out
 * through the site's Gmail account. Replaces a separate SMTP plugin.
 *
 * Set up under Settings > Henshe Site > Email: the Gmail address and an app password
 * (Google Account > Security > 2-Step Verification > App passwords).
 * The app password is encrypted before it is saved, with a key made from the secret keys in
 * wp-config.php, so a database copy or backup on its own can't reveal it. It is never shown again.
 * While another SMTP plugin is active, or nothing is saved, this file changes nothing.
 */

defined( 'ABSPATH' ) || exit;

function hs_gmail_key() {
	return hash( 'sha256', wp_salt( 'auth' ) . '|henshe-gmail', true );
}

function hs_gmail_encrypt( $plain ) {
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, hs_gmail_key() ) );
}

function hs_gmail_decrypt( $stored ) {
	$raw = base64_decode( (string) $stored, true );
	if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return false;
	}
	$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, hs_gmail_key() );
}

/* The saved address and password: array( address, password ), or null when not set up
   (or when the password can't be read, e.g. the wp-config.php keys were changed). */
function hs_gmail_account() {
	$opt  = get_option( 'hs_gmail', array() );
	$pass = empty( $opt['pass'] ) ? false : hs_gmail_decrypt( $opt['pass'] );
	return ( ! empty( $opt['user'] ) && false !== $pass ) ? array( $opt['user'], $pass ) : null;
}

/* Another mail plugin that would also send the email (it wins; we stay out of its way) */
function hs_gmail_other_plugin() {
	if ( function_exists( 'wp_mail_smtp' ) ) {
		return 'WP Mail SMTP';
	}
	if ( defined( 'FLUENTMAIL' ) ) {
		return 'FluentSMTP';
	}
	if ( class_exists( 'PostmanOptions' ) ) {
		return 'Post SMTP';
	}
	return '';
}

function hs_gmail_on() {
	return null !== hs_gmail_account() && '' === hs_gmail_other_plugin();
}

add_action( 'phpmailer_init', function ( $mail ) {
	if ( ! hs_gmail_on() ) {
		return;
	}
	list( $user, $pass ) = hs_gmail_account();
	$mail->isSMTP();
	$mail->Host       = 'smtp.gmail.com';
	$mail->Port       = 465;
	$mail->SMTPSecure = 'ssl';
	$mail->SMTPAuth   = true;
	$mail->Username   = $user;
	$mail->Password   = $pass;
	$mail->Timeout    = 15;

	// Gmail only sends from its own address, so use it. Keep the sender's name
	// (WordPress's default "WordPress" becomes the site title). Replies still go to Reply-To.
	$name = $mail->FromName;
	if ( '' === $name || 'WordPress' === $name ) {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}
	$mail->setFrom( $user, $name, false );
	$mail->Sender = $user;
}, 20 );

/* Remember how the last email went, to show on the settings page */
add_action( 'wp_mail_succeeded', function () {
	if ( hs_gmail_on() ) {
		update_option( 'hs_gmail_last', array( 'ok' => true, 'time' => time(), 'error' => '' ), false );
	}
} );
add_action( 'wp_mail_failed', function ( $error ) {
	if ( hs_gmail_on() ) {
		update_option( 'hs_gmail_last', array( 'ok' => false, 'time' => time(), 'error' => $error->get_error_message() ), false );
	}
} );

/* Send a test email to the site's admin address. Returns '' when sent, or the error. */
function hs_gmail_send_test() {
	$error = '';
	$catch = function ( $e ) use ( &$error ) {
		$error = $e->get_error_message();
	};
	add_action( 'wp_mail_failed', $catch );
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$via  = hs_gmail_on() ? 'Gmail (' . hs_gmail_account()[0] . ')' : ( hs_gmail_other_plugin() ?: 'the server' );
	$sent = wp_mail(
		get_option( 'admin_email' ),
		'Test email from ' . $site,
		"If you can read this, the website can send email.\n\nSent through " . $via . ' on ' . wp_date( 'j F Y, H:i' ) . '.'
	);
	remove_action( 'wp_mail_failed', $catch );
	if ( $sent ) {
		return '';
	}
	if ( hs_gmail_on() && false !== stripos( $error, 'authenticate' ) ) {
		$error .= ' Gmail did not accept the address or app password. Check both, or make a new app password.';
	}
	return $error ?: 'The email could not be sent.';
}

/* Settings > Henshe Site > Email */
add_action( 'henshe_site_settings_after', function () {
	$opt     = get_option( 'hs_gmail', array() );
	$saved   = ! empty( $opt['pass'] );
	$other   = hs_gmail_other_plugin();
	$last    = get_option( 'hs_gmail_last' );
	$notice  = get_transient( 'hs_gmail_notice' );
	$post_to = esc_url( admin_url( 'admin-post.php' ) );
	if ( $notice ) {
		delete_transient( 'hs_gmail_notice' );
	}
	?>
	<hr>
	<h2 id="email">Email</h2>
	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> inline"><p><?php echo esc_html( $notice[1] ); ?></p></div>
	<?php endif; ?>
	<p>
		<?php if ( hs_gmail_on() ) : ?>
			<strong style="color:#008a20">On.</strong> Site email goes out through Gmail.
			<?php if ( $last ) : ?>
				Last email: <?php echo $last['ok'] ? 'sent' : '<strong style="color:#d63638">failed</strong> (' . esc_html( $last['error'] ) . ')'; ?>, <?php echo esc_html( human_time_diff( $last['time'] ) ); ?> ago.
			<?php endif; ?>
		<?php elseif ( $saved && null === hs_gmail_account() ) : ?>
			<strong style="color:#d63638">The saved app password can't be read any more</strong> (the site's secret keys changed). Paste it again.
		<?php elseif ( $saved ) : ?>
			<strong>Ready.</strong> <?php echo esc_html( $other ); ?> is still active and sends the site's email. Deactivate it to switch to Gmail, then send a test.
		<?php else : ?>
			Send the site's email through Gmail. Needs a Google app password (Google Account &gt; Security &gt; 2-Step Verification &gt; App passwords).
		<?php endif; ?>
	</p>
	<form method="post" action="<?php echo $post_to; // phpcs:ignore -- escaped above ?>">
		<input type="hidden" name="action" value="hs_gmail_save">
		<?php wp_nonce_field( 'hs_gmail_save' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="hs-gmail-user">Gmail address</label></th>
				<td><input type="email" id="hs-gmail-user" name="user" class="regular-text" value="<?php echo esc_attr( $opt['user'] ?? '' ); ?>" required></td>
			</tr>
			<tr>
				<th scope="row"><label for="hs-gmail-pass">App password</label></th>
				<td>
					<input type="password" id="hs-gmail-pass" name="pass" class="regular-text" autocomplete="new-password"
						placeholder="<?php echo $saved ? 'Saved (paste a new one to replace it)' : 'abcd efgh ijkl mnop'; ?>" <?php echo $saved ? '' : 'required'; ?>>
					<?php if ( $saved ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hs_gmail_remove' ), 'hs_gmail_remove' ) ); ?>" style="color:#d63638;margin-left:8px" onclick="return confirm('Remove the saved Gmail details? Email then goes back to being sent the normal way.')">Remove</a>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php submit_button( 'Save email settings', 'primary', 'submit', false ); ?>
	</form>
	<form method="post" action="<?php echo $post_to; // phpcs:ignore -- escaped above ?>" style="margin-top:12px">
		<input type="hidden" name="action" value="hs_gmail_test">
		<?php wp_nonce_field( 'hs_gmail_test' ); ?>
		<?php submit_button( 'Send test email', 'secondary', 'submit', false ); ?>
		<span class="description">Goes to <?php echo esc_html( get_option( 'admin_email' ) ); ?>.</span>
	</form>
	<?php
} );

function hs_gmail_back( $type, $message ) {
	set_transient( 'hs_gmail_notice', array( $type, $message ), 120 );
	wp_safe_redirect( admin_url( 'options-general.php?page=henshe-site#email' ) );
	exit;
}

add_action( 'admin_post_hs_gmail_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'hs_gmail_save' );
	$opt  = get_option( 'hs_gmail', array() );
	$user = sanitize_email( wp_unslash( $_POST['user'] ?? '' ) );
	$pass = preg_replace( '/\s+/', '', (string) wp_unslash( $_POST['pass'] ?? '' ) ); // Google shows it with spaces
	if ( ! is_email( $user ) ) {
		hs_gmail_back( 'error', 'That Gmail address doesn\'t look right.' );
	}
	if ( '' === $pass && empty( $opt['pass'] ) ) {
		hs_gmail_back( 'error', 'Paste the app password.' );
	}
	$opt['user'] = $user;
	if ( '' !== $pass ) {
		$opt['pass'] = hs_gmail_encrypt( $pass );
	}
	update_option( 'hs_gmail', $opt, false );
	delete_option( 'hs_gmail_last' );
	hs_gmail_back( 'success', 'Saved. Now send a test email.' );
} );

add_action( 'admin_post_hs_gmail_remove', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'hs_gmail_remove' );
	delete_option( 'hs_gmail' );
	delete_option( 'hs_gmail_last' );
	hs_gmail_back( 'success', 'Gmail details removed.' );
} );

add_action( 'admin_post_hs_gmail_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'hs_gmail_test' );
	$error = hs_gmail_send_test();
	hs_gmail_back( $error ? 'error' : 'success', $error ? 'Test email failed: ' . $error : 'Test email sent to ' . get_option( 'admin_email' ) . '. Check the inbox (and Spam).' );
} );

/* POST /wp-json/henshe/v1/test-email (administrators): same test, for checking remotely */
add_action( 'rest_api_init', function () {
	register_rest_route( 'henshe/v1', '/test-email', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => function () {
			$error = hs_gmail_send_test();
			return array(
				'sent'  => '' === $error,
				'error' => $error,
				'via'   => hs_gmail_on() ? 'gmail' : ( hs_gmail_other_plugin() ?: 'server' ),
				'to'    => get_option( 'admin_email' ),
			);
		},
	) );
} );
