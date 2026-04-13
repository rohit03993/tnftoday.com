<?php
/**
 * Footer copy (Settings → TNF Footer).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Option key for footer settings array.
 */
function tnf_footer_settings_option_key(): string {
	return 'tnf_footer_settings';
}

/**
 * Default footer settings.
 *
 * @return array<string, int|string>
 */
function tnf_footer_default_settings(): array {
	$disclaimer = __(
		'The information published on this website is compiled from various sources; we do not guarantee accuracy. This site is not affiliated with any government body. For corrections, feedback, or complaints, please contact us using the email below.',
		'tnf-news-platform'
	);

	return array(
		'disclaimer_text'  => $disclaimer,
		'disclaimer_email' => 'contact@tnftoday.com',
		'credits_line'     => __('Designed & Developed with Love by Pal Digital', 'tnf-news-platform'),
	);
}

/**
 * Merged footer settings.
 *
 * @return array<string, int|string>
 */
function tnf_footer_get_settings(): array {
	$defaults = tnf_footer_default_settings();
	$raw      = get_option(tnf_footer_settings_option_key(), array());
	if (! is_array($raw)) {
		$raw = array();
	}

	$out                    = array_merge($defaults, $raw);
	$out['disclaimer_text'] = is_string($out['disclaimer_text']) ? $out['disclaimer_text'] : $defaults['disclaimer_text'];
	$out['disclaimer_email'] = is_string($out['disclaimer_email']) ? sanitize_email($out['disclaimer_email']) : '';
	$out['credits_line']     = is_string($out['credits_line']) ? $out['credits_line'] : $defaults['credits_line'];

	if ($out['disclaimer_email'] === '') {
		$out['disclaimer_email'] = 'contact@tnftoday.com';
	}
	if (trim((string) $out['credits_line']) === '' || trim((string) $out['credits_line']) === 'Designed & Developed by Your Team') {
		$out['credits_line'] = $defaults['credits_line'];
	}

	return $out;
}

/**
 * @param mixed $input Raw POST.
 * @return array<string, int|string>
 */
function tnf_footer_settings_sanitize($input): array {
	$defaults = tnf_footer_default_settings();
	if (! is_array($input)) {
		return $defaults;
	}

	$text = isset($input['disclaimer_text']) ? wp_unslash((string) $input['disclaimer_text']) : $defaults['disclaimer_text'];
	$email = isset($input['disclaimer_email']) ? sanitize_email(wp_unslash((string) $input['disclaimer_email'])) : '';
	if ($email === '') {
		$email = 'contact@tnftoday.com';
	}
	$creds = isset($input['credits_line']) ? sanitize_text_field(wp_unslash((string) $input['credits_line'])) : $defaults['credits_line'];

	return array(
		'disclaimer_text'  => $text,
		'disclaimer_email' => $email,
		'credits_line'     => $creds,
	);
}

/**
 * Register option and settings page.
 */
function tnf_footer_register_settings(): void {
	register_setting(
		'tnf_footer_settings_group',
		tnf_footer_settings_option_key(),
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tnf_footer_settings_sanitize',
			'default'           => tnf_footer_default_settings(),
		)
	);
}
add_action('admin_init', 'tnf_footer_register_settings');

/**
 * Settings submenu.
 */
function tnf_footer_options_menu(): void {
	add_options_page(
		__('TNF Footer', 'tnf-news-platform'),
		__('TNF Footer', 'tnf-news-platform'),
		'manage_options',
		'tnf-footer-settings',
		'tnf_footer_render_options_page'
	);
}
add_action('admin_menu', 'tnf_footer_options_menu');

/**
 * Render options UI.
 */
function tnf_footer_render_options_page(): void {
	if (! current_user_can('manage_options')) {
		return;
	}

	$opts = tnf_footer_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e('TNF Footer', 'tnf-news-platform'); ?></h1>
		<p class="description"><?php esc_html_e('Site-wide footer: disclaimer and copyright bar.', 'tnf-news-platform'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('tnf_footer_settings_group'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tnf_footer_disclaimer"><?php esc_html_e('Disclaimer text', 'tnf-news-platform'); ?></label></th>
					<td>
						<textarea id="tnf_footer_disclaimer" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[disclaimer_text]" class="large-text" rows="6"><?php echo esc_textarea($opts['disclaimer_text']); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_footer_email"><?php esc_html_e('Contact email (disclaimer)', 'tnf-news-platform'); ?></label></th>
					<td>
						<input id="tnf_footer_email" type="email" class="regular-text" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[disclaimer_email]" value="<?php echo esc_attr($opts['disclaimer_email']); ?>" placeholder="contact@tnftoday.com" autocomplete="email" />
						<p class="description"><?php esc_html_e('Shown below the disclaimer on every page that uses the TNF footer. Leave empty and save to use contact@tnftoday.com.', 'tnf-news-platform'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_footer_credits"><?php esc_html_e('Credits line', 'tnf-news-platform'); ?></label></th>
					<td>
						<input id="tnf_footer_credits" type="text" class="large-text" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[credits_line]" value="<?php echo esc_attr((string) $opts['credits_line']); ?>" />
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
