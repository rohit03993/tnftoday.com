<?php
/**
 * Admin meta boxes and columns.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register admin UI.
 */
function tnf_register_admin_ui(): void {
	add_action('add_meta_boxes_tnf_pdf_report', 'tnf_pdf_report_meta_boxes');
	add_action('save_post_tnf_pdf_report', 'tnf_save_pdf_report_meta', 10, 2);
	add_action('admin_enqueue_scripts', 'tnf_pdf_report_admin_enqueue_scripts');
	add_action('add_meta_boxes_tnf_video', 'tnf_video_meta_boxes');
	add_action('save_post_tnf_video', 'tnf_save_video_meta', 10, 2);
	add_action('admin_menu', 'tnf_homepage_controls_menu');
	add_action('admin_init', 'tnf_homepage_controls_register');
	add_action('admin_init', 'tnf_maybe_purge_fse_custom_templates');
	add_filter('post_row_actions', 'tnf_user_submission_row_actions', 10, 2);
	add_action('admin_post_tnf_approve_submission', 'tnf_admin_post_approve_user_submission');
	add_action('admin_post_tnf_reject_submission', 'tnf_admin_post_reject_user_submission');
	add_action('admin_notices', 'tnf_user_submission_moderation_admin_notices');
	add_action('add_meta_boxes_tnf_user_submission', 'tnf_user_submission_register_moderation_metabox');
	add_action('add_meta_boxes_tnf_news', 'tnf_news_embed_meta_boxes');
	add_action('save_post_tnf_news', 'tnf_save_news_embed_meta', 10, 2);
}

/**
 * Delete Site Editor–saved templates/parts for the active theme so file templates win.
 */
function tnf_purge_fse_custom_templates_for_active_theme(): int {
	if (! function_exists('get_block_templates')) {
		return 0;
	}

	$theme   = get_stylesheet();
	$deleted = 0;

	foreach ( array( 'wp_template', 'wp_template_part' ) as $kind ) {
		$list = get_block_templates(
			array( 'theme' => $theme ),
			$kind
		);
		if (! is_array($list)) {
			continue;
		}
		foreach ($list as $tpl) {
			if (($tpl->source ?? '') !== 'custom') {
				continue;
			}
			$wid = isset($tpl->wp_id) ? (int) $tpl->wp_id : 0;
			if ($wid > 0) {
				wp_delete_post($wid, true);
				$deleted++;
			}
		}
	}

	return $deleted;
}

/**
 * POST handler: reset customized block templates.
 */
function tnf_maybe_purge_fse_custom_templates(): void {
	if (! isset($_POST['tnf_purge_fse_templates']) || ! is_admin()) {
		return;
	}
	if (! current_user_can('edit_theme_options')) {
		return;
	}
	check_admin_referer('tnf_purge_fse_templates_action', 'tnf_purge_fse_templates_nonce');
	$n = tnf_purge_fse_custom_templates_for_active_theme();
	if ($n === 0) {
		add_settings_error(
			'tnf_templates',
			'tnf_purged',
			__('No customized templates were found for the active theme. File templates are already in use.', 'tnf-news-platform'),
			'info'
		);
	} else {
		add_settings_error(
			'tnf_templates',
			'tnf_purged',
			sprintf(
				/* translators: %d: number of removed template records */
				_n('Removed %d customized template record. Your theme’s HTML files are now used.', 'Removed %d customized template records. Your theme’s HTML files are now used.', $n, 'tnf-news-platform'),
				$n
			),
			'success'
		);
	}
}

/**
 * Meta boxes for PDF report.
 */
function tnf_pdf_report_meta_boxes(): void {
	add_meta_box(
		'tnf_pdf_file',
		__('PDF file & access', 'tnf-news-platform'),
		'tnf_render_pdf_file_metabox',
		'tnf_pdf_report',
		'side',
		'high'
	);
}

/**
 * Admin assets: Media modal for PDF metabox + block editor document panel.
 *
 * @param string $hook_suffix Current admin page.
 */
