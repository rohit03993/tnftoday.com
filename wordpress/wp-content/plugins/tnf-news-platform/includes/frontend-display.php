<?php
/**
 * Front-end display: chrome, singles, PDF/video embeds, archive styles.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

add_filter('the_content', 'tnf_prepend_pdf_report_viewer', 5);
add_filter('the_content', 'tnf_prepend_video_embed', 9);
add_filter('the_content', 'tnf_news_content_with_category_rail', 11);
add_filter('render_block', 'tnf_render_block_post_featured_image_tnf_cpts', 10, 2);
add_filter('render_block_core/post-featured-image', 'tnf_render_block_post_featured_image_tnf_cpts', 10, 2);
add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_chrome_styles', 12);
add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_tnf_cpt_styles', 20);

/**
 * Enqueue header/footer chrome CSS (templates using shortcodes or shared classes).
 */
function tnf_enqueue_frontend_chrome_styles(): void {
	if (is_admin()) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-chrome.css';
	if (! is_readable($path)) {
		return;
	}

	wp_enqueue_style(
		'tnf-frontend-chrome',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-chrome.css',
		array(),
		(string) filemtime($path)
	);

	$script_path = TNF_NEWS_PLATFORM_PATH . 'assets/js/frontend-chrome.js';
	if (is_readable($script_path)) {
		wp_enqueue_script(
			'tnf-frontend-chrome',
			TNF_NEWS_PLATFORM_URL . 'assets/js/frontend-chrome.js',
			array(),
			(string) filemtime($script_path),
			true
		);
	}
}

/**
 * Category archive URL respecting permalinks.
 *
 * @param string $slug Category slug.
 */
function tnf_news_category_link(string $slug): string {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return home_url('/');
	}

	$term = get_term_by('slug', $slug, 'category');
	if ($term instanceof WP_Term && ! is_wp_error($term)) {
		$link = get_term_link($term);
		if (! is_wp_error($link)) {
			return $link;
		}
	}

	return home_url('/category/' . rawurlencode($slug) . '/');
}

/**
 * Primary nav items for TNF chrome.
 *
 * @return array<int,array<string,string>>
 */
function tnf_news_nav_items(): array {
	$cats = array(
		'national'      => __('National', 'tnf-news-platform'),
		'health'        => __('Health', 'tnf-news-platform'),
		'religion'      => __('Religion', 'tnf-news-platform'),
		'entertainment' => __('Entertainment', 'tnf-news-platform'),
		'tech'          => __('Tech', 'tnf-news-platform'),
		'politics'      => __('Politics', 'tnf-news-platform'),
		'sports'        => __('Sports', 'tnf-news-platform'),
		'business'      => __('Business', 'tnf-news-platform'),
		'exclusive'     => __('Exclusive', 'tnf-news-platform'),
		'lifestyle'     => __('Lifestyle', 'tnf-news-platform'),
		'cultural'      => __('Cultural', 'tnf-news-platform'),
		'crime'         => __('Crime', 'tnf-news-platform'),
	);

	$items = array(
		array(
			'label' => __('Home', 'tnf-news-platform'),
			'url'   => home_url('/'),
		),
	);

	foreach ($cats as $slug => $label) {
		$items[] = array(
			'label' => $label,
			'url'   => tnf_news_category_link($slug),
		);
	}

	return $items;
}

/**
 * Whether a nav URL is the current request.
 *
 * @param string $url Nav href.
 */
function tnf_news_nav_url_is_current(string $url): bool {
	$path = wp_parse_url($url, PHP_URL_PATH);
	$path = is_string($path) ? trailingslashit($path) : '/';
	$req_full = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
	$req_path = wp_parse_url($req_full, PHP_URL_PATH);
	$req_path = is_string($req_path) ? trailingslashit($req_path) : '/';

	if ($path === '/' || $path === '') {
		return is_front_page();
	}

	return $path === $req_path;
}

/**
 * Breaking ticker: one marquee strip (links + separators), for duplicate seamless scroll.
 */
function tnf_news_breaking_ticker_inner_html(): string {
	$count = 14;
	if (function_exists('tnf_homepage_get_settings')) {
		$count = (int) tnf_homepage_get_settings()['breaking_count'];
	}
	$count = max(4, min(24, $count));

	$breaking = new WP_Query(
		array(
			'post_type'      => 'tnf_news',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
		)
	);

	$parts = array();
	if ($breaking->have_posts()) {
		while ($breaking->have_posts()) {
			$breaking->the_post();
			$parts[] = '<a class="tnf-breaking-link" href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>';
		}
		wp_reset_postdata();
	}

	if ($parts === array()) {
		return '';
	}

	return implode('<span class="tnf-breaking-sep" aria-hidden="true">◆</span>', $parts);
}

/**
 * @deprecated Use tnf_news_breaking_ticker_inner_html().
 */
function tnf_news_breaking_ticker_segment_html(): string {
	return tnf_news_breaking_ticker_inner_html();
}

/**
 * Register [tnf_site_header] and [tnf_site_footer].
 */
function tnf_register_site_chrome_shortcodes(): void {
	add_shortcode('tnf_site_header', 'tnf_sc_site_header');
	add_shortcode('tnf_site_footer', 'tnf_sc_site_footer');
}
add_action('init', 'tnf_register_site_chrome_shortcodes', 5);

/**
 * Shortcode: header chrome.
 *
 * @param array<string,string> $atts Attributes.
 */
function tnf_sc_site_header($atts = array()): string {
	ob_start();
	tnf_render_site_header_chrome();
	return (string) ob_get_clean();
}

/**
 * Shortcode: footer chrome.
 *
 * @param array<string,string> $atts Attributes.
 */
function tnf_sc_site_footer($atts = array()): string {
	ob_start();
	tnf_render_site_footer_chrome();
	return (string) ob_get_clean();
}

/**
 * CPT archive links for optional use.
 *
 * @return array<int,array{label:string,url:string}>
 */
