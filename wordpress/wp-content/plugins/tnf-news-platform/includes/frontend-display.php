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
add_filter('the_content', 'tnf_prepend_news_embed', 8);
add_filter('the_content', 'tnf_prepend_video_embed', 9);
add_filter('the_content', 'tnf_news_content_with_category_rail', 11);
add_filter('the_content', 'tnf_video_single_append_related', 12);
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
			'post_type'      => tnf_listing_news_post_types(),
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
	static $rendered = false;
	if ($rendered) {
		return '';
	}
	$rendered = true;
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
 * Credits line for footer bar: replaces the word "Love" with an inline heart icon.
 *
 * @param string $credits_line Value from TNF Footer settings.
 */
function tnf_footer_credits_line_html(string $credits_line): string {
	$credits_line = trim($credits_line);
	if ($credits_line === '') {
		return '';
	}

	$heart = '<span class="tnf-footer-bar__heart" role="img" aria-label="' . esc_attr__('love', 'tnf-news-platform') . '">♥</span>';

	if (! preg_match('/\bLove\b/i', $credits_line)) {
		return esc_html($credits_line);
	}

	$parts = preg_split('/\bLove\b/i', $credits_line, -1);
	$out   = '';
	$n     = count($parts);
	foreach ($parts as $i => $part) {
		$out .= esc_html($part);
		if ($i < $n - 1) {
			$out .= $heart;
		}
	}

	return wp_kses(
		$out,
		array(
			'span' => array(
				'class'      => true,
				'role'       => true,
				'aria-label' => true,
			),
		)
	);
}

/**
 * Site footer (disclaimer + bar).
 *
 * @param bool $wrap_root_typography Add .tnf-home-news for typography hooks.
 */