function tnf_pdf_report_admin_enqueue_scripts(string $hook_suffix): void {
	if ($hook_suffix !== 'post.php' && $hook_suffix !== 'post-new.php') {
		return;
	}

	$post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
	if ($post_type === '' && isset($_GET['post'])) {
		$post_type = get_post_type((int) $_GET['post']);
	}
	if ($post_type !== 'tnf_pdf_report') {
		return;
	}

	wp_enqueue_media();

	$metabox_js = TNF_NEWS_PLATFORM_PATH . 'assets/js/tnf-pdf-report-metabox.js';
	if (is_readable($metabox_js)) {
		wp_enqueue_script(
			'tnf-pdf-report-metabox',
			TNF_NEWS_PLATFORM_URL . 'assets/js/tnf-pdf-report-metabox.js',
			array('jquery'),
			(string) filemtime($metabox_js),
			true
		);
		wp_localize_script(
			'tnf-pdf-report-metabox',
			'tnfPdfMetaboxL10n',
			array(
				'title'   => __('Choose PDF file', 'tnf-news-platform'),
				'button'  => __('Use this file', 'tnf-news-platform'),
				'none'    => __('No PDF selected yet.', 'tnf-news-platform'),
				'remove'  => __('Remove PDF', 'tnf-news-platform'),
				'select'  => __('Upload or choose PDF', 'tnf-news-platform'),
				'replace' => __('Replace PDF', 'tnf-news-platform'),
			)
		);
	}

	$sidebar_js = TNF_NEWS_PLATFORM_PATH . 'assets/js/tnf-pdf-report-sidebar.js';
	if (is_readable($sidebar_js)) {
		wp_enqueue_script(
			'tnf-pdf-report-sidebar',
			TNF_NEWS_PLATFORM_URL . 'assets/js/tnf-pdf-report-sidebar.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-i18n',
				'wp-block-editor',
				'wp-core-data',
			),
			(string) filemtime($sidebar_js),
			true
		);
		wp_localize_script(
			'tnf-pdf-report-sidebar',
			'tnfPdfSidebarAdmin',
			array(
				'postEditUrlTpl' => admin_url('post.php?post=__ID__&action=edit'),
			)
		);
	}
}

/**
 * Render PDF metabox.
 *
 * @param WP_Post $post Post.
 */
