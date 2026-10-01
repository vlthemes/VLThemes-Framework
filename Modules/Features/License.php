<?php

namespace VLT\Framework\Modules\Features;

use VLT\Framework\BaseModule;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License Module
 *
 * Theme activation with a ThemeForest purchase code or a Gumroad license key (Appearance → Theme License),
 * daily license check and theme updates from the VLThemes license server.
 * Without activation the theme works as usual: only an admin notice, and updates come without a package.
 *
 * Config (vlt_fw_config):
 *     $config['modules']['license'] = true;
 *     $config['license'] = [ 'item' => 'miblerr', 'server' => 'https://vlthemes.me/wp-json/vlthemes/v1/' ];
 */
class License extends BaseModule {
	const OPTION = 'vlt_fw_license';
	const CRON   = 'vlt_fw_license_check';
	const PAGE   = 'vlt-license';
	const UPDATE = 'vlt_fw_license_update';

	protected $name = 'license';

	/**
	 * Register module
	 */
	public function register() {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_post_vlt_fw_license', [ $this, 'handle' ] );
		add_action( 'admin_notices', [ $this, 'notice' ] );
		add_action( self::CRON, [ $this, 'check' ] );
		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'update' ] );
	}

	/**
	 * Initialize module
	 */
	public function init() {
		if ( self::get()['key'] && !wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Stored license: [ key, status, product, supported_until, checked ]
	 */
	public static function get() {
		return wp_parse_args( (array) get_option( self::OPTION, [] ), [ 'key' => '', 'status' => '', 'product' => '', 'supported_until' => '', 'checked' => 0 ] );
	}

	/**
	 * Is the theme activated on this site
	 */
	public static function is_active() {
		return 'active' === self::get()['status'];
	}

	/**
	 * Theme slug on the license server
	 */
	private function item() {
		return (string) $this->get_config( 'license.item', get_template() );
	}

	/**
	 * This site's domain
	 */
	private function domain() {
		return (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Request to the license server → decoded answer or WP_Error (server message when there is one)
	 */
	private function request( $route, $key, $method = 'POST' ) {
		$server = trailingslashit( (string) $this->get_config( 'license.server', 'https://vlthemes.me/wp-json/vlthemes/v1/' ) );
		$args   = [ 'key' => $key, 'domain' => $this->domain(), 'item' => $this->item() ];

		$response = 'GET' === $method
			? wp_remote_get( add_query_arg( array_map( 'rawurlencode', $args ), $server . $route ), [ 'timeout' => 15 ] )
			: wp_remote_post( $server . $route, [ 'timeout' => 15, 'body' => $args ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || !is_array( $body ) ) {
			return new \WP_Error( is_array( $body ) && !empty( $body['code'] ) ? $body['code'] : 'vlt_fw_license_server', is_array( $body ) && !empty( $body['message'] ) ? $body['message'] : esc_html__( 'The license server can\'t be reached. Please try again later.', '@@textdomain' ), [ 'status' => $code ] );
		}

		return $body;
	}

	/**
	 * Save the server answer
	 */
	private function save( $key, $body ) {
		update_option(
			self::OPTION,
			[
				'key'             => $key,
				'status'          => (string) ( $body['status'] ?? '' ),
				'product'         => (string) ( $body['product'] ?? '' ),
				'supported_until' => (string) ( $body['supported_until'] ?? '' ),
				'checked'         => time()
			],
			false
		);

		// Updates: ask again with the new license
		delete_transient( self::UPDATE );
		delete_site_transient( 'update_themes' );
	}

	/**
	 * Appearance → Theme License
	 */
	public function add_page() {
		add_theme_page( esc_html__( 'Theme License', '@@textdomain' ), esc_html__( 'Theme License', '@@textdomain' ), 'manage_options', self::PAGE, [ $this, 'render' ] );
	}

	/**
	 * Activate / deactivate form
	 */
	public function handle() {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', '@@textdomain' ) );
		}

		check_admin_referer( 'vlt_fw_license' );

		$license = self::get();
		$message = [ 'type' => 'success', 'text' => '' ];

		if ( 'deactivate' === ( $_POST['license_action'] ?? '' ) ) {
			$body = $this->request( 'license/deactivate', $license['key'] );

			// Server can't be reached: keep the key, the domain is still taken there
			if ( is_wp_error( $body ) && 'http_request_failed' === $body->get_error_code() ) {
				$message = [ 'type' => 'error', 'text' => $body->get_error_message() ];
			} else {
				delete_option( self::OPTION );
				wp_clear_scheduled_hook( self::CRON );
				delete_transient( self::UPDATE );
				delete_site_transient( 'update_themes' );
				$message['text'] = esc_html__( 'License deactivated.', '@@textdomain' );
			}
		} else {
			$key  = trim( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
			$body = $key ? $this->request( 'license/activate', $key ) : new \WP_Error( 'vlt_fw_license_empty', esc_html__( 'Enter your purchase code or license key.', '@@textdomain' ) );

			if ( is_wp_error( $body ) ) {
				$message = [ 'type' => 'error', 'text' => $body->get_error_message() ];
			} else {
				$this->save( $key, $body );
				$message['text'] = (string) ( $body['message'] ?? '' );
			}
		}

		set_transient( 'vlt_fw_license_message_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'themes.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Daily check: changes made on the license server reach the site. Server down → the last status stays.
	 */
	public function check() {
		$license = self::get();

		if ( !$license['key'] ) {
			return;
		}

		$body = $this->request( 'license/check', $license['key'] );

		if ( !is_wp_error( $body ) ) {
			$this->save( $license['key'], $body );
		} elseif ( 404 === ( $body->get_error_data()['status'] ?? 0 ) ) {
			$this->save( $license['key'], [ 'status' => 'inactive' ] );
		}
	}

	/**
	 * Theme updates from the license server (package only for an active license)
	 */
	public function update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = get_transient( self::UPDATE );

		if ( false === $remote ) {
			$remote = $this->request( 'theme/update', self::get()['key'], 'GET' );
			$remote = is_wp_error( $remote ) ? [] : $remote;

			set_transient( self::UPDATE, $remote, $remote ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		}

		$theme = get_template();

		if ( !empty( $remote['version'] ) && version_compare( $remote['version'], wp_get_theme( $theme )->get( 'Version' ), '>' ) ) {
			$transient->response[ $theme ] = [
				'theme'        => $theme,
				'new_version'  => $remote['version'],
				'url'          => $remote['url'] ?? '',
				'package'      => $remote['package'] ?? '',
				'requires'     => $remote['requires'] ?? '',
				'requires_php' => $remote['requires_php'] ?? ''
			];
		}

		return $transient;
	}

	/**
	 * Admin notice until the theme is activated
	 */
	public function notice() {
		if ( self::is_active() || !current_user_can( 'manage_options' ) || self::PAGE === ( $_GET['page'] ?? '' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			/* translators: %s: theme name */
			esc_html( sprintf( __( 'Activate %s to get theme updates and support.', '@@textdomain' ), wp_get_theme( get_template() )->get( 'Name' ) ) ),
			esc_url( admin_url( 'themes.php?page=' . self::PAGE ) ),
			esc_html__( 'Activate now', '@@textdomain' )
		);
	}

	/**
	 * Support end date: 07-11-2026 (active, 37 days left) / 07-11-2026 (expired 3 days ago)
	 */
	private function support_label( $date ) {
		$until = $date ? strtotime( $date ) : 0;

		if ( !$until ) {
			return '';
		}

		$days = (int) floor( ( $until - strtotime( gmdate( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );

		return gmdate( 'd-m-Y', $until ) . ' (' . ( $days >= 0
			/* translators: %d: days */
			? sprintf( _n( 'active, %d day left', 'active, %d days left', $days, '@@textdomain' ), $days )
			/* translators: %d: days */
			: sprintf( _n( 'expired %d day ago', 'expired %d days ago', -$days, '@@textdomain' ), -$days ) ) . ')';
	}

	/**
	 * Theme License page
	 */
	public function render() {
		$license = self::get();
		$user    = 'vlt_fw_license_message_' . get_current_user_id();
		$message = get_transient( $user );
		$masked  = $license['key'] ? substr( $license['key'], 0, 4 ) . str_repeat( '•', 8 ) . substr( $license['key'], -4 ) : '';

		delete_transient( $user );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Theme License', '@@textdomain' ); ?></h1>

			<?php if ( !empty( $message['text'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message['type'] ); ?>"><p><?php echo esc_html( $message['text'] ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Enter your ThemeForest purchase code or Gumroad license key to get theme updates and support. One license works on one live site; local and staging sites are free.', '@@textdomain' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vlt_fw_license">
				<?php wp_nonce_field( 'vlt_fw_license' ); ?>

				<table class="form-table" role="presentation">
					<?php if ( $license['key'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', '@@textdomain' ); ?></th>
							<td><strong><?php echo esc_html( $license['status'] ? ucfirst( $license['status'] ) : '—' ); ?></strong></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Key', '@@textdomain' ); ?></th>
							<td><code><?php echo esc_html( $masked ); ?></code></td>
						</tr>
						<?php if ( $license['product'] ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Product', '@@textdomain' ); ?></th>
								<td><?php echo esc_html( $license['product'] ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Domain', '@@textdomain' ); ?></th>
							<td><?php echo esc_html( $this->domain() ); ?></td>
						</tr>
						<?php if ( $license['supported_until'] ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Support until', '@@textdomain' ); ?></th>
								<td><?php echo esc_html( $this->support_label( $license['supported_until'] ) ); ?></td>
							</tr>
						<?php endif; ?>
					<?php else : ?>
						<tr>
							<th scope="row"><label for="vlt-license-key"><?php esc_html_e( 'Purchase code / license key', '@@textdomain' ); ?></label></th>
							<td><input class="regular-text code" id="vlt-license-key" name="license_key" type="text" autocomplete="off" spellcheck="false" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required></td>
						</tr>
					<?php endif; ?>
				</table>

				<?php if ( $license['key'] ) : ?>
					<?php if ( !self::is_active() ) : ?>
						<input type="hidden" name="license_key" value="<?php echo esc_attr( $license['key'] ); ?>">
						<button class="button button-primary" type="submit" name="license_action" value="activate"><?php esc_html_e( 'Activate', '@@textdomain' ); ?></button>
					<?php endif; ?>
					<button class="button" type="submit" name="license_action" value="deactivate"><?php esc_html_e( 'Deactivate', '@@textdomain' ); ?></button>
				<?php else : ?>
					<button class="button button-primary" type="submit" name="license_action" value="activate"><?php esc_html_e( 'Activate', '@@textdomain' ); ?></button>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