function tnf_footer_content_type_links(): array {
	$links = array();

	$n = get_post_type_archive_link('tnf_news');
	if (is_string($n) && $n !== '') {
		$links[] = array(
			'label' => __('All News', 'tnf-news-platform'),
			'url'   => $n,
		);
	}

	$v = get_post_type_archive_link('tnf_video');
	if (is_string($v) && $v !== '') {
		$links[] = array(
			'label' => __('Video News', 'tnf-news-platform'),
			'url'   => $v,
		);
	}

	$p = get_post_type_archive_link('tnf_pdf_report');
	if (is_string($p) && $p !== '') {
		$links[] = array(
			'label' => __('PDF Reports', 'tnf-news-platform'),
			'url'   => $p,
		);
	}

	return $links;
}

/**
 * Eye icon SVG for views strip.
 */
function tnf_footer_views_eye_svg(): string {
	return '<svg class="tnf-footer-views__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>';
}

/**
 * Site footer (views + disclaimer + bar).
 *
 * @param bool $wrap_root_typography Add .tnf-home-news for typography hooks.
 */
function tnf_render_site_footer_chrome(bool $wrap_root_typography = true): void {
	$year  = (int) wp_date('Y');
	$name  = get_bloginfo('name');
	$root  = $wrap_root_typography ? 'tnf-site-footer tnf-home-news' : 'tnf-site-footer';
	$opts  = function_exists('tnf_footer_get_settings') ? tnf_footer_get_settings() : array();
	$show_v = ! empty($opts['show_views_bar']);
	$views  = isset($opts['total_views']) ? (int) $opts['total_views'] : 0;
	$views  = (int) apply_filters('tnf_footer_total_views', $views);
	$disc   = isset($opts['disclaimer_text']) ? (string) $opts['disclaimer_text'] : '';
	$email  = isset($opts['disclaimer_email']) ? (string) $opts['disclaimer_email'] : '';
	$creds  = isset($opts['credits_line']) ? trim((string) $opts['credits_line']) : '';
	?>
	<footer class="<?php echo esc_attr($root); ?>" role="contentinfo">
		<?php if ($show_v) : ?>
			<div class="tnf-footer-views">
				<div class="tnf-shell tnf-footer-views__inner">
					<?php echo tnf_footer_views_eye_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG ?>
					<span class="tnf-footer-views__label"><?php esc_html_e('Total Views:', 'tnf-news-platform'); ?></span>
					<span class="tnf-footer-views__count"><?php echo esc_html(number_format_i18n($views)); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<div class="tnf-footer-disclaimer">
			<div class="tnf-shell tnf-footer-disclaimer__inner">
				<p class="tnf-footer-disclaimer__intro">
					<strong><?php esc_html_e('Disclaimer :', 'tnf-news-platform'); ?></strong>
				</p>
				<?php if ($disc !== '') : ?>
					<div class="tnf-footer-disclaimer__body">
						<?php echo wp_kses_post(wpautop(trim($disc), true)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
				<?php if ($email !== '' && is_email($email)) : ?>
					<p class="tnf-footer-disclaimer__email">
						<a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="tnf-footer-bar">
			<div class="tnf-shell tnf-footer-bar__inner">
				<p class="tnf-footer-bar__copy">
					© <?php echo esc_html((string) $year); ?> <?php echo esc_html($name); ?>.
					<?php esc_html_e('All Rights Reserved', 'tnf-news-platform'); ?>
					<?php if ($creds !== '') : ?>
						<span class="tnf-footer-bar__sep" aria-hidden="true"> | </span>
						<span class="tnf-footer-bar__credits"><?php echo esc_html($creds); ?></span>
					<?php endif; ?>
				</p>
				<nav class="tnf-footer-bar__nav" aria-label="<?php esc_attr_e('Footer', 'tnf-news-platform'); ?>">
					<?php
					$fu_v = get_post_type_archive_link('tnf_video');
					$fu_e = get_post_type_archive_link('tnf_pdf_report');
					if (is_string($fu_v) && $fu_v !== '') :
						?>
					<a href="<?php echo esc_url($fu_v); ?>"><?php esc_html_e('Videos', 'tnf-news-platform'); ?></a>
						<?php
					endif;
					if (is_string($fu_e) && $fu_e !== '') :
						?>
					<a href="<?php echo esc_url($fu_e); ?>"><?php esc_html_e('ePaper', 'tnf-news-platform'); ?></a>
						<?php
					endif;
					?>
					<a href="<?php echo esc_url(home_url('/about-us/')); ?>"><?php esc_html_e('About Us', 'tnf-news-platform'); ?></a>
					<a href="<?php echo esc_url(home_url('/contact-us/')); ?>"><?php esc_html_e('Contact Us', 'tnf-news-platform'); ?></a>
					<a href="<?php echo esc_url(home_url('/terms-of-use/')); ?>"><?php esc_html_e('Terms of Use', 'tnf-news-platform'); ?></a>
					<a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>"><?php esc_html_e('Privacy Policy', 'tnf-news-platform'); ?></a>
				</nav>
				<button type="button" class="tnf-footer-backtop" aria-label="<?php esc_attr_e('Back to top', 'tnf-news-platform'); ?>" onclick="window.scrollTo({top:0,behavior:'smooth'})">
					<span aria-hidden="true">^</span>
				</button>
			</div>
		</div>
	</footer>
	<?php
}

/**
 * Site header chrome.
 *
 * @param bool $wrap_root_typography Add .tnf-home-news on wrapper.
 */
function tnf_render_site_header_chrome(bool $wrap_root_typography = true): void {
	$login_url    = wp_login_url();
	$register_url = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('register') : home_url('/register/');
	$account_url  = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('my-account') : home_url('/my-account/');
	$epaper_url   = get_post_type_archive_link('tnf_pdf_report');
	$epaper_url   = is_string($epaper_url) && $epaper_url !== '' ? $epaper_url : home_url('/pdf-reports/');
	$root_class   = $wrap_root_typography ? 'tnf-site-chrome tnf-home-news' : 'tnf-site-chrome';
	$ticker_inner = tnf_news_breaking_ticker_inner_html();
	?>
	<div class="<?php echo esc_attr($root_class); ?>">
		<div class="tnf-top-utility">
			<div class="tnf-shell">
				<div class="tnf-top-utility__left">
					<?php if (is_user_logged_in()) : ?>
						<a href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('My Account', 'tnf-news-platform'); ?></a>
						<a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Logout', 'tnf-news-platform'); ?></a>
					<?php else : ?>
						<a href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Login', 'tnf-news-platform'); ?></a>
						<a href="<?php echo esc_url($register_url); ?>"><?php esc_html_e('Register', 'tnf-news-platform'); ?></a>
					<?php endif; ?>
				</div>
				<div class="tnf-top-utility__right">
					<span><?php echo esc_html(wp_date('l, F j, Y')); ?></span>
				</div>
			</div>
		</div>

		<div class="tnf-masthead">
			<div class="tnf-shell tnf-masthead-inner">
				<div class="tnf-brand">
					<a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(get_bloginfo('name', 'display')); ?></a>
				</div>
				<div class="tnf-masthead-meta">
					<?php echo esc_html(get_bloginfo('description', 'display')); ?>
				</div>
				<div class="tnf-head-ad" aria-hidden="true"><?php esc_html_e('Advertisement', 'tnf-news-platform'); ?></div>
			</div>
		</div>

		<div class="tnf-top-nav">
			<div class="tnf-shell">
				<a class="tnf-nav-quicklink" href="<?php echo esc_url($epaper_url); ?>">
					<?php esc_html_e('ePaper', 'tnf-news-platform'); ?>
				</a>
				<button
					type="button"
					class="tnf-nav-toggle"
					aria-expanded="false"
					aria-controls="tnf-main-menu"
					aria-label="<?php esc_attr_e('Toggle sections menu', 'tnf-news-platform'); ?>"
				>
					<span class="tnf-nav-toggle__icon" aria-hidden="true"></span>
					<span class="tnf-nav-toggle__text"><?php esc_html_e('Menu', 'tnf-news-platform'); ?></span>
				</button>
				<nav id="tnf-main-menu" class="tnf-main-menu" aria-label="<?php esc_attr_e('Sections', 'tnf-news-platform'); ?>">
					<?php foreach (tnf_news_nav_items() as $item) : ?>
						<a href="<?php echo esc_url($item['url']); ?>" class="<?php echo tnf_news_nav_url_is_current($item['url']) ? 'is-active' : ''; ?>"><?php echo esc_html($item['label']); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>

		<?php if ($ticker_inner !== '') : ?>
			<div class="tnf-breaking" role="region" aria-label="<?php esc_attr_e('Breaking news ticker', 'tnf-news-platform'); ?>">
				<div class="tnf-shell tnf-breaking-inner">
					<div class="tnf-breaking-badge">
						<span class="tnf-breaking-badge__dot" aria-hidden="true"></span>
						<span class="tnf-breaking-badge__text"><?php esc_html_e('Live Breaking', 'tnf-news-platform'); ?></span>
					</div>
					<div class="tnf-breaking-viewport">
						<div class="tnf-breaking-marquee">
							<div class="tnf-breaking-marquee__strip">
								<?php echo $ticker_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
							<div class="tnf-breaking-marquee__strip" aria-hidden="true">
								<?php echo $ticker_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Validated page list from worker JSON for the e-paper viewer.
 *
 * @return array<int,array{page:int,url:string}>
 */
function tnf_pdf_report_viewer_pages(int $post_id): array {
	$raw = get_post_meta($post_id, 'tnf_pdf_pages_json', true);
	if (! is_string($raw) || $raw === '') {
		return array();
	}

	$pages = json_decode($raw, true);
	if (! is_array($pages) || $pages === array()) {
		return array();
	}

	// Accept both JSON list and keyed JSON object payloads.
	if (array_keys($pages) !== range(0, count($pages) - 1)) {
		$pages = array_values($pages);
	}

	$out = array();
	foreach ($pages as $idx => $row) {
		if (! is_array($row)) {
			continue;
		}
		$num = 0;
		if (isset($row['page'])) {
			$num = (int) $row['page'];
		} elseif (isset($row['page_num'])) {
			$num = (int) $row['page_num'];
		} elseif (isset($row['pg'])) {
			$num = (int) $row['pg'];
		}
		if ($num < 1) {
			$num = (int) $idx + 1;
		}
		$url = '';
		if (isset($row['url'])) {
			$url = esc_url_raw((string) $row['url']);
		} elseif (isset($row['image_url'])) {
			$url = esc_url_raw((string) $row['image_url']);
		} elseif (isset($row['image'])) {
			$url = esc_url_raw((string) $row['image']);
		} elseif (isset($row['src'])) {
			$url = esc_url_raw((string) $row['src']);
		} elseif (isset($row['attachment']) && is_array($row['attachment']) && isset($row['attachment']['url'])) {
			$url = esc_url_raw((string) $row['attachment']['url']);
		}
		if ($num < 1 || $url === '' || ! wp_http_validate_url($url)) {
			continue;
		}
		$out[] = array(
			'page' => $num,
			'url'  => $url,
		);
	}

	if ($out === array()) {
		return array();
	}

	usort(
		$out,
		static function (array $a, array $b): int {
			return $a['page'] <=> $b['page'];
		}
	);

	return $out;
}

/**
 * Share-by-URL bar (no download). $share_url should include ?tnf_pg= when applicable.
 */
function tnf_epaper_share_bar_html(string $share_url, string $title): string {
	$share_url   = esc_url($share_url);
	$enc_url     = rawurlencode($share_url);
	$enc_title   = rawurlencode($title);
	$html  = '<div class="tnf-epaper-share" data-share-base="' . esc_attr($share_url) . '" data-share-title="' . esc_attr($title) . '">';
	$html .= '<span class="tnf-epaper-share__label">' . esc_html__('Share', 'tnf-news-platform') . '</span>';
	$html .= '<div class="tnf-epaper-share__links">';
	$html .= '<a class="tnf-epaper-share__btn is-wa" href="https://wa.me/?text=' . $enc_title . '%20' . $enc_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on WhatsApp', 'tnf-news-platform') . '">WA</a>';
	$html .= '<a class="tnf-epaper-share__btn is-fb" href="https://www.facebook.com/sharer/sharer.php?u=' . $enc_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on Facebook', 'tnf-news-platform') . '">FB</a>';
	$html .= '<a class="tnf-epaper-share__btn is-x" href="https://twitter.com/intent/tweet?url=' . $enc_url . '&text=' . $enc_title . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on X', 'tnf-news-platform') . '">X</a>';
	$html .= '<a class="tnf-epaper-share__btn is-li" href="https://www.linkedin.com/sharing/share-offsite/?url=' . $enc_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on LinkedIn', 'tnf-news-platform') . '">in</a>';
	$html .= '<button type="button" class="tnf-epaper-share__btn is-clip" data-tnf-clip-toggle="1">' . esc_html__('Clip', 'tnf-news-platform') . '</button>';
	$html .= '<button type="button" class="tnf-epaper-share__btn is-copy" data-epaper-copy="' . esc_attr($share_url) . '">' . esc_html__('Copy link', 'tnf-news-platform') . '</button>';
	$html .= '</div></div>';

	return $html;
}

/**
 * PDF single: e-paper style viewer (thumbnails + page images + share URL). No download UI.
 *
 * @param string $content Post content.
 */
function tnf_prepend_pdf_report_viewer(string $content): string {
	if (! is_singular('tnf_pdf_report') || ! in_the_loop() || ! is_main_query()) {
		return $content;
	}

	$post_id = get_the_ID();
	if (! $post_id) {
		return $content;
	}

	$aid    = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
	$status = (string) get_post_meta($post_id, 'tnf_pdf_status', true);
	$url    = '';
	if ($aid) {
		$u = wp_get_attachment_url($aid);
		$url = is_string($u) ? $u : '';
	}

	$title   = get_the_title($post_id);
	$date_s  = get_the_date('', $post_id);
	$viewer_pages = tnf_pdf_report_viewer_pages($post_id);
	$n_pages = count($viewer_pages);

	$req_pg = isset($_GET['tnf_pg']) ? absint((string) wp_unslash($_GET['tnf_pg'])) : 0;
	if ($req_pg < 1) {
		$req_pg = 1;
	}
	if ($n_pages > 0 && $req_pg > $n_pages) {
		$req_pg = $n_pages;
	}

	$share_url = $n_pages > 0
		? add_query_arg('tnf_pg', $req_pg, get_permalink($post_id))
		: get_permalink($post_id);

	$out = '';

	if ($status === 'failed') {
		$err = (string) get_post_meta($post_id, 'tnf_pdf_error', true);
		$msg = $err !== '' ? $err : __('PDF processing failed.', 'tnf-news-platform');
		$out .= '<div class="tnf-pdf-report-panel"><p class="tnf-pdf-report-panel__status is-error">' . esc_html($msg) . '</p></div>';
		return $out . $content;
	}

	if ($url === '' && in_array($status, array( 'queued', 'processing', 'idle' ), true)) {
		$out .= '<div class="tnf-pdf-report-panel"><p class="tnf-pdf-report-panel__status">' . esc_html__('PDF is processing. Please check back shortly.', 'tnf-news-platform') . '</p></div>';
		return $out . $content;
	}

	if ($url === '') {
		$out .= '<div class="tnf-pdf-report-panel"><p class="tnf-pdf-report-panel__status">' . esc_html__('No PDF file is attached to this report.', 'tnf-news-platform') . '</p></div>';
		return $out . $content;
	}

	// Rendered pages: full e-paper UI (reference-style).
	if ($n_pages > 0) {
		$initial = null;
		foreach ($viewer_pages as $row) {
			if ((int) $row['page'] === $req_pg) {
				$initial = $row;
				break;
			}
		}
		if ($initial === null) {
			$initial = $viewer_pages[0];
			$req_pg  = (int) $initial['page'];
			$share_url = add_query_arg('tnf_pg', $req_pg, get_permalink($post_id));
		}

		$idx_one_based = 1;
		foreach ($viewer_pages as $i => $row) {
			if ((int) $row['page'] === $req_pg) {
				$idx_one_based = $i + 1;
				break;
			}
		}

		$json = wp_json_encode(
			$viewer_pages,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
		);

		$out .= '<div class="tnf-epaper" data-tnf-epaper="1" data-tnf-permalink="' . esc_url(get_permalink($post_id)) . '">';
		$out .= '<header class="tnf-epaper__masthead">';
		$out .= '<h1 class="tnf-epaper__title">' . esc_html($title) . '</h1>';
		$out .= '<p class="tnf-epaper__date">' . esc_html($date_s) . '</p>';
		$out .= '</header>';

		$out .= '<div class="tnf-epaper__toolbar">';
		$out .= '<div class="tnf-epaper__toolbar-left">';
		$out .= '<label class="tnf-epaper__select-wrap"><span class="tnf-epaper__select-label">' . esc_html__('Page', 'tnf-news-platform') . '</span>';
		$out .= '<select class="tnf-epaper__select" aria-label="' . esc_attr__('Select page', 'tnf-news-platform') . '">';
		foreach ($viewer_pages as $row) {
			$p = (int) $row['page'];
			$out .= '<option value="' . esc_attr((string) $p) . '"' . selected($p, $req_pg, false) . '>';
			$out .= esc_html(sprintf(/* translators: %d: page number */ __('Page %d', 'tnf-news-platform'), $p));
			$out .= '</option>';
		}
		$out .= '</select></label>';
		$out .= '<nav class="tnf-epaper__pager" aria-label="' . esc_attr__('Page navigation', 'tnf-news-platform') . '">';
		$out .= '<button type="button" class="tnf-epaper__nav-btn tnf-epaper__nav-btn--prev" data-tnf-epaper-prev aria-label="' . esc_attr__('Previous page', 'tnf-news-platform') . '">‹</button>';
		$out .= '<span class="tnf-epaper__pager-indicator"><span data-tnf-epaper-current>' . esc_html((string) $idx_one_based) . '</span> / <span data-tnf-epaper-total>' . esc_html((string) $n_pages) . '</span></span>';
		$out .= '<button type="button" class="tnf-epaper__nav-btn tnf-epaper__nav-btn--next" data-tnf-epaper-next aria-label="' . esc_attr__('Next page', 'tnf-news-platform') . '">›</button>';
		$out .= '</nav>';
		$out .= '<nav class="tnf-epaper__number-pager" aria-label="' . esc_attr__('Page number switcher', 'tnf-news-platform') . '" data-tnf-number-pager></nav>';
		$out .= '<div class="tnf-epaper__toolbar-page-status" data-tnf-page-status>' . esc_html(sprintf(__('Page %1$d of %2$d', 'tnf-news-platform'), $req_pg, $n_pages)) . '</div>';
		$out .= '</div>';
		$out .= '<div class="tnf-epaper__toolbar-right">';
		$out .= '<span class="tnf-epaper__zoom-label">' . esc_html__('Zoom', 'tnf-news-platform') . '</span>';
		$out .= '<div class="tnf-epaper__zoom-controls" role="group" aria-label="' . esc_attr__('Zoom controls', 'tnf-news-platform') . '">';
		$out .= '<button type="button" class="tnf-epaper__zoom-btn" data-tnf-zoom-out aria-label="' . esc_attr__('Zoom out', 'tnf-news-platform') . '">−</button>';
		$out .= '<button type="button" class="tnf-epaper__zoom-btn tnf-epaper__zoom-btn--value" data-tnf-zoom-reset aria-label="' . esc_attr__('Reset zoom', 'tnf-news-platform') . '"><span data-tnf-zoom-value>100%</span></button>';
		$out .= '<button type="button" class="tnf-epaper__zoom-btn" data-tnf-zoom-in aria-label="' . esc_attr__('Zoom in', 'tnf-news-platform') . '">+</button>';
		$out .= '</div>';
		$out .= '</div>';
		$out .= '</div>';

		$out .= tnf_epaper_share_bar_html($share_url, $title);

		$out .= '<div class="tnf-epaper__body">';
		$out .= '<aside class="tnf-epaper__sidebar" aria-label="' . esc_attr__('Page thumbnails', 'tnf-news-platform') . '">';
		foreach ($viewer_pages as $row) {
			$p   = (int) $row['page'];
			$u   = $row['url'];
			$sel = ($p === $req_pg) ? ' is-active' : '';
			$out .= '<button type="button" class="tnf-epaper__thumb' . $sel . '" data-tnf-page="' . esc_attr((string) $p) . '" aria-label="' . esc_attr(sprintf(/* translators: %d: page number */ __('Page %d', 'tnf-news-platform'), $p)) . '"' . ($p === $req_pg ? ' aria-current="page"' : '') . '>';
			$out .= '<span class="tnf-epaper__thumb-img-wrap"><img src="' . esc_url($u) . '" alt="" loading="lazy" decoding="async" width="120" height="160" /></span>';
			$out .= '<span class="tnf-epaper__thumb-label">' . esc_html(sprintf(/* translators: %d: page number */ __('Page %d', 'tnf-news-platform'), $p)) . '</span>';
			$out .= '</button>';
		}
		$out .= '</aside>';

		$out .= '<div class="tnf-epaper__stage">';
		$out .= '<figure class="tnf-epaper__figure">';
		$out .= '<img class="tnf-epaper__page-img" data-tnf-epaper-main src="' . esc_url($initial['url']) . '" alt="' . esc_attr($title) . '" />';
		$out .= '</figure>';
		$out .= '<nav class="tnf-epaper__mobile-bar" aria-label="' . esc_attr__('Page navigation', 'tnf-news-platform') . '">';
		$out .= '<button type="button" class="tnf-epaper__nav-btn tnf-epaper__nav-btn--prev" data-tnf-epaper-prev-mobile>‹ ' . esc_html__('Prev', 'tnf-news-platform') . '</button>';
		$out .= '<span class="tnf-epaper__mobile-status"><span data-tnf-epaper-current-mobile>' . esc_html((string) $idx_one_based) . '</span> / <span data-tnf-epaper-total-mobile>' . esc_html((string) $n_pages) . '</span></span>';
		$out .= '<button type="button" class="tnf-epaper__nav-btn tnf-epaper__nav-btn--next" data-tnf-epaper-next-mobile>' . esc_html__('Next', 'tnf-news-platform') . ' ›</button>';
		$out .= '</nav>';
		$out .= '</div></div>';

		$out .= '<script type="application/json" id="tnf-epaper-pages">' . $json . '</script>';
		$out .= '<p class="tnf-epaper__note">' . esc_html__('Tip: Share this page URL to open this edition on the same page.', 'tnf-news-platform') . '</p>';
		$out .= '</div>';

		return $out . $content;
	}

	// Fallback: in-page PDF.js reader (whole-page scroll, left thumbnails, page buttons, zoom).
	$out .= '<div class="tnf-epaper tnf-epaper--pdfjs" data-tnf-pdfjs="1" data-tnf-pdf-url="' . esc_url($url) . '" data-tnf-permalink="' . esc_url(get_permalink($post_id)) . '">';
	$out .= '<header class="tnf-epaper__masthead">';
	$out .= '<h1 class="tnf-epaper__title">' . esc_html($title) . '</h1>';
	$out .= '<p class="tnf-epaper__date">' . esc_html($date_s) . '</p>';
	$out .= '</header>';
	$out .= '<div class="tnf-epaper__toolbar">';
	$out .= '<div class="tnf-epaper__toolbar-left">';
	$out .= '<nav class="tnf-epaper__pager" aria-label="' . esc_attr__('Page navigation', 'tnf-news-platform') . '">';
	$out .= '<button type="button" class="tnf-epaper__nav-btn" data-tnf-pdfjs-prev aria-label="' . esc_attr__('Previous page', 'tnf-news-platform') . '">‹</button>';
	$out .= '<span class="tnf-epaper__pager-indicator"><span data-tnf-pdfjs-current>1</span> / <span data-tnf-pdfjs-total>1</span></span>';
	$out .= '<button type="button" class="tnf-epaper__nav-btn" data-tnf-pdfjs-next aria-label="' . esc_attr__('Next page', 'tnf-news-platform') . '">›</button>';
	$out .= '</nav>';
	$out .= '<nav class="tnf-epaper__number-pager" data-tnf-pdfjs-number-pager></nav>';
	$out .= '<div class="tnf-epaper__toolbar-page-status" data-tnf-pdfjs-status>' . esc_html__('Page 1 of 1', 'tnf-news-platform') . '</div>';
	$out .= '</div>';
	$out .= '<div class="tnf-epaper__toolbar-right">';
	$out .= '<label class="tnf-epaper__select-wrap"><span class="tnf-epaper__select-label">' . esc_html__('Zoom', 'tnf-news-platform') . '</span>';
	$out .= '<select class="tnf-epaper__select" data-tnf-pdfjs-zoom>';
	$out .= '<option value="0.5">50%</option><option value="0.8">80%</option><option value="1" selected>100%</option><option value="1.25">125%</option><option value="1.5">150%</option>';
	$out .= '</select></label>';
	$out .= '</div></div>';
	$out .= tnf_epaper_share_bar_html(get_permalink($post_id), $title);
	$out .= '<div class="tnf-epaper__body">';
	$out .= '<aside class="tnf-epaper__sidebar" data-tnf-pdfjs-thumbs aria-label="' . esc_attr__('Page thumbnails', 'tnf-news-platform') . '"></aside>';
	$out .= '<div class="tnf-epaper__stage">';
	$out .= '<figure class="tnf-epaper__figure tnf-epaper__figure--pdfjs">';
	$out .= '<canvas class="tnf-epaper__pdfjs-canvas" data-tnf-pdfjs-canvas></canvas>';
	$out .= '</figure>';
	$out .= '<nav class="tnf-epaper__mobile-bar" aria-label="' . esc_attr__('Page navigation', 'tnf-news-platform') . '">';
	$out .= '<button type="button" class="tnf-epaper__nav-btn tnf-epaper__nav-btn--prev" data-tnf-pdfjs-prev-mobile>‹ ' . esc_html__('Prev', 'tnf-news-platform') . '</button>';
	$out .= '<span class="tnf-epaper__mobile-status"><span data-tnf-pdfjs-current-mobile>1</span> / <span data-tnf-pdfjs-total-mobile>1</span></span>';
	$out .= '<button type="button" class="tnf-epaper__nav-btn tnf-epaper__nav-btn--next" data-tnf-pdfjs-next-mobile>' . esc_html__('Next', 'tnf-news-platform') . ' ›</button>';
	$out .= '</nav>';
	$out .= '</div></div>';
	$out .= '<p class="tnf-epaper__note">' . esc_html__('Use left thumbnails or number buttons to switch pages. Mouse wheel scroll moves the whole page.', 'tnf-news-platform') . '</p>';
	$out .= '</div>';
	if (current_user_can('edit_posts')) {
		$out .= '<p class="tnf-pdf-report-panel__embed-hint"><strong>' . esc_html__('Debug:', 'tnf-news-platform') . '</strong> '
			. esc_html(sprintf(__('status=%1$s, pages_detected=%2$d', 'tnf-news-platform'), $status !== '' ? $status : 'n/a', $n_pages))
			. '</p>';
	}

	return $out . $content;
}

/**
 * Video single: oEmbed from metabox URL.
 *
 * @param string $content Post content.
 */
function tnf_prepend_video_embed(string $content): string {
	if (! is_singular('tnf_video')) {
		return $content;
	}

	$url = (string) get_post_meta(get_the_ID(), 'tnf_embed_url', true);
	if ($url === '') {
		return $content;
	}

	$embed = wp_oembed_get($url, array( 'width' => 1280 ));
	if (! $embed || ! is_string($embed)) {
		return $content;
	}

	return '<div class="tnf-video-embed wp-block-embed is-type-video">' . $embed . '</div>' . $content;
}

/**
 * Extract YouTube video id from common URL shapes.
 */
function tnf_youtube_id_from_url(string $url): string {
	if ($url === '') {
		return '';
	}
	if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/|m\.youtube\.com/watch\?v=)([A-Za-z0-9_-]{6,})#', $url, $m)) {
		return $m[1];
	}

	return '';
}