function tnf_render_pdf_file_metabox(WP_Post $post): void {
	wp_nonce_field('tnf_pdf_meta', 'tnf_pdf_meta_nonce');
	$aid        = (int) get_post_meta($post->ID, 'tnf_pdf_attachment_id', true);
	$restricted = (bool) get_post_meta($post->ID, 'tnf_restricted', true);
	$status     = (string) get_post_meta($post->ID, 'tnf_pdf_status', true);
	$job        = (string) get_post_meta($post->ID, 'tnf_pdf_job_id', true);
	$err        = (string) get_post_meta($post->ID, 'tnf_pdf_error', true);
	$label      = '';
	if ($aid) {
		$att  = get_post($aid);
		$mime = get_post_mime_type($aid);
		if ($att instanceof WP_Post && $mime === 'application/pdf') {
			$label = $att->post_title ?: basename((string) get_attached_file($aid));
		} else {
			$label = sprintf(/* translators: %d: attachment ID */ __('Attachment #%d (missing or not a PDF)', 'tnf-news-platform'), $aid);
		}
	}
	?>
	<p class="description" style="margin-top:0;">
		<?php esc_html_e('In the block editor, use the PDF Report panel in the right sidebar (Document). You can also use the buttons below.', 'tnf-news-platform'); ?>
	</p>
	<input type="hidden" name="tnf_pdf_attachment_id" id="tnf_pdf_attachment_id" value="<?php echo esc_attr((string) $aid); ?>" />
	<p>
		<button type="button" class="button button-primary" id="tnf_pdf_select_btn"><?php esc_html_e('Upload or choose PDF', 'tnf-news-platform'); ?></button>
		<button type="button" class="button-link" id="tnf_pdf_clear_btn" style="margin-left:6px;<?php echo $aid ? '' : 'display:none;'; ?>"><?php esc_html_e('Remove', 'tnf-news-platform'); ?></button>
	</p>
	<p id="tnf_pdf_file_summary" class="description" style="word-break:break-word;">
		<?php echo $aid ? esc_html($label) : esc_html__('No PDF selected yet.', 'tnf-news-platform'); ?>
	</p>
	<?php if ($aid) : ?>
		<p class="description">
			<a href="<?php echo esc_url(admin_url('post.php?post=' . $aid . '&action=edit')); ?>"><?php esc_html_e('Edit file in Media Library', 'tnf-news-platform'); ?></a>
		</p>
	<?php endif; ?>
	<p>
		<label>
			<input type="checkbox" name="tnf_restricted" value="1" <?php checked($restricted); ?> />
			<?php esc_html_e('Subscriber-only PDF', 'tnf-news-platform'); ?>
		</label>
	</p>
	<hr />
	<p><strong><?php esc_html_e('Processing', 'tnf-news-platform'); ?></strong></p>
	<p><?php echo esc_html(sprintf(/* translators: 1: status */ __('Status: %s', 'tnf-news-platform'), $status ?: '—')); ?></p>
	<p><?php echo esc_html(sprintf(/* translators: 1: job id */ __('Job ID: %s', 'tnf-news-platform'), $job ?: '—')); ?></p>
	<?php if ($err) : ?>
		<p class="notice notice-error" style="padding:8px;"><?php echo esc_html($err); ?></p>
	<?php endif; ?>
	<p class="description"><?php esc_html_e('Publish this report so it appears on the public ePaper archive (/epaper/).', 'tnf-news-platform'); ?></p>
	<?php
}

/**
 * Save PDF metabox.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function tnf_save_pdf_report_meta(int $post_id, WP_Post $post): void {
	if (! isset($_POST['tnf_pdf_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tnf_pdf_meta_nonce'])), 'tnf_pdf_meta')) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}

	$aid = isset($_POST['tnf_pdf_attachment_id']) ? absint($_POST['tnf_pdf_attachment_id']) : 0;
	update_post_meta($post_id, 'tnf_pdf_attachment_id', $aid);
	$res = isset($_POST['tnf_restricted']) && '1' === $_POST['tnf_restricted'];
	update_post_meta($post_id, 'tnf_restricted', $res);
}

/**
 * Video meta boxes.
 */
function tnf_video_meta_boxes(): void {
	add_meta_box(
		'tnf_video_embed',
		__('Embed URL', 'tnf-news-platform'),
		'tnf_render_video_metabox',
		'tnf_video',
		'normal',
		'high'
	);
}

/**
 * Render video metabox.
 *
 * @param WP_Post $post Post.
 */
function tnf_render_video_metabox(WP_Post $post): void {
	wp_nonce_field('tnf_video_meta', 'tnf_video_meta_nonce');
	$url = (string) get_post_meta($post->ID, 'tnf_embed_url', true);
	?>
	<p>
		<label><strong><?php esc_html_e('YouTube / Instagram / Facebook URL', 'tnf-news-platform'); ?></strong></label><br />
		<input type="url" name="tnf_embed_url" value="<?php echo esc_attr($url); ?>" class="widefat" />
	</p>
	<?php
}

/**
 * News: optional embed URL (YouTube, etc.) for the single template.
 */
function tnf_news_embed_meta_boxes(): void {
	add_meta_box(
		'tnf_news_embed',
		__('Video / embed URL (optional)', 'tnf-news-platform'),
		'tnf_render_news_embed_metabox',
		'tnf_news',
		'normal',
		'high'
	);
}

/**
 * @param WP_Post $post Post.
 */
