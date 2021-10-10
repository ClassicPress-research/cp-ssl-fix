<?php
/**
 * Plugin Name:  ClassicPress SSL Fix
 * Plugin URI:   https://github.com/ClassicPress-research/cp-ssl-fix
 * Description:  Securely fix "SSL certificate problem: certificate has expired" error.
 * Version:      0.1.0
 * Author:       Simone Fioravanti, James Nylen
 * License:      GPL-2.0+
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:  cp-ssl-fix
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if called directly or if not running ClassicPress/WordPress.
	return;
}

$cp_ssl_fix_insecure = get_transient( 'cp_ssl_fix_insecure' );

/**
 * Attempt to fix SSL requests securely by overriding the certificate bundle.
 *
 * Depending on the server configuration in use, this may not be a complete
 * fix.  See the plugin's README.md file for more information.
 *
 * @param array  $r   An array of HTTP request arguments.
 * @param string $url The request URL.
 */
function cp_ssl_fix_process_request( $r, $url ) {
	global $cp_ssl_fix_insecure;
	if ( $r['sslcertificates'] === ABSPATH . WPINC . '/certificates/ca-bundle.crt' ) {
		$r['sslcertificates'] = __DIR__ . '/ca-bundle.crt';
	}
	if ( $cp_ssl_fix_insecure && empty( $r['_no_insecure'] ) ) {
		$r['sslverify'] = false;
	}
	return $r;
}

/**
 * Determine whether the plugin should try to override the certificate bundle.
 */
function cp_ssl_fix_should_override_ca( $details = false ) {
	if ( ! function_exists( 'curl_version' ) ) {
		return $details ? 'csf_no_curl' : false;
	}
	$v = curl_version();
	if ( substr( $v['ssl_version'], 0, 8 ) !== 'OpenSSL/' ) {
		return $details ? 'csf_not_openssl' : false;
	}
	$openssl_version = substr( $v['ssl_version'], 8 );
	if ( version_compare( $openssl_version, '1.0.2', 'ge' ) ) {
		return $details ? 'csf_new_openssl' : false;
	}
	return true;
}

/**
 * Register the plugin's admin page under the Tools menu.
 */
function cp_ssl_fix_register_admin_page() {
	add_management_page(
		__( 'CP SSL Fix', 'cp-ssl-fix' ),
		__( 'CP SSL Fix', 'cp-ssl-fix' ),
		'read',
		'cp-ssl-fix',
		'cp_ssl_fix_show_admin_page'
	);
}

/**
 * Apply the SSL fix if needed.
 */
function cp_ssl_fix_apply( $apply = true ) {
	remove_filter( 'http_request_args', 'cp_ssl_fix_process_request' );
	if ( ! $apply ) {
		return;
	}
	if ( cp_ssl_fix_should_override_ca() ) {
		add_filter( 'http_request_args', 'cp_ssl_fix_process_request', 10, 2 );
	}
}

/**
 * Output the plugin's styles for the relevant admin pages.
 *
 * @since 0.1.0
 */
function cp_ssl_fix_print_admin_styles() {
?>
<style>
.cp-ssl-emphasis {
	font-weight: bold;
	color: #800;
}
table#cp-ssl-fix-checks {
	margin: 1.5em 0 2em;
	border-spacing: 0;
}
#cp-ssl-fix-checks p {
	margin: 0;
}
#cp-ssl-fix-checks td {
	padding: 0.5em 0 0.5em 1em;
	margin: 0;
}
#cp-ssl-fix-checks td + td {
	padding-right: 0;
}
#cp-ssl-fix-checks tr + tr td {
	border-top: 1px solid #ccc;
}
.cp-ssl-fix-icon {
	font-size: 250%;
	font-weight: bold;
	border-radius: 0.5em;
	color: #f1f1f1; /* default wp-admin background */
	display: block;
	width: 1em;
	height: 1em;
}
.cp-ssl-fix-icon .dashicons {
	font-size: 1em;
	display: block;
	width: 1em;
	height: 1em;
	position: relative;
}
.cp-ssl-fix-icon.cp-pass {
	background: #080;
}
.cp-ssl-fix-icon.cp-pass .dashicons-yes {
	left: -0.025em;
	top: 0.030em;
}
.cp-ssl-fix-icon.cp-fail {
	background: #800;
}
.cp-ssl-fix-icon.cp-fail .dashicons-no {
	left: 0.005em;
	top: 0.010em;
}
.cp-ssl-fix-icon.cp-warn {
	background: #ffb900;
}
.cp-ssl-fix-icon.cp-warn .dashicons-flag {
	font-size: 0.8em;
	left: 0.140em;
	top: 0.100em;
}
</style>
<?php
}

/**
 * Show the plugin's admin page.
 */