/**
 * Best thumbnail URL for video cards (featured image, else YouTube poster from embed URL / content).
 */
function tnf_video_card_thumbnail_url(int $post_id): string {
	if ($post_id <= 0) {
		return '';
	}

	$feat = get_the_post_thumbnail_url($post_id, 'medium_large');
	if (is_string($feat) && $feat !== '') {
		return $feat;
	}

	$embed = (string) get_post_meta($post_id, 'tnf_embed_url', true);
	$yt    = tnf_youtube_id_from_url($embed);
	if ($yt !== '') {
		return 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
	}

	$content = (string) get_post_field('post_content', $post_id);
	if ($content !== '' && preg_match('#https?://[^\s"\'<>]+#i', $content, $m)) {
		$yt = tnf_youtube_id_from_url($m[0]);
		if ($yt !== '') {
			return 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
		}
	}

	return '';
}

/**
 * First page image URL from PDF worker manifest (presigned; may expire).
 */
function tnf_pdf_report_first_page_thumbnail_url(int $post_id): string {
	$pages = tnf_pdf_report_viewer_pages($post_id);
	if ($pages === array()) {
		return '';
	}
	return (string) $pages[0]['url'];
}

/**
 * WordPress-generated preview image for a PDF attachment (if available).
 */
function tnf_pdf_attachment_preview_image_url(int $attachment_id): string {
	if ($attachment_id <= 0) {
		return '';
	}
	if (get_post_mime_type($attachment_id) !== 'application/pdf') {
		return '';
	}

	foreach (array( 'large', 'medium_large', 'medium', 'full' ) as $size) {
		$u = wp_get_attachment_image_url($attachment_id, $size);
		if (is_string($u) && $u !== '') {
			return $u;
		}
	}

	return '';
}

