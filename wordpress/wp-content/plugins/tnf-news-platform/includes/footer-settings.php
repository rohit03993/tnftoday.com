<?php
/**
 * Footer copy and “total views” (Settings → TNF Footer).
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
		'show_views_bar'   => 1,
		'total_views'      => 0,
		'disclaimer_text'  => $disclaimer,
		'disclaimer_email' => '',
		'credits_line'     => __('Designed & Developed by Your Team', 'tnf-news-platform'),
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

	$out                     = array_merge($defaults, $raw);
	$out['show_views_bar']   = (int) $out['show_views_bar'] ? 1 : 0;
	$out['total_views']      = max(0, (int) $out['total_views']);
	$out['disclaimer_text']  = is_string($out['disclaimer_text']) ? $out['disclaimer_text'] : $defaults['disclaimer_text'];
	$out['disclaimer_email'] = is_string($out['disclaimer_email']) ? sanitize_email($out['disclaimer_email']) : '';
	$out['credits_line']     = is_string($out['credits_line']) ? $out['credits_line'] : $defaults['credits_line'];

	if ($out['disclaimer_email'] === '') {
		$out['disclaimer_email'] = sanitize_email((string) get_option('admin_email', ''));
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

	$show  = isset($input['show_views_bar']) && (string) $input['show_views_bar'] === '1' ? 1 : 0;
	$views = isset($input['total_views']) ? max(0, (int) $input['total_views']) : 0;
	$text  = isset($input['disclaimer_text']) ? wp_unslash((string) $input['disclaimer_text']) : $defaults['disclaimer_text'];
	$email = isset($input['disclaimer_email']) ? sanitize_email(wp_unslash((string) $input['disclaimer_email'])) : '';
	$creds = isset($input['credits_line']) ? sanitize_text_field(wp_unslash((string) $input['credits_line'])) : $defaults['credits_line'];

	return array(
		'show_views_bar'     => $show,
		'total_views'        => $views,
		'disclaimer_text'    => $text,
		'disclaimer_email'   => $email,
		'credits_line'       => $creds,
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
		<p class="description"><?php esc_html_e('Site-wide footer: views strip, disclaimer, copyright bar.', 'tnf-news-platform'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('tnf_footer_settings_group'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Total views bar', 'tnf-news-platform'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[show_views_bar]" value="1" <?php checked($opts['show_views_bar'], 1); ?> />
							<?php esc_html_e('Show the light gray “Total views” strip', 'tnf-news-platform'); ?>
						</label>
						<p>
							<label for="tnf_footer_total_views"><?php esc_html_e('Displayed number', 'tnf-news-platform'); ?></label><br />
							<input id="tnf_footer_total_views" class="small-text" type="number" min="0" step="1" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[total_views]" value="<?php echo esc_attr((string) $opts['total_views']); ?>" />
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_footer_disclaimer"><?php esc_html_e('Disclaimer text', 'tnf-news-platform'); ?></label></th>
					<td>
						<textarea id="tnf_footer_disclaimer" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[disclaimer_text]" class="large-text" rows="6"><?php echo esc_textarea($opts['disclaimer_text']); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_footer_email"><?php esc_html_e('Contact email (disclaimer)', 'tnf-news-platform'); ?></label></th>
					<td>
						<input id="tnf_footer_email" type="email" class="regular-text" name="<?php echo esc_attr(tnf_footer_settings_option_key()); ?>[disclaimer_email]" value="<?php echo esc_attr($opts['disclaimer_email']); ?>" />
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
