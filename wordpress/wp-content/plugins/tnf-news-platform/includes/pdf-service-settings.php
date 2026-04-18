<?php
/**
 * Settings → TNF PDF / ePaper: PDF microservice base URL and optional secret.
 *
 * @package TNF_News_Platform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param mixed $value Raw option value.
 */
function tnf_pdf_service_settings_sanitize_url( $value ): string {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = trim( $value );
	if ( $value === '' ) {
		return '';
	}
	$out = esc_url_raw( $value );

	return is_string( $out ) ? untrailingslashit( $out ) : '';
}

/**
 * @param mixed $value Raw option value.
 */
function tnf_pdf_service_settings_sanitize_secret( $value ): string {
	if ( isset( $_POST['tnf_pdf_service_secret_clear'] ) && '1' === sanitize_text_field( wp_unslash( (string) ( $_POST['tnf_pdf_service_secret_clear'] ?? '' ) ) ) ) {
		return '';
	}
	$prev = (string) get_option( 'tnf_pdf_service_secret', '' );
	if ( ! is_string( $value ) ) {
		return $prev;
	}
	$value = trim( $value );
	if ( $value === '' ) {
		return $prev;
	}

	return sanitize_text_field( $value );
}

/**
 * Register options (used when wp-config constants are not set).
 */
function tnf_pdf_service_settings_register(): void {
	register_setting(
		'tnf_pdf_service_settings_group',
		'tnf_pdf_service_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'tnf_pdf_service_settings_sanitize_url',
			'default'           => '',
		)
	);
	register_setting(
		'tnf_pdf_service_settings_group',
		'tnf_pdf_service_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'tnf_pdf_service_settings_sanitize_secret',
			'default'           => '',
		)
	);
}
add_action( 'admin_init', 'tnf_pdf_service_settings_register' );

/**
 * Settings submenu under Settings.
 */
function tnf_pdf_service_settings_menu(): void {
	add_options_page(
		__( 'TNF PDF / ePaper', 'tnf-news-platform' ),
		__( 'TNF PDF / ePaper', 'tnf-news-platform' ),
		'manage_options',
		'tnf-pdf-service-settings',
		'tnf_pdf_service_settings_render_page'
	);
}
add_action( 'admin_menu', 'tnf_pdf_service_settings_menu' );

/**
 * Render settings form.
 */
function tnf_pdf_service_settings_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$url_opt    = (string) get_option( 'tnf_pdf_service_url', '' );
	$has_const  = defined( 'TNF_PDF_SERVICE_URL' ) && TNF_PDF_SERVICE_URL;
	$has_env    = is_string( getenv( 'TNF_PDF_SERVICE_URL' ) ) && getenv( 'TNF_PDF_SERVICE_URL' ) !== '';
	$effective  = tnf_pdf_service_base_url();
	$has_secret = ( defined( 'TNF_PDF_SERVICE_SECRET' ) && TNF_PDF_SERVICE_SECRET )
		|| ( is_string( getenv( 'TNF_PDF_SERVICE_SECRET' ) ) && getenv( 'TNF_PDF_SERVICE_SECRET' ) !== '' );
	$stored_secret = (string) get_option( 'tnf_pdf_service_secret', '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'TNF PDF / ePaper', 'tnf-news-platform' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'ePaper processing calls a separate PDF microservice (FastAPI). Production must not use localhost — set the URL below, or use wp-config.php / server environment variables.', 'tnf-news-platform' ); ?>
		</p>

		<?php if ( $has_const ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'TNF_PDF_SERVICE_URL is defined in wp-config.php and overrides the field below. The value shown as “effective” is what WordPress uses.', 'tnf-news-platform' ); ?></p>
			</div>
		<?php elseif ( $has_env ) : ?>
			<div class="notice notice-info">
				<p><?php esc_html_e( 'TNF_PDF_SERVICE_URL is set in the server environment and overrides the field below.', 'tnf-news-platform' ); ?></p>
			</div>
		<?php endif; ?>

		<p><strong><?php esc_html_e( 'Effective base URL:', 'tnf-news-platform' ); ?></strong>
			<code><?php echo esc_html( $effective ); ?></code>
			<span class="description"><?php esc_html_e( '(WordPress calls {this}/pdf/process)', 'tnf-news-platform' ); ?></span>
		</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'tnf_pdf_service_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="tnf_pdf_service_url"><?php esc_html_e( 'PDF service base URL', 'tnf-news-platform' ); ?></label>
					</th>
					<td>
						<input
							id="tnf_pdf_service_url"
							name="tnf_pdf_service_url"
							type="url"
							class="large-text code"
							value="<?php echo esc_attr( $url_opt ); ?>"
							placeholder="https://pdf-api.example.com"
							<?php disabled( $has_const || $has_env ); ?>
						/>
						<p class="description">
							<?php esc_html_e( 'HTTPS URL of your PDF worker, with no trailing slash. Leave empty for default (localhost:8000 — local dev only).', 'tnf-news-platform' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="tnf_pdf_service_secret"><?php esc_html_e( 'Service secret (optional)', 'tnf-news-platform' ); ?></label>
					</th>
					<td>
						<input
							id="tnf_pdf_service_secret"
							name="tnf_pdf_service_secret"
							type="password"
							class="regular-text code"
							value=""
							autocomplete="new-password"
							<?php disabled( $has_secret ); ?>
						/>
						<p class="description">
							<?php
							if ( $has_secret ) {
								esc_html_e( 'Secret is set via wp-config.php or environment; this field is disabled.', 'tnf-news-platform' );
							} else {
								esc_html_e( 'Sent as X-Service-Secret if your API requires it. Leave blank to keep the current secret; enter a new value to replace it.', 'tnf-news-platform' );
							}
							?>
						</p>
						<?php if ( ! $has_secret && $stored_secret !== '' ) : ?>
							<p>
								<label>
									<input type="checkbox" name="tnf_pdf_service_secret_clear" value="1" />
									<?php esc_html_e( 'Remove stored secret', 'tnf-news-platform' ); ?>
								</label>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