/**
 * Card image for tnf_pdf_report archives: rendered page 1, then WP PDF preview, else let core handle featured image.
 */
function tnf_pdf_report_archive_card_image_url(int $post_id): string {
	$aid = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
	$preview = tnf_pdf_attachment_preview_image_url($aid);
	if ($preview !== '') {
		return $preview;
	}

	$from_job = tnf_pdf_report_first_page_thumbnail_url($post_id);
	if ($from_job !== '') {
		return $from_job;
	}

	if (has_post_thumbnail($post_id)) {
		return '';
	}

	return '';
}

/**
 * Resolve post ID for block-render filters (query loop context first, global loop fallback).
 *
 * @param array<string,mixed> $block Parsed block.
 */
function tnf_block_context_post_id(array $block): int {
	if (isset($block['context']['postId'])) {
		$ctx_id = (int) $block['context']['postId'];
		if ($ctx_id > 0) {
			return $ctx_id;
		}
	}

	return (int) get_the_ID();
}

/**
 * Build featured-image block markup for a resolved thumbnail URL.
 *
 * @param array<string,mixed> $block Parsed block.
 */
function tnf_post_featured_image_figure_html(int $post_id, array $block, string $thumb_url): string {
	$attrs   = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
	$is_link = ! empty($attrs['isLink']);
	$aspect  = isset($attrs['aspectRatio']) ? (string) $attrs['aspectRatio'] : '';
	$scale   = isset($attrs['scale']) ? (string) $attrs['scale'] : 'cover';
	if (! in_array($scale, array( 'cover', 'contain', 'fill', 'none' ), true)) {
		$scale = 'cover';
	}

	$radius = '';
	if (isset($attrs['style']['border']['radius'])) {
		$radius = is_string($attrs['style']['border']['radius']) ? $attrs['style']['border']['radius'] : '';
	}

	$figure_style = 'overflow:hidden;width:100%;';
	if ($aspect !== '') {
		$figure_style .= 'aspect-ratio:' . $aspect . ';';
	}
	if ($radius !== '') {
		$figure_style .= 'border-radius:' . $radius . ';';
	}

	$title = get_the_title($post_id);
	$img   = sprintf(
		'<img src="%s" alt="%s" class="wp-post-image" loading="lazy" decoding="async" style="width:100%%;height:100%%;object-fit:%s;display:block;" />',
		esc_url($thumb_url),
		esc_attr($title),
		esc_attr($scale)
	);

	$inner = $img;
	if ($is_link) {
		$inner = '<a href="' . esc_url(get_permalink($post_id)) . '">' . $img . '</a>';
	}

	$extra   = isset($attrs['className']) ? trim((string) $attrs['className']) : '';
	$classes = trim('wp-block-post-featured-image' . ($extra !== '' ? ' ' . $extra : ''));

	return '<figure class="' . esc_attr($classes) . '" style="' . esc_attr($figure_style) . '">' . $inner . '</figure>';
}