function tnf_render_news_embed_metabox(WP_Post $post): void {
	wp_nonce_field('tnf_news_embed_meta', 'tnf_news_embed_meta_nonce');
	$url = (string) get_post_meta($post->ID, 'tnf_embed_url', true);
	?>
	<p>
		<label><strong><?php esc_html_e('YouTube / Instagram / Facebook URL', 'tnf-news-platform'); ?></strong></label><br />
		<input type="url" name="tnf_news_embed_url" value="<?php echo esc_attr($url); ?>" class="widefat" />
	</p>
	<?php
}

/**
 * Save news embed meta.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function tnf_save_news_embed_meta(int $post_id, WP_Post $post): void {
	if (! isset($_POST['tnf_news_embed_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tnf_news_embed_meta_nonce'])), 'tnf_news_embed_meta')) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}
	$url = isset($_POST['tnf_news_embed_url']) ? esc_url_raw(wp_unslash((string) $_POST['tnf_news_embed_url'])) : '';
	update_post_meta($post_id, 'tnf_embed_url', $url);
}

/**
 * Save video meta.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function tnf_save_video_meta(int $post_id, WP_Post $post): void {
	if (! isset($_POST['tnf_video_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tnf_video_meta_nonce'])), 'tnf_video_meta')) {
		return;
	}
	if (! current_user_can('edit_post', $post_id)) {
		return;
	}
	$url = isset($_POST['tnf_embed_url']) ? esc_url_raw(wp_unslash($_POST['tnf_embed_url'])) : '';
	update_post_meta($post_id, 'tnf_embed_url', $url);
}

/**
 * Homepage controls option key.
 */
function tnf_homepage_controls_option_key(): string {
	return 'tnf_homepage_controls';
}

/**
 * Defaults for homepage controls.
 *
 * @return array<string,mixed>
 */
function tnf_homepage_default_controls(): array {
	return array(
		'breaking_count'        => 12,
		'top_stories_count'     => 6,
		'featured_videos_count' => 8,
		'recent_news_count'     => 10,
		'trending_count'        => 8,
		'show_featured_videos'  => 1,
		'show_trending'         => 1,
		'show_weather'          => 1,
		'show_crime'            => 1,
	);
}

/**
 * Return sanitized homepage controls merged with defaults.
 *
 * @return array<string,mixed>
 */
function tnf_homepage_get_settings(): array {
	$defaults = tnf_homepage_default_controls();
	$raw      = get_option(tnf_homepage_controls_option_key(), array());
	if (! is_array($raw)) {
		$raw = array();
	}

	$settings = array(
		'breaking_count'        => max(1, min(30, absint($raw['breaking_count'] ?? $defaults['breaking_count']))),
		'top_stories_count'     => max(3, min(18, absint($raw['top_stories_count'] ?? $defaults['top_stories_count']))),
		'featured_videos_count' => max(1, min(20, absint($raw['featured_videos_count'] ?? $defaults['featured_videos_count']))),
		'recent_news_count'     => max(4, min(30, absint($raw['recent_news_count'] ?? $defaults['recent_news_count']))),
		'trending_count'        => max(3, min(20, absint($raw['trending_count'] ?? $defaults['trending_count']))),
		'show_featured_videos'  => empty($raw['show_featured_videos']) ? 0 : 1,
		'show_trending'         => empty($raw['show_trending']) ? 0 : 1,
		'show_weather'          => empty($raw['show_weather']) ? 0 : 1,
		'show_crime'            => empty($raw['show_crime']) ? 0 : 1,
	);

	return $settings;
}

/**
 * Settings API: register homepage controls.
 */
function tnf_homepage_controls_register(): void {
	register_setting(
		'tnf_homepage_controls_group',
		tnf_homepage_controls_option_key(),
		array(
			'type'              => 'array',
			'sanitize_callback' => 'tnf_homepage_controls_sanitize',
			'default'           => tnf_homepage_default_controls(),
		)
	);
}

/**
 * Sanitize homepage controls option.
 *
 * @param mixed $input Option payload.
 * @return array<string,mixed>
 */
