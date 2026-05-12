<?php
/**
 * Header banner (Settings → TNF Header).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Option key for header settings array.
 */
function tnf_header_settings_option_key(): string {
	return 'tnf_header_settings';
}

/**
 * Default header settings.
 *
 * @return array<string,int|string>
 */
function tnf_header_default_settings(): array {
	return array(
		'banner_attachment_id' => 0,
		'banner_link_url'      => '',
	);
}

/**
 * Merged header settings.
 *
 * @return array<string,int|string>
 */
function tnf_header_get_settings(): array {
	$defaults = tnf_header_default_settings();
	$raw      = get_option(tnf_header_settings_option_key(), array());
	if (! is_array($raw)) {
		$raw = array();
	}

	$out                          = array_merge($defaults, $raw);
	$out['banner_attachment_id'] = absint($out['banner_attachment_id']);
	$out['banner_link_url']      = is_string($out['banner_link_url']) ? esc_url_raw($out['banner_link_url']) : '';

	return $out;
}

/**
 * @param mixed $input Raw POST.
 * @return array<string,int|string>
 */
function tnf_header_settings_sanitize($input): array {
	$defaults = tnf_header_default_settings();
	if (! is_array($input)) {
		return $defaults;
	}

	$aid  = isset($input['banner_attachment_id']) ? absint($input['banner_attachment_id']) : 0;
	$link = isset($input['banner_link_url']) ? esc_url_raw(wp_unslash((string) $input['banner_link_url'])) : '';

	return array(
		'banner_attachment_id' => $aid,
		'banner_link_url'      => $link,
	);
}

/**
 * Register option.
 */
function tnf_header_register_settings(): void {
	register_setting(
		'tnf_header_settings_group',
		tnf_header_settings_option_key(),
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tnf_header_settings_sanitize',
			'default'           => tnf_header_default_settings(),
		)
	);
}
add_action('admin_init', 'tnf_header_register_settings');

/**
 * Settings submenu.
 */
function tnf_header_options_menu(): void {
	add_options_page(
		__('TNF Header', 'tnf-news-platform'),
		__('TNF Header', 'tnf-news-platform'),
		'manage_options',
		'tnf-header-settings',
		'tnf_header_render_options_page'
	);
}
add_action('admin_menu', 'tnf_header_options_menu');

/**
 * Enqueue media modal + admin JS on settings page.
 *
 * @param string $hook_suffix Current admin page.
 */
function tnf_header_settings_admin_enqueue_scripts(string $hook_suffix): void {
	if ($hook_suffix !== 'settings_page_tnf-header-settings') {
		return;
	}

	wp_enqueue_media();

	$js_path = TNF_NEWS_PLATFORM_PATH . 'assets/js/tnf-header-settings.js';
	if (! is_readable($js_path)) {
		return;
	}

	wp_enqueue_script(
		'tnf-header-settings',
		TNF_NEWS_PLATFORM_URL . 'assets/js/tnf-header-settings.js',
		array('jquery'),
		(string) filemtime($js_path),
		true
	);

	wp_localize_script(
		'tnf-header-settings',
		'tnfHeaderSettingsL10n',
		array(
			'title'  => __('Choose header banner image', 'tnf-news-platform'),
			'button' => __('Use this image', 'tnf-news-platform'),
		)
	);
}
add_action('admin_enqueue_scripts', 'tnf_header_settings_admin_enqueue_scripts');

/**
 * Render options UI.
 */
function tnf_header_render_options_page(): void {
	if (! current_user_can('manage_options')) {
		return;
	}

	$opts = tnf_header_get_settings();
	$aid  = (int) ($opts['banner_attachment_id'] ?? 0);
	$src  = $aid ? wp_get_attachment_image_url($aid, 'large') : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e('TNF Header', 'tnf-news-platform'); ?></h1>
		<p class="description"><?php esc_html_e('When set, this single image replaces the logo + “Top banner” area above the menu. Upload a wide masthead (PNG/WebP/JPG). Login / Register stay on the right.', 'tnf-news-platform'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('tnf_header_settings_group'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Top banner image', 'tnf-news-platform'); ?></th>
					<td>
						<input
							type="hidden"
							id="tnf_header_banner_attachment_id"
							name="<?php echo esc_attr(tnf_header_settings_option_key()); ?>[banner_attachment_id]"
							value="<?php echo esc_attr((string) $aid); ?>"
						/>
						<div id="tnf_header_banner_preview" style="margin:8px 0;">
							<?php if (is_string($src) && $src !== '') : ?>
								<img src="<?php echo esc_url($src); ?>" alt="" style="max-width:520px;width:100%;height:auto;border:1px solid rgba(0,0,0,.12);border-radius:6px;" />
							<?php else : ?>
								<div style="max-width:520px;padding:18px;border:1px dashed rgba(0,0,0,.25);border-radius:6px;color:#555;">
									<?php esc_html_e('No banner image selected yet.', 'tnf-news-platform'); ?>
								</div>
							<?php endif; ?>
						</div>
						<p>
							<button type="button" class="button button-primary" id="tnf_header_banner_select_btn"><?php esc_html_e('Upload / choose image', 'tnf-news-platform'); ?></button>
							<button type="button" class="button-link" id="tnf_header_banner_clear_btn" style="margin-left:6px;<?php echo $aid ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove', 'tnf-news-platform'); ?></button>
						</p>
						<p class="description">
							<?php esc_html_e('Recommended: a wide rectangular banner (PNG/WebP/JPG).', 'tnf-news-platform'); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_header_banner_link"><?php esc_html_e('Banner link (optional)', 'tnf-news-platform'); ?></label></th>
					<td>
						<input
							id="tnf_header_banner_link"
							type="url"
							class="large-text"
							name="<?php echo esc_attr(tnf_header_settings_option_key()); ?>[banner_link_url]"
							value="<?php echo esc_attr((string) ($opts['banner_link_url'] ?? '')); ?>"
							placeholder="https://"
						/>
						<p class="description"><?php esc_html_e('If set, clicking the banner opens this URL.', 'tnf-news-platform'); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