/**
 * Build PDF page-1 iframe fallback for archive cards when image thumbnails are unavailable.
 *
 * @param array<string,mixed> $block Parsed block.
 */
function tnf_pdf_report_iframe_fallback_figure_html(int $post_id, array $block): string {
	$aid = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
	if ($aid <= 0) {
		return '';
	}
	$pdf_url = wp_get_attachment_url($aid);
	if (! is_string($pdf_url) || $pdf_url === '') {
		return '';
	}

	$attrs   = isset($block['attrs']) && is_array($block['attrs']) ? $block['attrs'] : array();
	$is_link = ! empty($attrs['isLink']);
	$aspect  = isset($attrs['aspectRatio']) ? (string) $attrs['aspectRatio'] : '16/9';

	$radius = '';
	if (isset($attrs['style']['border']['radius'])) {
		$radius = is_string($attrs['style']['border']['radius']) ? $attrs['style']['border']['radius'] : '';
	}

	$figure_style = 'overflow:hidden;width:100%;background:#fff;';
	if ($aspect !== '') {
		$figure_style .= 'aspect-ratio:' . $aspect . ';';
	}
	if ($radius !== '') {
		$figure_style .= 'border-radius:' . $radius . ';';
	}

	$iframe_src = $pdf_url . '#page=1&view=FitH&toolbar=0&navpanes=0&scrollbar=0';
	$iframe     = '<iframe class="tnf-pdf-card-iframe" src="' . esc_url($iframe_src) . '" title="' . esc_attr__('PDF first page preview', 'tnf-news-platform') . '" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>';
	$inner      = '<span class="tnf-pdf-card-iframe-wrap">' . $iframe . '</span>';
	if ($is_link) {
		$inner = '<a href="' . esc_url(get_permalink($post_id)) . '" aria-label="' . esc_attr(get_the_title($post_id)) . '">' . $inner . '</a>';
	}

	$extra   = isset($attrs['className']) ? trim((string) $attrs['className']) : '';
	$classes = trim('wp-block-post-featured-image tnf-pdf-card-fallback' . ($extra !== '' ? ' ' . $extra : ''));

	return '<figure class="' . esc_attr($classes) . '" style="' . esc_attr($figure_style) . '">' . $inner . '</figure>';
}