function tnf_homepage_controls_sanitize($input): array {
	$input = is_array($input) ? $input : array();

	return array(
		'breaking_count'        => max(1, min(30, absint($input['breaking_count'] ?? 12))),
		'top_stories_count'     => max(3, min(18, absint($input['top_stories_count'] ?? 6))),
		'featured_videos_count' => max(1, min(20, absint($input['featured_videos_count'] ?? 8))),
		'recent_news_count'     => max(4, min(30, absint($input['recent_news_count'] ?? 10))),
		'trending_count'        => max(3, min(20, absint($input['trending_count'] ?? 8))),
		'show_featured_videos'  => empty($input['show_featured_videos']) ? 0 : 1,
		'show_trending'         => empty($input['show_trending']) ? 0 : 1,
		'show_weather'          => empty($input['show_weather']) ? 0 : 1,
		'show_crime'            => empty($input['show_crime']) ? 0 : 1,
	);
}

/**
 * Add settings page for homepage controls.
 */
function tnf_homepage_controls_menu(): void {
	add_options_page(
		__('TNF Homepage Controls', 'tnf-news-platform'),
		__('TNF Homepage Controls', 'tnf-news-platform'),
		'manage_options',
		'tnf-homepage-controls',
		'tnf_render_homepage_controls_page'
	);
}

/**
 * Render homepage controls page.
 */