function cp_ssl_fix_show_admin_page() {
	global $cp_ssl_fix_insecure;

	echo '<div class="wrap">';
	echo '<h1>';
	esc_html_e( 'ClassicPress SSL Fix', 'cp-ssl-fix' );
	echo '</h1>';
	echo '<p><a target="_blank" rel="noopener noreferrer" href="https://github.com/ClassicPress-research/cp-ssl-fix">';
	esc_html_e( 'Read more about this plugin &#187;' );
	echo '</a></p>';

	$curl_status = cp_ssl_fix_should_override_ca( true );
	$errors = [
		'csf_no_curl'     => __( 'The cURL extension for PHP is not present.', 'cp-ssl-fix' ),
		'csf_not_openssl' => __( 'The cURL extension for PHP is not using OpenSSL.', 'cp-ssl-fix' ),
	];
	if ( isset( $errors[ $curl_status ] ) ) {
		echo '<p><strong>';
		esc_html_e( 'This plugin cannot operate on your site:', 'cp-ssl-fix' );
		echo '</strong></p>';
		echo '<p>';
		echo esc_html( $errors[ $curl_status ] );
		echo '</p>';
		if ( $curl_status === 'csf_not_openssl' ) {
			cp_ssl_fix_show_curl_ssl_version();
		}
		return;
	}

	if (
		isset( $_GET['action'] ) &&
		$_GET['action'] === 'enable-insecure' &&
		wp_verify_nonce( $_GET['_wpnonce'], 'cp-ssl-insecure' )
	) {
		echo '<script>window.history.replaceState( null, null, location.href.split("&")[0] );</script>';
		set_transient( 'cp_ssl_fix_insecure', true, 60 * 3 );
		// Make it easier to test in ClassicPress by deleting the cached data
		// for the petitions widget.
		delete_transient( 'dash_v2_bf927a36d7e5199f965b7762dceee968' );
	}

	$ssl_checks = [];
	echo '<table id="cp-ssl-fix-checks">' . "\n";

	// Check: Unmodified request
	cp_ssl_fix_apply( false );
	$ssl_checks['unmodified'] = cp_ssl_fix_request();
	cp_ssl_fix_apply();
	cp_ssl_fix_show_check(
		$ssl_checks['unmodified'],
		__( 'Your site is able to make external requests to <code>api-v1.classicpress.net</code> without this plugin changing anything.', 'cp-ssl-fix' ),
		__( 'Your site is <strong>not</strong> able to make external requests to <code>api-v1.classicpress.net</code> without this plugin changing anything.', 'cp-ssl-fix' )
	);

	// Check: Overridden CA
	// It's possible for cp_ssl_fix_should_override_ca() to return true even
	// though the unmodified request succeeds - apparently there are some
	// (patched?) versions of OpenSSL 1.0.2 that don't have this problem.  It's
	// also possible for the request to fail on sites that should have working
	// SSL, if there is a network issue or something else preventing access to
	// the test server.
	if ( ! $ssl_checks['unmodified'] ) {
		$ssl_checks['overridden'] = cp_ssl_fix_request( [ '_no_insecure' => true ] );
		cp_ssl_fix_show_check(
			$ssl_checks['overridden'],
			__( 'Your site is able to make external requests to <code>api-v1.classicpress.net</code> when this plugin overrides the certificates in use.', 'cp-ssl-fix' ),
			__( 'Your site is <strong>not</strong> able to make external requests to <code>api-v1.classicpress.net</code> when this plugin overrides the certificates in use.', 'cp-ssl-fix' )
		);
	} else {
		$ssl_checks['overridden'] = true;
	}

	// Check: Insecure request
	if ( ! $ssl_checks['unmodified'] && ! $ssl_checks['overridden'] ) {
		$ssl_checks['insecure'] = cp_ssl_fix_request( [ 'sslverify' => false ] );
		cp_ssl_fix_show_check(
			$ssl_checks['insecure'],
			__( 'Your site is able to make <strong>insecure</strong> external requests to <code>api-v1.classicpress.net</code> when this plugin disables certificate verification.', 'cp-ssl-fix' ),
			__( 'Your site is <strong>not</strong> able to make insecure external requests to <code>api-v1.classicpress.net</code> when this plugin disables certificate verification.', 'cp-ssl-fix' ),
			true
		);
	} else {
		$ssl_checks['insecure'] = true;
	}

	echo '</table><!-- #cp-ssl-fix-checks -->';

	echo '<h2>';
	esc_html_e( 'Recommendations', 'cp-ssl-fix' );
	echo '</h2>';

	if ( $ssl_checks['unmodified'] ) {
		echo '<p><strong>';
		esc_html_e( 'Everything looks fine.', 'cp-ssl-fix' );
		echo '</strong></p>';
		echo '<p>';
		esc_html_e( 'You can disable and delete this plugin.', 'cp-ssl-fix' );
		echo '</p>';

	} else if ( $ssl_checks['overridden'] || $ssl_checks['insecure'] ) {
		echo '<p><strong class="cp-ssl-emphasis">';
		esc_html_e( 'Upgrade your web server software.', 'cp-ssl-fix' );
		echo '</strong></p>';
		echo '<p>';
		esc_html_e( 'Contact your web host. Ask them to remove the expired "DST Root CA X3" certificate from your system and upgrade your PHP and cURL versions.', 'cp-ssl-fix' );
		echo '</p>';
		$package = function_exists( 'classicpress_version' ) ? 'ClassicPress' : 'WordPress';
		echo '<p>';
		echo esc_html( sprintf(
			__( 'Also, make sure you have updated to the latest version of %s.', 'cp-ssl-fix' ),
			$package
		) );
		echo '</p>';

		if ( $ssl_checks['overridden'] ) {
			echo '<p>';
			esc_html_e( 'In the meantime, leave this plugin active to keep your site working correctly.', 'cp-ssl-fix' );
			echo '</p>';

		} else if ( $cp_ssl_fix_insecure ) {
			$expiration = max( time(), get_option( '_transient_timeout_cp_ssl_fix_insecure' ) );
			echo '<p><strong class="cp-ssl-emphasis">';
			echo esc_html( sprintf(
				__( 'This plugin has disabled certificate verification for the next %s.', 'cp-ssl-fix' ),
				human_time_diff( $expiration )
			) );
			echo '</strong></p>';

		} else {
			echo '<p>';
			_e( 'In the meantime, you can click this button to enable <strong class="cp-ssl-emphasis">insecure requests</strong> for up to 3 minutes:', 'cp-ssl-fix' );
			echo '</p>';
?>
	<form method="get" action="tools.php?page=cp-ssl-fix&action=enable-insecure">
		<input type="hidden" name="page" value="cp-ssl-fix">
		<input type="hidden" name="action" value="enable-insecure">
		<?php wp_nonce_field( 'cp-ssl-insecure' ); ?>
		<button class="button button-primary"><?php esc_html_e( 'Enable Insecure Requests', 'cp-ssl-fix' ); ?></button>
	</form>
<?php
		}

	} else { // all checks failed!
		echo '<p><strong class="cp-ssl-emphasis">';
		esc_html_e( 'Something else went wrong.', 'cp-ssl-fix' );
		echo '</strong></p>';
		echo '<p>';
		esc_html_e( 'This plugin was not able to determine the status of your site.', 'cp-ssl-fix' );
		echo '</p><p>';
		// Not being able to talk to the CP API server is only likely to be a
		// problem for CP sites.
		if ( function_exists( 'classicpress_version' ) ) {
			esc_html_e( 'You may need to contact your web host and ask them to enable outgoing connections to <code>api-v1.classicpress.net</code>.', 'cp-ssl-fix' );
		} else {
			esc_html_e( 'If everything on your site is working correctly then this is probably not a big problem.', 'cp-ssl-fix' );
		}
		echo '</p>';
	}

	cp_ssl_fix_show_curl_ssl_version();

?>
</div><!-- .wrap -->
<?php
}