/**
 * Query-loop cards: video YouTube/featured fallbacks; PDF first-page / preview fallbacks.
 *
 * @param string               $block_content Rendered block HTML.
 * @param array<string,mixed> $block         Parsed block.
 */
function tnf_render_block_post_featured_image_tnf_cpts(string $block_content, array $block): string {
	if (($block['blockName'] ?? '') !== 'core/post-featured-image') {
		return $block_content;
	}

	$post_id = tnf_block_context_post_id($block);
	if ($post_id <= 0) {
		return $block_content;
	}

	$type = get_post_type($post_id);
	if ($type === 'tnf_video') {
		if (has_post_thumbnail($post_id)) {
			return $block_content;
		}
		$thumb = tnf_video_card_thumbnail_url($post_id);
		if ($thumb === '') {
			return $block_content;
		}

		return tnf_post_featured_image_figure_html($post_id, $block, $thumb);
	}

	if ($type === 'tnf_pdf_report') {
		$thumb = tnf_pdf_report_archive_card_image_url($post_id);
		if ($thumb !== '') {
			return tnf_post_featured_image_figure_html($post_id, $block, $thumb);
		}

		$fallback = tnf_pdf_report_iframe_fallback_figure_html($post_id, $block);
		if ($fallback !== '') {
			return $fallback;
		}

		return $block_content;
	}

	return $block_content;
}