function tnf_render_homepage_controls_page(): void {
	if (! current_user_can('manage_options')) {
		return;
	}

	$opts = tnf_homepage_get_settings();
	settings_errors('tnf_templates');
	?>
	<div class="wrap">
		<h1><?php esc_html_e('TNF Homepage Controls', 'tnf-news-platform'); ?></h1>
		<p><?php esc_html_e('Control major home page sections and item counts without code changes.', 'tnf-news-platform'); ?></p>

		<?php if (get_stylesheet() === 'twentytwentyfive') : ?>
			<div class="notice notice-warning">
				<p>
					<?php
					echo wp_kses_post(
						__(
							'<strong>Use the child theme for stable layouts.</strong> Activate <strong>TNF Twenty Twenty-Five Child</strong> under Appearance → Themes so category grids, archives, and home stay the same when Twenty Twenty-Five updates.',
							'tnf-news-platform'
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields('tnf_homepage_controls_group'); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="tnf_breaking_count"><?php esc_html_e('Breaking items', 'tnf-news-platform'); ?></label></th>
					<td><input id="tnf_breaking_count" type="number" min="1" max="30" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[breaking_count]" value="<?php echo esc_attr((string) $opts['breaking_count']); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_top_stories_count"><?php esc_html_e('Top stories count', 'tnf-news-platform'); ?></label></th>
					<td><input id="tnf_top_stories_count" type="number" min="3" max="18" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[top_stories_count]" value="<?php echo esc_attr((string) $opts['top_stories_count']); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_featured_videos_count"><?php esc_html_e('Featured videos count', 'tnf-news-platform'); ?></label></th>
					<td><input id="tnf_featured_videos_count" type="number" min="1" max="20" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[featured_videos_count]" value="<?php echo esc_attr((string) $opts['featured_videos_count']); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_recent_news_count"><?php esc_html_e('Recent news count', 'tnf-news-platform'); ?></label></th>
					<td><input id="tnf_recent_news_count" type="number" min="4" max="30" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[recent_news_count]" value="<?php echo esc_attr((string) $opts['recent_news_count']); ?>" class="small-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="tnf_trending_count"><?php esc_html_e('Trending sidebar count', 'tnf-news-platform'); ?></label></th>
					<td><input id="tnf_trending_count" type="number" min="3" max="20" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[trending_count]" value="<?php echo esc_attr((string) $opts['trending_count']); ?>" class="small-text" /></td>
				</tr>
			</table>

			<h2><?php esc_html_e('Section visibility', 'tnf-news-platform'); ?></h2>
			<p>
				<label><input type="checkbox" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[show_featured_videos]" value="1" <?php checked((int) $opts['show_featured_videos'], 1); ?> /> <?php esc_html_e('Show Featured Videos section', 'tnf-news-platform'); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[show_trending]" value="1" <?php checked((int) $opts['show_trending'], 1); ?> /> <?php esc_html_e('Show Trending sidebar section', 'tnf-news-platform'); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[show_weather]" value="1" <?php checked((int) $opts['show_weather'], 1); ?> /> <?php esc_html_e('Show Weather sidebar section', 'tnf-news-platform'); ?></label><br />
				<label><input type="checkbox" name="<?php echo esc_attr(tnf_homepage_controls_option_key()); ?>[show_crime]" value="1" <?php checked((int) $opts['show_crime'], 1); ?> /> <?php esc_html_e('Show Crime News section', 'tnf-news-platform'); ?></label>
			</p>

			<?php submit_button(__('Save homepage controls', 'tnf-news-platform')); ?>
		</form>

		<hr />

		<h2><?php esc_html_e('Templates & Site Editor', 'tnf-news-platform'); ?></h2>
		<p class="description">
			<?php esc_html_e('If category or archive pages revert to an old list layout after editing in the Site Editor, WordPress saved a copy in the database that overrides theme files. Reset below to use the TNF HTML templates from your active theme again.', 'tnf-news-platform'); ?>
		</p>
		<form method="post" action="">
			<?php wp_nonce_field('tnf_purge_fse_templates_action', 'tnf_purge_fse_templates_nonce'); ?>
			<input type="hidden" name="tnf_purge_fse_templates" value="1" />
			<?php
			submit_button(
				__('Reset customized templates (use theme files)', 'tnf-news-platform'),
				'secondary',
				'submit',
				false,
				array(
					'onclick' => 'return confirm("' . esc_js(__('This removes Site Editor customizations for the active theme only. Continue?', 'tnf-news-platform')) . '");',
				)
			);
			?>
		</form>
	</div>
	<?php
}

/**
 * List-table and edit-screen actions for moderating user submissions.
 *
 * @param array<string,string> $actions Row actions.
 * @param WP_Post              $post    Post object.
 * @return array<string,string>
 */
function tnf_user_submission_row_actions(array $actions, WP_Post $post): array {
	if ($post->post_type !== 'tnf_user_submission' || ! tnf_user_can_moderate_submissions()) {
		return $actions;
	}

	$sid     = (int) $post->ID;
	$news_id = (int) get_post_meta($sid, 'tnf_promoted_news_id', true);
	$status  = (string) get_post_meta($sid, 'tnf_submission_status', true);

	if ($news_id > 0 && get_post($news_id) instanceof WP_Post) {
		$actions['tnf_view_news'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(get_edit_post_link($news_id, 'raw')),
			esc_html__('Edit published news', 'tnf-news-platform')
		);
		$view = get_permalink($news_id);
		if (is_string($view) && $view !== '') {
			$actions['tnf_view_news_front'] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url($view),
				esc_html__('View on site', 'tnf-news-platform')
			);
		}
		return $actions;
	}

	if ($status === 'rejected') {
		return $actions;
	}

	$approve_url = wp_nonce_url(
		admin_url('admin-post.php?action=tnf_approve_submission&post=' . $sid),
		'tnf_approve_submission_' . $sid
	);
	$reject_url = wp_nonce_url(
		admin_url('admin-post.php?action=tnf_reject_submission&post=' . $sid),
		'tnf_reject_submission_' . $sid
	);

	$actions['tnf_approve'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url($approve_url),
		esc_html__('Approve & publish', 'tnf-news-platform')
	);
	$confirm                = esc_js(__('Reject this submission? It will be marked as rejected and returned to draft.', 'tnf-news-platform'));
	$actions['tnf_reject'] = sprintf(
		'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
		esc_url($reject_url),
		$confirm,
		esc_html__('Reject', 'tnf-news-platform')
	);

	return $actions;
}

/**
 * Approve handler (admin-post.php).
 */
function tnf_admin_post_approve_user_submission(): void {
	if (! isset($_GET['post'])) {
		wp_die(esc_html__('Missing submission.', 'tnf-news-platform'));
	}
	$id = absint((string) wp_unslash($_GET['post']));
	if ($id <= 0) {
		wp_die(esc_html__('Invalid submission.', 'tnf-news-platform'));
	}
	check_admin_referer('tnf_approve_submission_' . $id);

	if (! tnf_user_can_moderate_submissions()) {
		wp_die(esc_html__('You do not have permission to moderate submissions.', 'tnf-news-platform'), '', array('response' => 403));
	}

	$result = tnf_user_submission_approve($id);
	if (is_wp_error($result)) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'      => 'tnf_user_submission',
					'tnf_sub_error'  => rawurlencode($result->get_error_code()),
				),
				admin_url('edit.php')
			)
		);
		exit;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'post'            => $result,
				'action'          => 'edit',
				'tnf_sub_approved' => '1',
			),
			admin_url('post.php')
		)
	);
	exit;
}