/**
 * Show the SSL version reported by cURL.
 */
function cp_ssl_fix_show_curl_ssl_version() {
	$curl_version = curl_version();
	echo '<p><em>';
	printf(
		__( 'SSL version reported by cURL: <strong>%s</strong>', 'cp-ssl-fix' ),
		$curl_version['ssl_version']
	);
	echo '</em></p>';
}

/**
 * Perform a test SSL request.
 */
function cp_ssl_fix_request( $args = [] ) {
	$result = wp_remote_get( 'https://api-v1.classicpress.net/?cp-ssl-fix', $args );
	return ( wp_remote_retrieve_response_code( $result ) === 200 );
}

/**
 * Show the result of an SSL check.
 */
function cp_ssl_fix_show_check( $result, $msg_pass, $msg_fail, $is_warning = false ) {
	$icon_ssl_pass = (
		'<div class="cp-ssl-fix-icon cp-pass">'
		. '<div class="dashicons dashicons-yes"></div>'
		. '</div>'
	);
	$icon_ssl_warn = (
		'<div class="cp-ssl-fix-icon cp-warn">'
		. '<div class="dashicons dashicons-flag"></div>'
		. '</div>'
	);
	$icon_ssl_fail = (
		'<div class="cp-ssl-fix-icon cp-fail">'
		. '<div class="dashicons dashicons-no"></div>'
		. '</div>'
	);

	echo '<tr><td>';
	if ( $result ) {
		echo $is_warning ? $icon_ssl_warn : $icon_ssl_pass;
	} else {
		echo $icon_ssl_fail;
	}
	echo '</td><td><p>';
	if ( $result ) {
		echo $msg_pass;
	} else {
		echo $msg_fail;
	}
	echo '</p></td></tr>';
}

// Main plugin code.
add_action( 'admin_menu', 'cp_ssl_fix_register_admin_page' );
add_action( 'admin_head-tools_page_cp-ssl-fix', 'cp_ssl_fix_print_admin_styles' );
cp_ssl_fix_apply();