/**
 * News single: category rail, related, share bar.
 *
 * @param string $content Post content.
 */
function tnf_news_content_with_category_rail(string $content): string {
	if (! is_singular('tnf_news') || ! in_the_loop() || ! is_main_query()) {
		return $content;
	}

	$post_id = get_the_ID();
	if (! $post_id) {
		return $content;
	}

	$terms = get_the_terms($post_id, 'category');
	$items = '';
	$inline_cats = '';
	if (is_array($terms) && ! is_wp_error($terms)) {
		foreach ($terms as $term) {
			$link = get_term_link($term);
			if (is_wp_error($link)) {
				continue;
			}
			$items .= '<li><a href="' . esc_url((string) $link) . '">' . esc_html($term->name) . '</a></li>';
			$inline_cats .= '<a href="' . esc_url((string) $link) . '">' . esc_html($term->name) . '</a> ';
		}
	}

	if ($items === '') {
		$items = '<li><span>' . esc_html__('Uncategorized', 'tnf-news-platform') . '</span></li>';
	}

	$rail  = '<aside class="tnf-news-meta-rail">';
	$rail .= '<h4>' . esc_html__('Categories', 'tnf-news-platform') . '</h4>';
	$rail .= '<ul>' . $items . '</ul>';
	$rail .= '</aside>';

	$meta  = '<div class="tnf-news-meta-head">';
	$meta .= '<div class="tnf-news-meta-head__crumbs"><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Home', 'tnf-news-platform') . '</a> <span>/</span> <span>' . esc_html__('News', 'tnf-news-platform') . '</span></div>';
	$meta .= '<div class="tnf-news-meta-head__row">';
	$meta .= '<time datetime="' . esc_attr(get_the_date(DATE_W3C, $post_id)) . '">' . esc_html(get_the_date('', $post_id)) . '</time>';
	if ($inline_cats !== '') {
		$meta .= '<span class="tnf-news-meta-head__cats">' . $inline_cats . '</span>';
	}
	$meta .= '</div></div>';

	$related_html = '';
	$term_ids     = array();
	if (is_array($terms) && ! is_wp_error($terms)) {
		foreach ($terms as $term) {
			$term_ids[] = (int) $term->term_id;
		}
	}

	$related_q = new WP_Query(
		array(
			'post_type'           => 'tnf_news',
			'post_status'         => 'publish',
			'posts_per_page'      => 4,
			'post__not_in'        => array((int) $post_id),
			'ignore_sticky_posts' => true,
			'category__in'        => $term_ids,
		)
	);

	if ($related_q->have_posts()) {
		$related_html .= '<section class="tnf-news-related">';
		$related_html .= '<h3>' . esc_html__('Related News', 'tnf-news-platform') . '</h3>';
		$related_html .= '<div class="tnf-news-related-grid">';
		while ($related_q->have_posts()) {
			$related_q->the_post();
			$thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium_large');
			if (! is_string($thumb) || $thumb === '') {
				$thumb = 'https://picsum.photos/seed/tnf-related-' . get_the_ID() . '/640/360';
			}
			$related_html .= '<article class="tnf-news-related-card">';
			$related_html .= '<a class="tnf-news-related-card__thumb" href="' . esc_url(get_permalink()) . '"><img src="' . esc_url($thumb) . '" alt="' . esc_attr(get_the_title()) . '" loading="lazy" /></a>';
			$related_html .= '<h4><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h4>';
			$related_html .= '</article>';
		}
		$related_html .= '</div></section>';
		wp_reset_postdata();
	}

	$permalink     = get_permalink($post_id);
	$title         = get_the_title($post_id);
	$encoded_url   = rawurlencode((string) $permalink);
	$encoded_title = rawurlencode((string) $title);

	$share  = '<div class="tnf-share-bar" data-share-url="' . esc_attr((string) $permalink) . '">';
	$share .= '<a class="tnf-share-btn is-wa" href="https://wa.me/?text=' . $encoded_title . '%20' . $encoded_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on WhatsApp', 'tnf-news-platform') . '">WA</a>';
	$share .= '<a class="tnf-share-btn is-fb" href="https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on Facebook', 'tnf-news-platform') . '">FB</a>';
	$share .= '<a class="tnf-share-btn is-x" href="https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on X', 'tnf-news-platform') . '">X</a>';
	$share .= '<button type="button" class="tnf-share-btn is-copy" data-copy-link="' . esc_attr((string) $permalink) . '">' . esc_html__('Copy', 'tnf-news-platform') . '</button>';
	$share .= '</div>';

	$copy_js = '<script>(function(){var b=document.querySelector(".tnf-share-btn.is-copy");if(!b){return;}b.addEventListener("click",function(){var u=b.getAttribute("data-copy-link")||"";if(!u){return;}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(function(){b.textContent="Copied";setTimeout(function(){b.textContent="Copy";},1600);});}else{var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();try{document.execCommand("copy");b.textContent="Copied";setTimeout(function(){b.textContent="Copy";},1600);}catch(e){}document.body.removeChild(t);}});})();</script>';

	return $share . '<div class="tnf-news-content-layout">' . $rail . '<div class="tnf-news-content-body">' . $meta . $content . $related_html . '</div></div>' . $copy_js;
}