/**
 * Reject handler (admin-post.php).
 */
function tnf_admin_post_reject_user_submission(): void {
	if (! isset($_GET['post'])) {
		wp_die(esc_html__('Missing submission.', 'tnf-news-platform'));
	}
	$id = absint((string) wp_unslash($_GET['post']));
	if ($id <= 0) {
		wp_die(esc_html__('Invalid submission.', 'tnf-news-platform'));
	}
	check_admin_referer('tnf_reject_submission_' . $id);

	if (! tnf_user_can_moderate_submissions()) {
		wp_die(esc_html__('You do not have permission to moderate submissions.', 'tnf-news-platform'), '', array('response' => 403));
	}

	$result = tnf_user_submission_reject($id, '');
	if (is_wp_error($result)) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'     => 'tnf_user_submission',
					'tnf_sub_error' => rawurlencode($result->get_error_code()),
				),
				admin_url('edit.php')
			)
		);
		exit;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'       => 'tnf_user_submission',
				'tnf_sub_rejected' => '1',
			),
			admin_url('edit.php')
		)
	);
	exit;
}

/**
 * Feedback after approve / reject from the list table.
 */
function tnf_user_submission_moderation_admin_notices(): void {
	if (! is_admin() || ! tnf_user_can_moderate_submissions()) {
		return;
	}

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (! $screen) {
		return;
	}

	if ($screen->id === 'edit-tnf_user_submission') {
		if (isset($_GET['tnf_sub_rejected'])) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Submission rejected.', 'tnf-news-platform') . '</p></div>';
		}

		if (isset($_GET['tnf_sub_error'])) {
			$code = sanitize_key((string) wp_unslash($_GET['tnf_sub_error']));
			$msg  = $code === 'cannot_reject'
				? __('That submission cannot be rejected.', 'tnf-news-platform')
				: ($code === 'already_approved'
					? __('That submission was already published.', 'tnf-news-platform')
					: __('Something went wrong.', 'tnf-news-platform'));
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msg) . '</p></div>';
		}
	}

	if ($screen->base === 'post' && $screen->post_type === 'tnf_news' && isset($_GET['tnf_sub_approved'])) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('News article created from a user submission. Review categories and featured image before sharing.', 'tnf-news-platform') . '</p></div>';
	}
}

/**
 * Metabox: quick moderation links on the submission edit screen.
 */
function tnf_user_submission_register_moderation_metabox(): void {
	add_meta_box(
		'tnf_submission_moderate',
		__('Editorial review', 'tnf-news-platform'),
		'tnf_render_user_submission_moderate_metabox',
		'tnf_user_submission',
		'side',
		'high'
	);
}

/**
 * @param WP_Post $post Post object.
 */