function tnf_render_site_footer_chrome(bool $wrap_root_typography = true): void {
	$year  = (int) wp_date('Y');
	$name  = trim((string) get_bloginfo('name'));
	if ($name === '') {
		$name = 'TNF Today';
	}
	$root  = $wrap_root_typography ? 'tnf-site-footer tnf-home-news' : 'tnf-site-footer';
	$opts  = function_exists('tnf_footer_get_settings') ? tnf_footer_get_settings() : array();
	$disc  = isset($opts['disclaimer_text']) ? (string) $opts['disclaimer_text'] : '';
	$email = isset($opts['disclaimer_email']) ? (string) $opts['disclaimer_email'] : '';
	$creds = isset($opts['credits_line']) ? trim((string) $opts['credits_line']) : '';
	?>
	<footer class="<?php echo esc_attr($root); ?>" role="contentinfo">
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
						<span class="tnf-footer-bar__credits"><?php echo tnf_footer_credits_line_html($creds); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tnf_footer_credits_line_html ?></span>
					<?php endif; ?>
				</p>
				<nav class="tnf-footer-bar__nav" aria-label="<?php esc_attr_e('Footer', 'tnf-news-platform'); ?>">
					<?php
					$fu_v = get_post_type_archive_link('tnf_video');
					$fu_e = get_post_type_archive_link('tnf_pdf_report');
					$footer_nav = array();
					if (is_string($fu_v) && $fu_v !== '') {
						$footer_nav[] = array(
							'url'   => $fu_v,
							'label' => __('Videos', 'tnf-news-platform'),
						);
					}
					if (is_string($fu_e) && $fu_e !== '') {
						$footer_nav[] = array(
							'url'   => $fu_e,
							'label' => __('ePaper', 'tnf-news-platform'),
						);
					}
					$footer_nav[] = array( 'url' => home_url('/about-us/'), 'label' => __('About Us', 'tnf-news-platform') );
					$footer_nav[] = array( 'url' => home_url('/contact-us/'), 'label' => __('Contact Us', 'tnf-news-platform') );
					$footer_nav[] = array( 'url' => home_url('/terms-of-use/'), 'label' => __('Terms of Use', 'tnf-news-platform') );
					$footer_nav[] = array( 'url' => home_url('/privacy-policy/'), 'label' => __('Privacy Policy', 'tnf-news-platform') );
					foreach ($footer_nav as $i => $item) {
						if ($i > 0) {
							echo '<span class="tnf-footer-bar__pipe" aria-hidden="true"> | </span>';
						}
						echo '<a href="' . esc_url($item['url']) . '">' . esc_html($item['label']) . '</a>';
					}
					?>
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
	static $rendered = false;
	if ($rendered) {
		return;
	}
	$rendered = true;

	$login_url    = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('login') : home_url('/login/');
	$register_url = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('register') : home_url('/register/');
	$account_url  = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('my-account') : home_url('/my-account/');
	$epaper_url   = get_post_type_archive_link('tnf_pdf_report');
	$epaper_url   = is_string($epaper_url) && $epaper_url !== '' ? $epaper_url : home_url('/pdf-reports/');
	$videos_url   = get_post_type_archive_link('tnf_video');
	$videos_url   = is_string($videos_url) && $videos_url !== '' ? $videos_url : '';
	$root_class   = $wrap_root_typography ? 'tnf-site-chrome tnf-home-news' : 'tnf-site-chrome';
	$ticker_inner = tnf_news_breaking_ticker_inner_html();
	?>
	<div class="<?php echo esc_attr($root_class); ?>">
		<div class="tnf-top-utility">
			<div class="tnf-shell">
				<div class="tnf-top-utility__left">
					<?php if ($videos_url !== '') : ?>
						<a href="<?php echo esc_url($videos_url); ?>"><?php esc_html_e('Videos', 'tnf-news-platform'); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url($epaper_url); ?>"><?php esc_html_e('ePaper', 'tnf-news-platform'); ?></a>
					<a href="<?php echo esc_url(home_url('/about-us/')); ?>"><?php esc_html_e('About Us', 'tnf-news-platform'); ?></a>
					<a href="<?php echo esc_url(home_url('/contact-us/')); ?>"><?php esc_html_e('Contact Us', 'tnf-news-platform'); ?></a>
					<a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>"><?php esc_html_e('Privacy Policy', 'tnf-news-platform'); ?></a>
				</div>
				<div class="tnf-top-utility__right">
					<span><?php echo esc_html(wp_date('l, d M Y')); ?></span>
				</div>
			</div>
		</div>

		<header class="tnf-masthead">
			<div class="tnf-shell tnf-masthead-inner">
				<div class="tnf-logo-wrap">
					<?php if (function_exists('has_custom_logo') && has_custom_logo()) : ?>
						<div class="tnf-logo-image"><?php the_custom_logo(); ?></div>
					<?php endif; ?>
					<?php
					$site_title = trim((string) get_bloginfo('name', 'display'));
					$tagline    = trim((string) get_bloginfo('description', 'display'));
					if ($tagline === '' && function_exists('get_theme_mod')) {
						// Backward compatibility: keep existing custom tagline only when WP tagline is empty.
						$tagline = trim((string) get_theme_mod('tnf_masthead_tagline', ''));
					}
					?>
					<div class="tnf-brand"><?php echo esc_html($site_title); ?></div>
					<?php if ($tagline !== '') : ?>
					<div class="tnf-meta"><?php echo esc_html($tagline); ?></div>
					<?php endif; ?>
				</div>
				<div class="tnf-head-ad" aria-hidden="true">
					<span><?php esc_html_e('Top Banner Space', 'tnf-news-platform'); ?></span>
				</div>
				<div class="tnf-account-wrap">
					<?php if (is_user_logged_in()) : ?>
						<a class="tnf-auth-nav-btn" href="<?php echo esc_url($account_url); ?>"><?php esc_html_e('My Account', 'tnf-news-platform'); ?></a>
					<?php else : ?>
						<a class="tnf-auth-nav-btn" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('Login', 'tnf-news-platform'); ?></a>
						<a class="tnf-auth-nav-btn tnf-auth-nav-btn--secondary" href="<?php echo esc_url($register_url); ?>"><?php esc_html_e('Register', 'tnf-news-platform'); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</header>

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
 * News single: optional oEmbed from video URL meta (same key as Videos).
 *
 * @param string $content Post content.
 */
function tnf_prepend_news_embed(string $content): string {
	if (! tnf_is_news_article_singular()) {
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

	return '<div class="tnf-news-embed tnf-video-embed wp-block-embed is-type-video">' . $embed . '</div>' . $content;
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
 * Featured image URL for tnf_news cards (related, lists); deterministic placeholder if none.
 */
function tnf_news_post_thumbnail_url(int $post_id): string {
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

	return 'https://picsum.photos/seed/tnf-news-' . $post_id . '/640/360';
}

/**
 * Related post IDs for a single: same category first, then recent by date.
 *
 * @param string|array<int,string> $post_type One type or list of types (e.g. tnf_news + post).
 * @return array<int,int>
 */
function tnf_related_post_ids_for_single(int $post_id, $post_type, int $limit = 4): array {
	$post_id = (int) $post_id;
	$limit   = max(1, min(12, $limit));
	if ($post_id <= 0) {
		return array();
	}
	if (is_string($post_type)) {
		if ($post_type === '') {
			return array();
		}
		$types = array( $post_type );
	} elseif (is_array($post_type)) {
		$types = array_values( array_filter( array_map( 'strval', $post_type ) ) );
		if ($types === array()) {
			return array();
		}
	} else {
		return array();
	}

	$term_ids = array();
	if (taxonomy_exists('category')) {
		$terms = get_the_terms($post_id, 'category');
		if (is_array($terms) && ! is_wp_error($terms)) {
			foreach ($terms as $term) {
				$term_ids[] = (int) $term->term_id;
			}
		}
	}

	$related_ids = array();
	$exclude     = array($post_id);
	$base_args   = array(
		'post_type'           => count( $types ) === 1 ? $types[0] : $types,
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'fields'              => 'ids',
	);

	if ($term_ids !== array()) {
		$q_cat = new WP_Query(
			array_merge(
				$base_args,
				array(
					'posts_per_page' => $limit,
					'post__not_in'   => $exclude,
					'category__in'   => $term_ids,
				)
			)
		);
		$related_ids = array_map('intval', $q_cat->posts);
	}

	$exclude = array_merge($exclude, $related_ids);
	if (count($related_ids) < $limit) {
		$q_fill = new WP_Query(
			array_merge(
				$base_args,
				array(
					'posts_per_page' => $limit - count($related_ids),
					'post__not_in'   => $exclude,
				)
			)
		);
		$related_ids = array_merge($related_ids, array_map('intval', $q_fill->posts));
	}

	return $related_ids;
}

/**
 * Video single: related videos below content (same grid styles as news).
 *
 * @param string $content Post content.
 */
function tnf_video_single_append_related(string $content): string {
	if (! is_singular('tnf_video') || ! in_the_loop() || ! is_main_query()) {
		return $content;
	}

	$post_id = get_the_ID();
	if (! $post_id) {
		return $content;
	}

	$related_ids = tnf_related_post_ids_for_single($post_id, 'tnf_video', 4);
	if ($related_ids === array()) {
		return $content;
	}

	$html  = '<section class="tnf-news-related tnf-video-related" aria-label="' . esc_attr__('Related videos', 'tnf-news-platform') . '">';
	$html .= '<h3 class="tnf-news-related__title">' . esc_html__('Related Videos', 'tnf-news-platform') . '</h3>';
	$html .= '<div class="tnf-news-related-grid">';
	foreach ($related_ids as $rid) {
		$rid = (int) $rid;
		if ($rid <= 0) {
			continue;
		}
		$thumb = tnf_video_card_thumbnail_url($rid);
		if ($thumb === '') {
			$thumb = 'https://picsum.photos/seed/tnf-video-' . $rid . '/640/360';
		}
		$rel_terms = get_the_terms($rid, 'category');
		$rel_cat   = '';
		if (is_array($rel_terms) && ! is_wp_error($rel_terms) && isset($rel_terms[0])) {
			$rel_cat = (string) $rel_terms[0]->name;
		}
		$rel_title = get_the_title($rid);
		$html .= '<article class="tnf-news-related-card">';
		$html .= '<a class="tnf-news-related-card__thumb" href="' . esc_url(get_permalink($rid)) . '">';
		$html .= '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($rel_title) . '" loading="lazy" decoding="async" />';
		$html .= '</a>';
		$html .= '<div class="tnf-news-related-card__body">';
		if ($rel_cat !== '') {
			$html .= '<span class="tnf-news-related-card__cat">' . esc_html($rel_cat) . '</span>';
		}
		$html .= '<h4><a href="' . esc_url(get_permalink($rid)) . '">' . esc_html($rel_title) . '</a></h4>';
		$html .= '<time datetime="' . esc_attr(get_the_date(DATE_W3C, $rid)) . '">' . esc_html(get_the_date('', $rid)) . '</time>';
		$html .= '</div></article>';
	}
	$html .= '</div></section>';

	return $content . $html;
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
		if (
			! is_admin()
			&& is_singular('tnf_video')
			&& (int) get_queried_object_id() === $post_id
		) {
			$embed_url = (string) get_post_meta($post_id, 'tnf_embed_url', true);
			if ($embed_url !== '') {
				$embed_chk = wp_oembed_get($embed_url, array( 'width' => 1280 ));
				if ($embed_chk && is_string($embed_chk)) {
					return '';
				}
			}
		}
		if (has_post_thumbnail($post_id)) {
			return $block_content;
		}
		$thumb = tnf_video_card_thumbnail_url($post_id);
		if ($thumb === '') {
			return $block_content;
		}

		return tnf_post_featured_image_figure_html($post_id, $block, $thumb);
	}

	if ($type === 'tnf_news' || $type === 'post') {
		if (
			! is_admin()
			&& (int) get_queried_object_id() === $post_id
			&& (
				( $type === 'tnf_news' && is_singular('tnf_news') )
				|| ( $type === 'post' && is_singular('post') )
			)
		) {
			$embed_url = (string) get_post_meta($post_id, 'tnf_embed_url', true);
			if ($embed_url !== '') {
				$embed_chk = wp_oembed_get($embed_url, array( 'width' => 1280 ));
				if ($embed_chk && is_string($embed_chk)) {
					return '';
				}
			}
		}
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
 * Inline SVG icons for news single share bar (decorative; links carry aria-label).
 *
 * @param string $id One of share, fb, wa, li, x, link.
 */
function tnf_news_single_share_icon_svg(string $id): string {
	switch ($id) {
		case 'share':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.26.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>';
		case 'fb':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M24 12.073C24 5.446 18.627 0 12 0S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
		case 'wa':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>';
		case 'li':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
		case 'x':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
		case 'link':
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';
		default:
			return '';
	}
}

/**
 * News single: meta, byline + share, related grid.
 *
 * @param string $content Post content.
 */
function tnf_news_content_with_category_rail(string $content): string {
	if (! tnf_is_news_article_singular() || ! in_the_loop() || ! is_main_query()) {
		return $content;
	}

	$post_id = get_the_ID();
	if (! $post_id) {
		return $content;
	}

	$terms       = get_the_terms($post_id, 'category');
	$inline_cats = '';
	if (is_array($terms) && ! is_wp_error($terms)) {
		foreach ($terms as $term) {
			$link = get_term_link($term);
			if (is_wp_error($link)) {
				continue;
			}
			$inline_cats .= '<a href="' . esc_url((string) $link) . '">' . esc_html($term->name) . '</a> ';
		}
	}

	$meta  = '<div class="tnf-news-meta-head">';
	$meta .= '<div class="tnf-news-meta-head__crumbs"><a href="' . esc_url(home_url('/')) . '">' . esc_html__('Home', 'tnf-news-platform') . '</a> <span>/</span> <span>' . esc_html__('News', 'tnf-news-platform') . '</span></div>';
	$meta .= '<div class="tnf-news-meta-head__row">';
	$meta .= '<time datetime="' . esc_attr(get_the_date(DATE_W3C, $post_id)) . '">' . esc_html(get_the_date('', $post_id)) . '</time>';
	if ($inline_cats !== '') {
		$meta .= '<span class="tnf-news-meta-head__cats">' . $inline_cats . '</span>';
	}
	$meta .= '</div></div>';

	$author_id   = (int) get_post_field('post_author', $post_id);
	$author_name = $author_id ? get_the_author_meta('display_name', $author_id) : '';
	$author_url  = $author_id ? get_author_posts_url($author_id) : '';
	$avatar_html = $author_id
		? get_avatar(
			$author_id,
			96,
			'',
			$author_name !== '' ? $author_name : __('Author', 'tnf-news-platform'),
			array(
				'class' => 'tnf-news-byline__avatar-img',
			)
		)
		: '';

	$byline  = '<div class="tnf-news-byline">';
	$byline .= '<span class="tnf-news-byline__label">' . esc_html__('News by', 'tnf-news-platform') . '</span>';
	$byline .= '<span class="tnf-news-byline__avatar">' . $avatar_html . '</span>';
	if ($author_name !== '' && $author_url !== '') {
		$byline .= '<a class="tnf-news-byline__name" href="' . esc_url($author_url) . '">' . esc_html($author_name) . '</a>';
	} elseif ($author_name !== '') {
		$byline .= '<span class="tnf-news-byline__name">' . esc_html($author_name) . '</span>';
	} else {
		$byline .= '<span class="tnf-news-byline__name">' . esc_html__('Editorial', 'tnf-news-platform') . '</span>';
	}
	$byline .= '</div>';

	$related_html = '';
	$related_ids  = tnf_related_post_ids_for_single($post_id, tnf_listing_news_post_types(), 4);

	if ($related_ids !== array()) {
		$related_html .= '<section class="tnf-news-related" aria-label="' . esc_attr__('Related news', 'tnf-news-platform') . '">';
		$related_html .= '<h3 class="tnf-news-related__title">' . esc_html__('Related News', 'tnf-news-platform') . '</h3>';
		$related_html .= '<div class="tnf-news-related-grid">';
		foreach ($related_ids as $rid) {
			$rid = (int) $rid;
			if ($rid <= 0) {
				continue;
			}
			$thumb     = tnf_news_post_thumbnail_url($rid);
			$rel_terms = get_the_terms($rid, 'category');
			$rel_cat   = '';
			if (is_array($rel_terms) && ! is_wp_error($rel_terms) && isset($rel_terms[0])) {
				$rel_cat = (string) $rel_terms[0]->name;
			}
			$rel_title = get_the_title($rid);
			$related_html .= '<article class="tnf-news-related-card">';
			$related_html .= '<a class="tnf-news-related-card__thumb" href="' . esc_url(get_permalink($rid)) . '">';
			$related_html .= '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($rel_title) . '" loading="lazy" decoding="async" />';
			$related_html .= '</a>';
			$related_html .= '<div class="tnf-news-related-card__body">';
			if ($rel_cat !== '') {
				$related_html .= '<span class="tnf-news-related-card__cat">' . esc_html($rel_cat) . '</span>';
			}
			$related_html .= '<h4><a href="' . esc_url(get_permalink($rid)) . '">' . esc_html($rel_title) . '</a></h4>';
			$related_html .= '<time datetime="' . esc_attr(get_the_date(DATE_W3C, $rid)) . '">' . esc_html(get_the_date('', $rid)) . '</time>';
			$related_html .= '</div></article>';
		}
		$related_html .= '</div></section>';
	}

	$permalink     = get_permalink($post_id);
	$title         = get_the_title($post_id);
	$encoded_url   = rawurlencode((string) $permalink);
	$encoded_title = rawurlencode((string) $title);

	$share  = '<div class="tnf-share-bar" data-share-url="' . esc_attr((string) $permalink) . '" role="group" aria-label="' . esc_attr__('Share this article', 'tnf-news-platform') . '">';
	$share .= '<span class="tnf-share-bar__lead" aria-hidden="true">' . tnf_news_single_share_icon_svg('share') . '</span>';
	$share .= '<div class="tnf-share-bar__buttons">';
	$share .= '<a class="tnf-share-btn is-fb" href="https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on Facebook', 'tnf-news-platform') . '">' . tnf_news_single_share_icon_svg('fb') . '</a>';
	$share .= '<a class="tnf-share-btn is-wa" href="https://wa.me/?text=' . $encoded_title . '%20' . $encoded_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on WhatsApp', 'tnf-news-platform') . '">' . tnf_news_single_share_icon_svg('wa') . '</a>';
	$share .= '<a class="tnf-share-btn is-li" href="https://www.linkedin.com/sharing/share-offsite/?url=' . $encoded_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on LinkedIn', 'tnf-news-platform') . '">' . tnf_news_single_share_icon_svg('li') . '</a>';
	$share .= '<a class="tnf-share-btn is-x" href="https://twitter.com/intent/tweet?url=' . $encoded_url . '&text=' . $encoded_title . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on X', 'tnf-news-platform') . '">' . tnf_news_single_share_icon_svg('x') . '</a>';
	$share .= '<button type="button" class="tnf-share-btn is-copy" data-copy-link="' . esc_attr((string) $permalink) . '" aria-label="' . esc_attr__('Copy link', 'tnf-news-platform') . '">';
	$share .= '<span class="tnf-share-btn__icon" aria-hidden="true">' . tnf_news_single_share_icon_svg('link') . '</span>';
	$share .= '<span class="tnf-sr-only tnf-share-btn__text">' . esc_html__('Copy link', 'tnf-news-platform') . '</span>';
	$share .= '</button>';
	$share .= '</div></div>';

	$copy_js = '<script>(function(){var root=document.querySelector(".tnf-news-content-body");if(!root){return;}var b=root.querySelector(".tnf-share-btn.is-copy");if(!b){return;}var label=b.querySelector(".tnf-share-btn__text");var orig=label?label.textContent:"";b.addEventListener("click",function(){var u=b.getAttribute("data-copy-link")||"";if(!u){return;}var done=function(){b.classList.add("is-copied");if(label){label.textContent="' . esc_js(__('Copied', 'tnf-news-platform')) . '";}setTimeout(function(){b.classList.remove("is-copied");if(label){label.textContent=orig;}},1800);};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(done);}else{var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();try{document.execCommand("copy");done();}catch(e){}document.body.removeChild(t);}});})();</script>';

	$byline_share = '<div class="tnf-news-byline-share">' . $byline . $share . '</div>';

	return '<div class="tnf-news-content-layout"><div class="tnf-news-content-body">' . $meta . $byline_share . $content . $related_html . '</div></div>' . $copy_js;
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
		tnf_is_news_article_singular()
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