/**
 * Enqueue single styles for TNF CPTs.
 */
function tnf_enqueue_frontend_tnf_cpt_styles(): void {
	if (is_admin()) {
		return;
	}

	$single_css = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-single-news.css';
	if (
		is_singular('tnf_news')
		|| is_singular('tnf_video')
		|| is_singular('tnf_pdf_report')
	) {
		if (is_readable($single_css)) {
			wp_enqueue_style(
				'tnf-single-news',
				TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-single-news.css',
				array(),
				(string) filemtime($single_css)
			);
		}
	}

	if (is_singular('tnf_pdf_report')) {
		$pdf_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-pdf-report.css';
		if (is_readable($pdf_path)) {
			wp_enqueue_style(
				'tnf-pdf-report',
				TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-pdf-report.css',
				array('tnf-single-news'),
				(string) filemtime($pdf_path)
			);
		}

		$epaper_js = TNF_NEWS_PLATFORM_PATH . 'assets/js/tnf-epaper-viewer.js';
		if (is_readable($epaper_js)) {
			wp_enqueue_script(
				'tnf-pdfjs-core',
				'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js',
				array(),
				'3.11.174',
				true
			);
			wp_enqueue_script(
				'tnf-epaper-viewer',
				TNF_NEWS_PLATFORM_URL . 'assets/js/tnf-epaper-viewer.js',
				array('tnf-pdfjs-core'),
				(string) filemtime($epaper_js),
				true
			);
			wp_localize_script(
				'tnf-epaper-viewer',
				'tnfEpaperL10n',
				array(
					'copied' => __('Copied!', 'tnf-news-platform'),
					'pdfjsWorkerSrc' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
				)
			);
		}
	}
}