function tnf_render_user_submission_moderate_metabox(WP_Post $post): void {
	if (! tnf_user_can_moderate_submissions()) {
		echo '<p>' . esc_html__('You do not have permission to moderate submissions.', 'tnf-news-platform') . '</p>';
		return;
	}

	$news_id = (int) get_post_meta($post->ID, 'tnf_promoted_news_id', true);
	$status  = (string) get_post_meta($post->ID, 'tnf_submission_status', true);

	if ($news_id > 0 && get_post($news_id) instanceof WP_Post) {
		echo '<p>' . esc_html__('This submission was published as a news article.', 'tnf-news-platform') . '</p>';
		printf(
			'<p><a class="button button-primary" href="%s">%s</a></p>',
			esc_url(get_edit_post_link($news_id, '')),
			esc_html__('Edit news article', 'tnf-news-platform')
		);
		$view = get_permalink($news_id);
		if (is_string($view) && $view !== '') {
			printf(
				'<p><a class="button" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url($view),
				esc_html__('View on site', 'tnf-news-platform')
			);
		}
		return;
	}

	if ($status === 'rejected') {
		$reason = (string) get_post_meta($post->ID, 'tnf_rejection_reason', true);
		echo '<p>' . esc_html__('This submission was rejected.', 'tnf-news-platform') . '</p>';
		if ($reason !== '') {
			echo '<p><strong>' . esc_html__('Note:', 'tnf-news-platform') . '</strong> ' . esc_html($reason) . '</p>';
		}
		return;
	}

	$approve_url = wp_nonce_url(
		admin_url('admin-post.php?action=tnf_approve_submission&post=' . (int) $post->ID),
		'tnf_approve_submission_' . (int) $post->ID
	);
	$reject_url = wp_nonce_url(
		admin_url('admin-post.php?action=tnf_reject_submission&post=' . (int) $post->ID),
		'tnf_reject_submission_' . (int) $post->ID
	);
	$confirm = esc_js(__('Reject this submission?', 'tnf-news-platform'));

	echo '<p>';
	printf(
		'<a class="button button-primary" href="%s">%s</a> ',
		esc_url($approve_url),
		esc_html__('Approve & publish', 'tnf-news-platform')
	);
	printf(
		'<a class="button" href="%s" onclick="return confirm(\'%s\');">%s</a>',
		esc_url($reject_url),
		$confirm,
		esc_html__('Reject', 'tnf-news-platform')
	);
	echo '</p>';
	echo '<p class="description">' . esc_html__('Approve creates a published News post from this content and archives the submission.', 'tnf-news-platform') . '</p>';
}

/**
 * User profile field for subscription gate.
 *
 * @param WP_User $user User object.
 */
function tnf_render_subscription_profile_field(WP_User $user): void {
	if (! current_user_can('edit_users')) {
		return;
	}
	$active = (bool) get_user_meta($user->ID, 'tnf_subscription_active', true);
	?>
	<h2><?php esc_html_e('TNF Subscription', 'tnf-news-platform'); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="tnf_subscription_active"><?php esc_html_e('Premium access', 'tnf-news-platform'); ?></label></th>
			<td>
				<label>
					<input type="checkbox" id="tnf_subscription_active" name="tnf_subscription_active" value="1" <?php checked($active, true); ?> />
					<?php esc_html_e('User has active subscription', 'tnf-news-platform'); ?>
				</label>
				<p class="description"><?php esc_html_e('Controls access to restricted PDF endpoints.', 'tnf-news-platform'); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Save profile subscription field.
 *
 * @param int $user_id User ID.
 */
function tnf_save_subscription_profile_field(int $user_id): void {
	if (! current_user_can('edit_user', $user_id)) {
		return;
	}
	$active = isset($_POST['tnf_subscription_active']) && $_POST['tnf_subscription_active'] === '1';
	update_user_meta($user_id, 'tnf_subscription_active', $active ? '1' : '0');
}

add_action('show_user_profile', 'tnf_render_subscription_profile_field');
add_action('edit_user_profile', 'tnf_render_subscription_profile_field');
add_action('personal_options_update', 'tnf_save_subscription_profile_field');
add_action('edit_user_profile_update', 'tnf_save_subscription_profile_field');
