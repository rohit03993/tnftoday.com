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
add_filter('render_block', 'tnf_render_block_hide_duplicate_video_embed', 10, 2);
add_filter('render_block', 'tnf_render_block_youtube_embed_aspect', 11, 2);
add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_chrome_styles', 12);
add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_header_aajtak_styles', 40);
add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_tnf_cpt_styles', 20);
add_action('wp_enqueue_scripts', 'tnf_enqueue_video_embed_assets', 99);
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
 * Aaj Tak header CSS — after theme home-news so layout wins over legacy nav rules.
 */
function tnf_enqueue_frontend_header_aajtak_styles(): void {
	if (is_admin()) {
		return;
	}

	$aaj_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-header-aajtak.css';
	if (! is_readable($aaj_path)) {
		return;
	}

	$deps = array('tnf-frontend-chrome');
	if (wp_style_is('tnf-child-home-news', 'registered') || wp_style_is('tnf-child-home-news', 'enqueued')) {
		$deps[] = 'tnf-child-home-news';
	}

	wp_enqueue_style(
		'tnf-frontend-header-aajtak',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-header-aajtak.css',
		$deps,
		(string) filemtime($aaj_path)
	);
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
 * Nav sections for the chrome menu (mobile: grouped cards; desktop: flattened visually).
 *
 * @return array<int, array{id:string, title:string, accent:string, items: array<int, array{label:string, url:string}>}>
 */
function tnf_news_nav_sections(): array {
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

	$link = static function (string $slug) use ($cats): array {
		return array(
			'label' => $cats[ $slug ],
			'url'   => tnf_news_category_link($slug),
		);
	};

	return array(
		array(
			'id'     => 'start',
			'title'  => __('Start here', 'tnf-news-platform'),
			'accent' => '#c41e3a',
			'items'  => array(
				array(
					'label' => __('Home', 'tnf-news-platform'),
					'url'   => home_url('/'),
				),
			),
		),
		array(
			'id'     => 'daily',
			'title'  => __('Daily digest', 'tnf-news-platform'),
			'accent' => '#2563eb',
			'items'  => array(
				$link('national'),
				$link('health'),
				$link('religion'),
				$link('entertainment'),
			),
		),
		array(
			'id'     => 'desk',
			'title'  => __('Desk & arena', 'tnf-news-platform'),
			'accent' => '#0d9488',
			'items'  => array(
				$link('tech'),
				$link('politics'),
				$link('sports'),
				$link('business'),
			),
		),
		array(
			'id'     => 'magazine',
			'title'  => __('Magazine', 'tnf-news-platform'),
			'accent' => '#7c3aed',
			'items'  => array(
				$link('exclusive'),
				$link('lifestyle'),
				$link('cultural'),
				$link('crime'),
			),
		),
	);
}

/**
 * Primary nav items for TNF chrome (flat list, same order as sections).
 *
 * @return array<int, array<string, string>>
 */
function tnf_news_nav_items(): array {
	$items = array();
	foreach (tnf_news_nav_sections() as $section) {
		foreach ($section['items'] as $item) {
			$items[] = $item;
		}
	}

	return $items;
}

/**
 * One primary nav link (desktop bar + mobile drawer).
 *
 * @param array{label:string, url:string} $item Nav item.
 */
function tnf_render_main_menu_link(array $item): void {
	$active = tnf_news_nav_url_is_current($item['url']) ? ' is-active' : '';
	echo '<a class="tnf-main-menu__link' . esc_attr($active) . '" href="' . esc_url($item['url']) . '">';
	echo '<span class="tnf-main-menu__label">' . esc_html($item['label']) . '</span>';
	echo '</a>';
}

/**
 * Primary items in the navy bar (Aaj Tak–style top row).
 *
 * @return array<int, array{label:string, url:string}>
 */
function tnf_header_primary_nav_items(): array {
	$epaper_url = get_post_type_archive_link('tnf_pdf_report');
	$epaper_url = is_string($epaper_url) && $epaper_url !== '' ? $epaper_url : home_url('/pdf-reports/');

	$link = static function (string $slug): array {
		$cats = array(
			'national'      => __('National', 'tnf-news-platform'),
			'entertainment' => __('Entertainment', 'tnf-news-platform'),
			'religion'      => __('Religion', 'tnf-news-platform'),
			'lifestyle'     => __('Lifestyle', 'tnf-news-platform'),
			'sports'        => __('Sports', 'tnf-news-platform'),
		);

		return array(
			'label' => $cats[ $slug ],
			'url'   => tnf_news_category_link($slug),
		);
	};

	return array(
		array(
			'label' => __('Home', 'tnf-news-platform'),
			'url'   => home_url('/'),
		),
		array(
			'label' => __('ePaper', 'tnf-news-platform'),
			'url'   => $epaper_url,
		),
		$link('national'),
		$link('entertainment'),
		$link('religion'),
		$link('lifestyle'),
		$link('sports'),
	);
}

/**
 * Horizontal topic pills under the breaking bar (categories + hot tags).
 *
 * @return array<int, array{label:string, url:string}>
 */
function tnf_header_topic_pill_items(): array {
	$pills = array();
	$tags = get_tags(
		array(
			'orderby' => 'count',
			'order'   => 'DESC',
			'number'  => 6,
			'hide_empty' => true,
		)
	);
	if (is_array($tags)) {
		foreach ($tags as $tag) {
			if (! $tag instanceof WP_Term) {
				continue;
			}
			$link = get_tag_link($tag);
			if (is_wp_error($link)) {
				continue;
			}
			$pills[] = array(
				'label' => $tag->name,
				'url'   => $link,
			);
			if (count($pills) >= 14) {
				break;
			}
		}
	}

	$seen = array();
	$unique = array();
	foreach ($pills as $pill) {
		$key = strtolower($pill['url']);
		if (isset($seen[ $key ])) {
			continue;
		}
		$seen[ $key ] = true;
		$unique[]     = $pill;
	}

	return array_slice($unique, 0, 14);
}

/**
 * Inline SVG for chrome icon buttons.
 *
 * @param string $name epaper|search|live|menu|home|video|account.
 */
function tnf_chrome_icon_svg(string $name): string {
	$icons = array(
		'epaper'  => '<svg class="tnf-chrome-tool__epaper-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 2h9l5 5v13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm8 1.5V8h4.5L16 3.5zM7 7h10v1.5H7V7zm0 4h10v1.5H7V11zm0 4h7v1.5H7V15z"/></svg>',
		'search'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>',
		'live'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>',
		'menu'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/></svg>',
		'home'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>',
		'video'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>',
		'account' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
	);

	return $icons[ $name ] ?? '';
}

/**
 * Logo block for chrome head (custom logo, unified banner, or text).
 */
function tnf_render_chrome_logo(string $home_url, string $home_aria, bool $has_unified, int $banner_aid, string $banner_link): void {
	// Aaj Tak chrome bar always uses the compact custom logo (not the wide unified banner).
	$logo_img   = tnf_chrome_logo_image_html();
	if ($logo_img === '') {
		$logo_img = tnf_masthead_custom_logo_image_html();
	}
	$site_title = trim((string) get_bloginfo('name', 'display'));
	?>
	<a class="tnf-chrome-head__logo tnf-logo-home" href="<?php echo esc_url($home_url); ?>" rel="home" aria-label="<?php echo esc_attr($home_aria); ?>">
		<?php if ($logo_img !== '') : ?>
			<span class="tnf-logo-image"><?php echo $logo_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php elseif ($site_title !== '') : ?>
			<span class="tnf-brand"><?php echo esc_html($site_title); ?></span>
		<?php endif; ?>
	</a>
	<?php
}

/**
 * Side navigation drawer (mobile + desktop hamburger).
 */
function tnf_render_chrome_menu_drawer(): void {
	$home_url   = tnf_masthead_home_url();
	$epaper_url = get_post_type_archive_link('tnf_pdf_report');
	$epaper_url = is_string($epaper_url) && $epaper_url !== '' ? $epaper_url : home_url('/pdf-reports/');
	$logo_html  = tnf_chrome_logo_image_html();
	$home_aria  = sprintf(
		/* translators: %s: site name */
		__('Go to %s homepage', 'tnf-news-platform'),
		get_bloginfo('name', 'display')
	);
	?>
	<aside
		id="tnf-chrome-drawer"
		class="tnf-chrome-side-drawer"
		aria-hidden="true"
		aria-label="<?php esc_attr_e('Site sections', 'tnf-news-platform'); ?>"
	>
		<div class="tnf-drawer-head">
			<a class="tnf-drawer-head__logo" href="<?php echo esc_url($home_url); ?>" aria-label="<?php echo esc_attr($home_aria); ?>">
				<?php if ($logo_html !== '') : ?>
					<span class="tnf-drawer-head__logo-img"><?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php else : ?>
					<span class="tnf-drawer-head__site"><?php echo esc_html(get_bloginfo('name', 'display')); ?></span>
				<?php endif; ?>
			</a>
			<a class="tnf-drawer-head__epaper" href="<?php echo esc_url($epaper_url); ?>"><?php esc_html_e('e-Paper', 'tnf-news-platform'); ?></a>
			<button
				type="button"
				class="tnf-drawer-close"
				data-tnf-drawer-close
				aria-label="<?php esc_attr_e('Close menu', 'tnf-news-platform'); ?>"
			>
				<span aria-hidden="true">×</span>
			</button>
		</div>
		<nav class="tnf-drawer-body tnf-drawer-nav" aria-label="<?php esc_attr_e('Sections', 'tnf-news-platform'); ?>">
			<?php
			foreach (tnf_news_nav_items() as $item) {
				tnf_render_main_menu_link($item);
			}
			if (function_exists('tnf_mobile_app_active') && tnf_mobile_app_active()) {
				$login_url   = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('login') : home_url('/login/');
				$account_url = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('my-account') : home_url('/my-account/');
				if (is_user_logged_in()) {
					tnf_render_main_menu_link(
						array(
							'label' => __('My Account', 'tnf-news-platform'),
							'url'   => $account_url,
						)
					);
				} else {
					tnf_render_main_menu_link(
						array(
							'label' => __('Sign In', 'tnf-news-platform'),
							'url'   => $login_url,
						)
					);
				}
			}
			?>
		</nav>
	</aside>
	<?php
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

	$cache_key = 'tnf_breaking_ticker_html';
	$cached    = get_transient($cache_key);
	if (is_string($cached) && $cached !== '') {
		return $cached;
	}

	$breaking = new WP_Query(
		array(
			'post_type'              => tnf_listing_news_post_types(),
			'post_status'            => 'publish',
			'posts_per_page'         => $count,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
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

	$html = implode('<span class="tnf-breaking-sep" aria-hidden="true">◆</span>', $parts);
	set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);

	return $html;
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
 * Front-page URL for masthead logo / home control.
 */
function tnf_masthead_home_url(): string {
	return home_url('/');
}

/**
 * Compact logo markup for the Aaj Tak chrome bar (sized for nav height).
 */
function tnf_chrome_logo_image_html(): string {
	if (! function_exists('has_custom_logo') || ! has_custom_logo()) {
		return '';
	}
	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id <= 0) {
		return '';
	}
	$html = wp_get_attachment_image(
		$logo_id,
		array( 160, 160 ),
		false,
		array(
			'class'    => 'custom-logo tnf-chrome-logo-img',
			'loading'  => 'eager',
			'decoding' => 'async',
			'alt'      => get_bloginfo('name', 'display'),
		)
	);

	return is_string($html) ? $html : '';
}

/**
 * Custom logo image markup for masthead (no nested home link).
 */
function tnf_masthead_custom_logo_image_html(): string {
	if (! function_exists('has_custom_logo') || ! has_custom_logo()) {
		return '';
	}
	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id <= 0) {
		return '';
	}
	$html = wp_get_attachment_image(
		$logo_id,
		'full',
		false,
		array(
			'class'    => 'custom-logo',
			'loading'  => 'eager',
			'decoding' => 'async',
			'alt'      => get_bloginfo('name', 'display'),
		)
	);
	return is_string($html) ? $html : '';
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

	$login_url   = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('login') : home_url('/login/');
	$account_url = function_exists('tnf_auth_page_url') ? tnf_auth_page_url('my-account') : home_url('/my-account/');
	$epaper_url   = get_post_type_archive_link('tnf_pdf_report');
	$epaper_url   = is_string($epaper_url) && $epaper_url !== '' ? $epaper_url : home_url('/pdf-reports/');
	$auth_lite    = function_exists('tnf_is_auth_page') && tnf_is_auth_page();
	$root_class   = $wrap_root_typography ? 'tnf-site-chrome tnf-home-news tnf-chrome-aaj' : 'tnf-site-chrome tnf-chrome-aaj';
	if ($auth_lite) {
		$root_class .= ' tnf-chrome--auth-lite';
	}
	$ticker_inner = $auth_lite ? '' : tnf_news_breaking_ticker_inner_html();
	$videos_url   = get_post_type_archive_link('tnf_video');
	$videos_url   = is_string($videos_url) && $videos_url !== '' ? $videos_url : home_url('/videos/');
	$whatsapp_url = apply_filters('tnf_chrome_whatsapp_url', '');
	$whatsapp_url = is_string($whatsapp_url) ? esc_url($whatsapp_url) : '';
	$topic_pills  = $auth_lite ? array() : tnf_header_topic_pill_items();

	$header_settings = function_exists('tnf_header_get_settings') ? tnf_header_get_settings() : array();
	$banner_aid      = absint($header_settings['banner_attachment_id'] ?? 0);
	$banner_link     = is_string($header_settings['banner_link_url'] ?? null) ? (string) $header_settings['banner_link_url'] : '';
	$banner_src          = $banner_aid ? wp_get_attachment_image_url($banner_aid, 'large') : '';
	$has_unified_masthead = is_string($banner_src) && $banner_src !== '';
	$home_url             = tnf_masthead_home_url();
	$news_url             = get_post_type_archive_link('tnf_news');
	$news_url             = is_string($news_url) && $news_url !== '' ? $news_url : home_url('/news/');
	$account_href         = is_user_logged_in() ? $account_url : $login_url;
	$account_label        = is_user_logged_in() ? __('My Account', 'tnf-news-platform') : __('Sign In', 'tnf-news-platform');
	$home_aria            = sprintf(
		/* translators: %s: site name */
		__('Go to %s homepage', 'tnf-news-platform'),
		get_bloginfo('name', 'display')
	);
	?>
	<div class="<?php echo esc_attr($root_class); ?>">
		<div class="tnf-chrome-stack">
		<div class="tnf-top-nav tnf-chrome-head">
			<div class="tnf-shell tnf-chrome-head__inner">
				<div class="tnf-chrome-head__lead">
					<button
						type="button"
						class="tnf-nav-toggle tnf-chrome-head__menu-btn"
						aria-expanded="false"
						aria-controls="tnf-chrome-drawer"
						aria-label="<?php esc_attr_e('Toggle sections menu', 'tnf-news-platform'); ?>"
					>
						<?php echo tnf_chrome_icon_svg('menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
					<?php tnf_render_chrome_logo($home_url, $home_aria, $has_unified_masthead, $banner_aid, $banner_link); ?>
				</div>

				<nav id="tnf-main-menu" class="tnf-main-menu" aria-label="<?php esc_attr_e('Sections', 'tnf-news-platform'); ?>">
					<div class="tnf-main-menu__bar" role="menubar"><?php
					foreach (tnf_header_primary_nav_items() as $item) {
						tnf_render_main_menu_link($item);
					}
					?>
						<button
							type="button"
							class="tnf-main-menu__link tnf-main-menu__more"
							aria-expanded="false"
							aria-controls="tnf-chrome-drawer"
							data-tnf-nav-more
						>
							<span class="tnf-main-menu__label"><?php esc_html_e('More', 'tnf-news-platform'); ?></span>
						</button>
					</div>
				</nav>

				<div class="tnf-chrome-head__tools">
					<a class="tnf-chrome-tool tnf-chrome-tool--epaper" href="<?php echo esc_url($epaper_url); ?>" aria-label="<?php esc_attr_e('e-Paper', 'tnf-news-platform'); ?>">
						<span class="tnf-chrome-tool__epaper-icon" aria-hidden="true">
							<?php echo tnf_chrome_icon_svg('epaper'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<span class="tnf-chrome-tool__epaper-label"><?php esc_html_e('e-Paper', 'tnf-news-platform'); ?></span>
					</a>
					<a class="tnf-chrome-tool tnf-chrome-tool--signin" href="<?php echo esc_url($account_href); ?>" aria-label="<?php echo esc_attr($account_label); ?>">
						<?php echo tnf_chrome_icon_svg('account'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="tnf-chrome-tool__signin-label"><?php echo esc_html($account_label); ?></span>
					</a>
				</div>
			</div>
		</div>

		<?php if ($ticker_inner !== '') : ?>
			<div class="tnf-breaking" role="region" aria-label="<?php esc_attr_e('Breaking news ticker', 'tnf-news-platform'); ?>">
				<div class="tnf-shell">
					<div class="tnf-breaking-inner">
						<div class="tnf-breaking-badge">
							<span class="tnf-breaking-badge__text"><?php esc_html_e('Breaking News', 'tnf-news-platform'); ?></span>
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
			</div>
		<?php endif; ?>
		</div><!-- .tnf-chrome-stack -->

		<button
			type="button"
			class="tnf-chrome-drawer-backdrop"
			data-tnf-drawer-backdrop
			aria-label="<?php esc_attr_e('Close menu', 'tnf-news-platform'); ?>"
			hidden
		></button>
		<?php tnf_render_chrome_menu_drawer(); ?>

		<?php if ($whatsapp_url !== '') : ?>
			<a class="tnf-chrome-whatsapp" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer">
				<span class="tnf-chrome-whatsapp__icon" aria-hidden="true">WA</span>
				<span class="tnf-chrome-whatsapp__text"><?php esc_html_e('Join our WhatsApp channel', 'tnf-news-platform'); ?></span>
			</a>
		<?php endif; ?>

		<?php if ($topic_pills !== array() && (! function_exists('tnf_mobile_app_active') || ! tnf_mobile_app_active())) : ?>
			<div class="tnf-chrome-topics" role="navigation" aria-label="<?php esc_attr_e('Trending topics', 'tnf-news-platform'); ?>">
				<div class="tnf-shell tnf-chrome-topics__inner">
					<button type="button" class="tnf-chrome-topics__scroll tnf-chrome-topics__scroll--prev" data-tnf-topics-scroll="prev" aria-label="<?php esc_attr_e('Scroll topics left', 'tnf-news-platform'); ?>">‹</button>
					<div class="tnf-chrome-topics__viewport">
						<div class="tnf-chrome-topics__track" data-tnf-topics-track>
							<?php foreach ($topic_pills as $pill) : ?>
								<a class="tnf-chrome-topics__pill" href="<?php echo esc_url($pill['url']); ?>"><?php echo esc_html($pill['label']); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
					<button type="button" class="tnf-chrome-topics__scroll tnf-chrome-topics__scroll--next" data-tnf-topics-scroll="next" aria-label="<?php esc_attr_e('Scroll topics right', 'tnf-news-platform'); ?>">›</button>
				</div>
			</div>
		<?php endif; ?>

		<?php if (! function_exists('tnf_mobile_app_active') || ! tnf_mobile_app_active()) : ?>
		<nav class="tnf-chrome-bottom-nav" aria-label="<?php esc_attr_e('Mobile navigation', 'tnf-news-platform'); ?>">
			<a class="tnf-chrome-bottom-nav__item<?php echo is_front_page() ? ' is-active' : ''; ?>" href="<?php echo esc_url($home_url); ?>">
				<?php echo tnf_chrome_icon_svg('home'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e('Home', 'tnf-news-platform'); ?></span>
			</a>
			<a class="tnf-chrome-bottom-nav__item<?php echo tnf_news_nav_url_is_current($videos_url) ? ' is-active' : ''; ?>" href="<?php echo esc_url($videos_url); ?>">
				<?php echo tnf_chrome_icon_svg('video'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e('Videos', 'tnf-news-platform'); ?></span>
			</a>
			<a class="tnf-chrome-bottom-nav__item tnf-chrome-bottom-nav__item--live" href="<?php echo esc_url($videos_url); ?>">
				<span class="tnf-chrome-bottom-nav__live-dot" aria-hidden="true"></span>
				<?php echo tnf_chrome_icon_svg('live'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e('Live TV', 'tnf-news-platform'); ?></span>
			</a>
			<a class="tnf-chrome-bottom-nav__item<?php echo tnf_news_nav_url_is_current($news_url) ? ' is-active' : ''; ?>" href="<?php echo esc_url($news_url); ?>">
				<?php echo tnf_chrome_icon_svg('epaper'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e('News', 'tnf-news-platform'); ?></span>
			</a>
			<button type="button" class="tnf-chrome-bottom-nav__item tnf-chrome-bottom-nav__menu" data-tnf-bottom-menu aria-label="<?php esc_attr_e('Open menu', 'tnf-news-platform'); ?>">
				<?php echo tnf_chrome_icon_svg('menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php esc_html_e('Menu', 'tnf-news-platform'); ?></span>
			</button>
		</nav>
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
 * Inline SVG helpers for ePaper toolbar + social share row (icons for touch targets).
 */
function tnf_epaper_toolbar_scissors_svg(): string {
	return '<svg class="tnf-epaper__tool-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3.5 7.5V3.5h4M20.5 7.5V3.5h-4M3.5 16.5v4h4M20.5 16.5v4h-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><rect x="7.2" y="7.2" width="9.6" height="9.6" rx="2.2" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
}

/**
 * @return string
 */
function tnf_epaper_toolbar_link_svg(): string {
	return '<svg class="tnf-epaper__tool-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/**
 * Clip + copy link controls (toolbar). Scissor icon; copy shows short label for feedback.
 *
 * @param string $share_url Absolute URL (may include ?tnf_pg=).
 * @param string $title     Share title text.
 */
function tnf_epaper_toolbar_tools_html(string $share_url, string $title): string {
	$share_url = esc_url($share_url);
	$clip_tip  = esc_attr__('Drag on the page to cut a shareable clip', 'tnf-news-platform');
	$copy_tip  = esc_attr__('Copy link to this page', 'tnf-news-platform');
	$tools_lbl = esc_attr__('Page tools', 'tnf-news-platform');

	$html  = '<div class="tnf-epaper__tools" role="group" aria-label="' . $tools_lbl . '">';
	$html .= '<button type="button" class="tnf-epaper__tool-btn is-clip" data-tnf-clip-toggle="1" aria-pressed="false" title="' . $clip_tip . '" aria-label="' . esc_attr__('Cut clip', 'tnf-news-platform') . '">';
	$html .= '<span class="tnf-epaper__tool-icon" aria-hidden="true">' . tnf_epaper_toolbar_scissors_svg() . '</span>';
	$html .= '<span class="tnf-epaper__tool-text">' . esc_html__('Crop', 'tnf-news-platform') . '</span>';
	$html .= '</button>';
	$html .= '<button type="button" class="tnf-epaper__tool-btn is-copy" data-epaper-copy="' . esc_attr($share_url) . '" title="' . $copy_tip . '" aria-label="' . esc_attr__('Copy page link', 'tnf-news-platform') . '">';
	$html .= '<span class="tnf-epaper__tool-icon" aria-hidden="true">' . tnf_epaper_toolbar_link_svg() . '</span>';
	$html .= '<span class="tnf-epaper__tool-text" data-tnf-copy-label>' . esc_html__('Link', 'tnf-news-platform') . '</span>';
	$html .= '</button>';
	$html .= '</div>';

	return $html;
}

/**
 * Social share controls (inline in toolbar; does not overlay the page). $share_url may include ?tnf_pg=.
 *
 * @param string $share_url Absolute URL.
 * @param string $title     Share title.
 */
function tnf_epaper_share_social_dock_html(string $share_url, string $title): string {
	$share_url = esc_url($share_url);
	$enc_url   = rawurlencode($share_url);
	$enc_title = rawurlencode($title);

	$wa_icon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M19.1 4.9A9.94 9.94 0 0 0 12.05 2C6.56 2 2.1 6.46 2.1 11.95c0 1.76.46 3.49 1.33 5.01L2 22l5.19-1.36a9.89 9.89 0 0 0 4.84 1.24h.01c5.49 0 9.95-4.46 9.95-9.95a9.9 9.9 0 0 0-2.9-7.03Zm-7.06 15.3a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.08.8.83-3-.2-.31a8.2 8.2 0 0 1-1.26-4.42c0-4.55 3.7-8.25 8.26-8.25a8.2 8.2 0 0 1 5.84 2.42 8.2 8.2 0 0 1 2.41 5.84c0 4.55-3.7 8.25-8.25 8.25Zm4.52-6.16c-.25-.13-1.47-.73-1.7-.81-.23-.09-.4-.13-.56.12-.16.24-.64.81-.78.98-.14.16-.28.18-.53.06-.25-.13-1.05-.39-2-1.25-.74-.66-1.24-1.47-1.39-1.72-.14-.24-.02-.37.11-.5.11-.11.25-.28.37-.42.12-.14.16-.24.24-.41.08-.16.04-.3-.02-.42-.07-.12-.56-1.36-.77-1.86-.2-.47-.4-.41-.56-.42h-.48c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2 0 1.18.86 2.32.98 2.48.12.16 1.68 2.56 4.06 3.59.57.24 1.01.38 1.36.49.57.18 1.08.15 1.49.09.45-.07 1.47-.6 1.68-1.17.2-.58.2-1.07.14-1.17-.06-.11-.22-.17-.47-.29Z"/></svg>';
	$fb_icon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.5-3.88 3.78-3.88 1.09 0 2.23.2 2.23.2v2.45H15.2c-1.21 0-1.59.75-1.59 1.52V12h2.7l-.43 2.89h-2.27v6.99A10 10 0 0 0 22 12Z"/></svg>';
	$x_icon  = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18.9 2H22l-6.77 7.74L23.2 22h-6.24l-4.9-6.44L6.4 22H3.3l7.24-8.28L.8 2h6.4l4.43 5.85L18.9 2Zm-1.1 18h1.73L6.26 3.9H4.4L17.8 20Z"/></svg>';
	$li_icon = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M6.94 8.5H3.56V20h3.38V8.5ZM5.25 3a1.96 1.96 0 1 0 0 3.91A1.96 1.96 0 0 0 5.25 3ZM20.44 13.4c0-3.05-1.63-4.9-4.25-4.9-1.96 0-2.84 1.08-3.33 1.84V8.5H9.5V20h3.36v-5.69c0-1.5.29-2.95 2.14-2.95 1.82 0 1.85 1.7 1.85 3.05V20h3.37v-6.6Z"/></svg>';
	$sh_icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	$html  = '<div class="tnf-epaper-share tnf-epaper-share--toolbar" data-share-base="' . esc_attr($share_url) . '" data-share-title="' . esc_attr($title) . '">';
	$html .= '<span class="tnf-epaper-share__toolbar-label">' . esc_html__('Share', 'tnf-news-platform') . '</span>';
	$html .= '<div class="tnf-epaper-share__links" role="toolbar" aria-label="' . esc_attr__('Share this edition', 'tnf-news-platform') . '">';
	$html .= '<button type="button" class="tnf-epaper-share__btn is-native tnf-epaper-share__btn--icon" data-tnf-share-native="1" hidden title="' . esc_attr__('Share via your device', 'tnf-news-platform') . '" aria-label="' . esc_attr__('Share…', 'tnf-news-platform') . '">' . $sh_icon . '</button>';
	$html .= '<a class="tnf-epaper-share__btn is-wa tnf-epaper-share__btn--icon" href="https://wa.me/?text=' . $enc_title . '%20' . $enc_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on WhatsApp', 'tnf-news-platform') . '">' . $wa_icon . '</a>';
	$html .= '<a class="tnf-epaper-share__btn is-fb tnf-epaper-share__btn--icon" href="https://www.facebook.com/sharer/sharer.php?u=' . $enc_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on Facebook', 'tnf-news-platform') . '">' . $fb_icon . '</a>';
	$html .= '<a class="tnf-epaper-share__btn is-x tnf-epaper-share__btn--icon" href="https://twitter.com/intent/tweet?url=' . $enc_url . '&text=' . $enc_title . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on X', 'tnf-news-platform') . '">' . $x_icon . '</a>';
	$html .= '<a class="tnf-epaper-share__btn is-li tnf-epaper-share__btn--icon" href="https://www.linkedin.com/sharing/share-offsite/?url=' . $enc_url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__('Share on LinkedIn', 'tnf-news-platform') . '">' . $li_icon . '</a>';
	$html .= '</div></div>';

	return $html;
}

/**
 * Legacy full-width share row (embed / old markup). On singles, social icons live in the toolbar.
 *
 * @param string $share_url Absolute URL.
 * @param string $title     Share title.
 */
function tnf_epaper_share_bar_html(string $share_url, string $title): string {
	return tnf_epaper_share_social_dock_html($share_url, $title);
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

		$clip_brand = tnf_epaper_clip_brand_image_url();
		$out .= '<div class="tnf-epaper" data-tnf-epaper="1" data-tnf-permalink="' . esc_url(get_permalink($post_id)) . '" data-tnf-epaper-date="' . esc_attr($date_s) . '"' . ( $clip_brand !== '' ? ' data-tnf-clip-brand="' . esc_url($clip_brand) . '"' : '' ) . '>';
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
		$out .= tnf_epaper_toolbar_tools_html($share_url, $title);
		$out .= tnf_epaper_share_social_dock_html($share_url, $title);
		$out .= '</div>';
		$out .= '</div>';

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
	$clip_brand_js = tnf_epaper_clip_brand_image_url();
	$out .= '<div class="tnf-epaper tnf-epaper--pdfjs" data-tnf-pdfjs="1" data-tnf-pdf-url="' . esc_url($url) . '" data-tnf-permalink="' . esc_url(get_permalink($post_id)) . '" data-tnf-epaper-date="' . esc_attr($date_s) . '"' . ( $clip_brand_js !== '' ? ' data-tnf-clip-brand="' . esc_url($clip_brand_js) . '"' : '' ) . '>';
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
	$out .= tnf_epaper_toolbar_tools_html(get_permalink($post_id), $title);
	$out .= tnf_epaper_share_social_dock_html(get_permalink($post_id), $title);
	$out .= '</div></div>';
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

	$embed_html = tnf_render_video_embed_html($url, (int) get_the_ID());
	if ($embed_html === '') {
		return $content;
	}

	$yt_id = tnf_youtube_id_from_url($url);
	if ($yt_id !== '') {
		$content = tnf_strip_youtube_embeds_from_content($content, $yt_id);
	}

	return '<div class="tnf-news-embed">' . $embed_html . '</div>' . $content;
}

/**
 * Video single: one player from metabox URL (strip duplicate embeds in post body).
 *
 * @param string $content Post content.
 */
function tnf_prepend_video_embed(string $content): string {
	if (! is_singular('tnf_video') || ! in_the_loop() || ! is_main_query()) {
		return $content;
	}

	static $prepended = false;
	if ($prepended) {
		return $content;
	}

	$url = (string) get_post_meta(get_the_ID(), 'tnf_embed_url', true);
	if ($url === '') {
		return $content;
	}

	$post_id    = (int) get_the_ID();
	$embed_html = tnf_render_video_embed_html($url, $post_id);
	if ($embed_html === '') {
		return $content;
	}

	$yt_id = tnf_youtube_id_from_url($url);
	if ($yt_id !== '') {
		$content = tnf_strip_youtube_embeds_from_content($content, $yt_id);
	}

	$prepended = true;

	return $embed_html . $content;
}

/**
 * True when URL is a YouTube Shorts link.
 */
function tnf_youtube_is_shorts_url(string $url): bool {
	return $url !== '' && preg_match('#youtube\.com/shorts/|youtube\.com/shorts\?#i', $url) === 1;
}

/**
 * Single video player markup (YouTube / Shorts-aware; oEmbed fallback for other hosts).
 *
 * @param string $url     Video URL.
 * @param int    $post_id Optional post ID for Shorts detection (metabox + content).
 */
function tnf_render_video_embed_html(string $url, int $post_id = 0): string {
	$url = trim($url);
	if ($url === '') {
		return '';
	}

	$yt_id = tnf_youtube_id_from_url($url);
	if ($yt_id !== '') {
		$is_short = tnf_youtube_is_shorts_url($url);
		if (! $is_short && $post_id > 0) {
			$is_short = tnf_video_card_is_shorts($post_id);
		}
		$wrap_cls = 'tnf-video-embed wp-block-embed is-type-video is-provider-youtube'
			. ( $is_short ? ' tnf-video-embed--shorts' : ' tnf-video-embed--landscape' );
		$title    = get_the_title() ?: __( 'Video', 'tnf-news-platform' );
		$src      = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($yt_id)
			. '?rel=0&modestbranding=1&playsinline=1';
		$wrapper_attr = $is_short ? ' data-tnf-shorts="1"' : '';
		$iframe       = sprintf(
			'<div class="wp-block-embed__wrapper"%3$s><iframe src="%1$s" title="%2$s" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
			esc_url($src),
			esc_attr($title),
			$wrapper_attr
		);

		$outer_attr = $is_short ? ' data-tnf-shorts="1"' : '';
		$inner = '<div class="' . esc_attr($wrap_cls) . '"' . $outer_attr . '>' . $iframe . '</div>';
		$mode  = $is_short ? 'portrait' : 'landscape';

		return '<div class="tnf-video-player tnf-video-player--' . esc_attr($mode) . '" data-tnf-video="' . esc_attr($mode) . '">'
			. '<div class="tnf-video-player__ratio">' . $inner . '</div></div>';
	}

	$embed = wp_oembed_get($url, array( 'width' => 1280 ));
	if (! $embed || ! is_string($embed)) {
		return '';
	}

	$inner = '<div class="tnf-video-embed tnf-video-embed--landscape wp-block-embed is-type-video">' . $embed . '</div>';

	return '<div class="tnf-video-player tnf-video-player--landscape" data-tnf-video="landscape">'
		. '<div class="tnf-video-player__ratio">' . $inner . '</div></div>';
}

/**
 * Remove duplicate YouTube embeds from post content when the metabox URL is rendered above.
 *
 * @param string $content   Post HTML.
 * @param string $video_id  YouTube id.
 */
function tnf_strip_youtube_embeds_from_content(string $content, string $video_id): string {
	if ($content === '' || $video_id === '') {
		return $content;
	}

	$id = preg_quote($video_id, '#' );

	$patterns = array(
		'#<figure\b[^>]*\bwp-block-embed\b[^>]*>.*?' . $id . '.*?</figure>#is',
		'#<div\b[^>]*\bwp-block-embed\b[^>]*>.*?' . $id . '.*?</div>\s*</div>#is',
		'#<div\b[^>]*\bwp-block-embed\b[^>]*>.*?' . $id . '.*?</div>#is',
		'#<iframe\b[^>]*\b(?:youtube\.com|youtube-nocookie\.com|youtu\.be)[^>]*' . $id . '[^>]*>.*?</iframe>#is',
		'#<iframe\b[^>]*' . $id . '[^>]*>.*?</iframe>#is',
		'#<p>\s*https?://(?:www\.)?(?:youtube\.com|youtu\.be)[^\s<]*' . $id . '[^\s<]*\s*</p>#i',
	);

	foreach ($patterns as $pattern) {
		$content = preg_replace($pattern, '', $content);
	}

	return trim($content);
}

/**
 * Hide core/embed blocks in the editor content when metabox URL already outputs the player.
 *
 * @param string $block_content Block HTML.
 * @param array  $block         Block.
 */
function tnf_render_block_hide_duplicate_video_embed(string $block_content, array $block): string {
	$name = (string) ( $block['blockName'] ?? '' );
	if ($name !== 'core/embed' && $name !== 'core-embed/youtube') {
		return $block_content;
	}

	if (! is_singular('tnf_video') && ! tnf_is_news_article_singular()) {
		return $block_content;
	}

	$post_id = (int) get_the_ID();
	if ($post_id <= 0) {
		return $block_content;
	}

	$url = (string) get_post_meta($post_id, 'tnf_embed_url', true);
	if ($url === '') {
		return $block_content;
	}

	$meta_id = tnf_youtube_id_from_url($url);
	if ($meta_id === '') {
		return $block_content;
	}

	if (str_contains($block_content, $meta_id)) {
		return '';
	}

	return $block_content;
}

/**
 * Add landscape / Shorts classes to core YouTube embed blocks (editor content).
 *
 * @param string $block_content Block HTML.
 * @param array  $block         Block.
 */
function tnf_render_block_youtube_embed_aspect(string $block_content, array $block): string {
	if ($block_content === '') {
		return $block_content;
	}

	$name = (string) ( $block['blockName'] ?? '' );
	if ($name !== 'core/embed' && $name !== 'core-embed/youtube') {
		return $block_content;
	}

	if (! preg_match('#youtube\.com|youtu\.be|youtube-nocookie\.com#i', $block_content)) {
		return $block_content;
	}

	$url = '';
	if (isset($block['attrs']['url']) && is_string($block['attrs']['url'])) {
		$url = $block['attrs']['url'];
	}

	$is_short = tnf_youtube_is_shorts_url($url) || preg_match('#youtube\.com/shorts/#i', $block_content) === 1;
	$modifier = $is_short ? 'tnf-video-embed--shorts' : 'tnf-video-embed--landscape';

	if (str_contains($block_content, $modifier)) {
		return $block_content;
	}

	$data_attr = $is_short ? ' data-tnf-shorts="1"' : '';
	$replaced = preg_replace(
		'/<figure(\s+[^>]*class=")([^"]*wp-block-embed[^"]*)(")/',
		'<figure$1$2 tnf-video-embed ' . $modifier . '$3' . $data_attr,
		$block_content,
		1
	);

	if (! is_string($replaced) || $replaced === '') {
		return $block_content;
	}

	if (! str_contains($replaced, 'tnf-video-player')) {
		$mode = $is_short ? 'portrait' : 'landscape';
		return '<div class="tnf-video-player tnf-video-player--' . esc_attr($mode) . '" data-tnf-video="' . esc_attr($mode) . '">'
			. '<div class="tnf-video-player__ratio">' . $replaced . '</div></div>';
	}

	return $replaced;
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
 * True when a video should use portrait (Shorts) card layout on the homepage.
 */
function tnf_video_card_is_shorts(int $post_id): bool {
	if ($post_id <= 0) {
		return false;
	}

	$embed = (string) get_post_meta($post_id, 'tnf_embed_url', true);
	if ($embed !== '' && tnf_youtube_is_shorts_url($embed)) {
		return true;
	}

	$content = (string) get_post_field('post_content', $post_id);
	if ($content !== '' && preg_match('#https?://[^\s"\'<>]+#i', $content, $m)) {
		return tnf_youtube_is_shorts_url($m[0]);
	}

	return (bool) apply_filters('tnf_video_card_is_shorts', false, $post_id);
}

/**
 * Homepage Featured Videos — horizontal scroll rail (Shorts + landscape cards).
 *
 * @param int $count Max videos.
 */
function tnf_render_home_featured_videos_rail(int $count = 10): void {
	$count = max(1, min(20, $count));

	$videos = new WP_Query(
		array(
			'post_type'              => 'tnf_video',
			'post_status'            => 'publish',
			'posts_per_page'         => $count,
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	if (! $videos->have_posts()) {
		return;
	}

	$more_videos = get_post_type_archive_link('tnf_video');
	$more_videos = is_string($more_videos) && $more_videos !== '' ? $more_videos : home_url('/videos/');
	?>
	<section class="tnf-card tnf-featured-videos">
		<div class="tnf-cat-head">
			<h3><?php esc_html_e('Featured Videos', 'tnf-news-platform'); ?></h3>
			<a href="<?php echo esc_url($more_videos); ?>"><?php esc_html_e('See all videos', 'tnf-news-platform'); ?></a>
		</div>
		<div class="tnf-video-rail" data-tnf-video-rail tabindex="0" role="region" aria-label="<?php esc_attr_e('Featured videos', 'tnf-news-platform'); ?>">
			<?php
			while ($videos->have_posts()) :
				$videos->the_post();
				$pid       = (int) get_the_ID();
				$is_shorts = tnf_video_card_is_shorts($pid);
				$thumb     = function_exists('tnf_video_card_thumbnail_url')
					? tnf_video_card_thumbnail_url($pid)
					: ( function_exists('twentytwentyfive_tnf_video_thumbnail_url')
						? twentytwentyfive_tnf_video_thumbnail_url($pid)
						: '' );
				$card_cls  = 'tnf-video-card' . ( $is_shorts ? ' tnf-video-card--shorts' : ' tnf-video-card--landscape' );
				?>
				<article class="<?php echo esc_attr($card_cls); ?>">
					<a class="tnf-video-card__thumb" href="<?php the_permalink(); ?>">
						<?php if ($thumb !== '') : ?>
							<img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" decoding="async" />
						<?php endif; ?>
						<span class="tnf-video-play" aria-hidden="true"></span>
						<?php if ($is_shorts) : ?>
							<span class="tnf-video-card__badge"><?php esc_html_e('Short', 'tnf-news-platform'); ?></span>
						<?php endif; ?>
					</a>
					<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
					<time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date()); ?></time>
				</article>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	</section>
	<?php
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

	return function_exists('tnf_placeholder_image_url') ? tnf_placeholder_image_url() : '';
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
 * Public-facing author label: avoid raw email when display_name is an address.
 *
 * @param int $author_id User ID.
 */
function tnf_news_single_author_display_name(int $author_id): string {
	if ($author_id <= 0) {
		return '';
	}
	$display = (string) get_the_author_meta('display_name', $author_id);
	if ($display !== '' && ! is_email($display)) {
		return $display;
	}
	$nick = (string) get_the_author_meta('nickname', $author_id);
	if ($nick !== '' && ! is_email($nick)) {
		return $nick;
	}
	$first = (string) get_the_author_meta('first_name', $author_id);
	$last  = (string) get_the_author_meta('last_name', $author_id);
	$full  = trim($first . ' ' . $last);
	if ($full !== '') {
		return $full;
	}
	return __('Editorial desk', 'tnf-news-platform');
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
	$author_name = tnf_news_single_author_display_name($author_id);
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
		$byline .= '<span class="tnf-news-byline__name">' . esc_html__('Editorial desk', 'tnf-news-platform') . '</span>';
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
 * Video embed CSS — one file, loads after mobile + block library.
 */
function tnf_enqueue_video_embed_assets(): void {
	if (is_admin()) {
		return;
	}
	if (! is_singular('tnf_video') && ! tnf_is_news_article_singular()) {
		return;
	}

	$deps = array('tnf-single-news');
	foreach (array('tnf-frontend-mobile', 'tnf-mobile-app', 'tnf-child-home-news', 'tnf-frontend-chrome') as $handle) {
		if (wp_style_is($handle, 'registered') || wp_style_is($handle, 'enqueued')) {
			$deps[] = $handle;
		}
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-video-embed.css';
	if (! is_readable($path)) {
		return;
	}

	wp_enqueue_style(
		'tnf-video-embed',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-video-embed.css',
		array_values(array_unique($deps)),
		(string) filemtime($path)
	);
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
					'copyLink' => __('Copy link', 'tnf-news-platform'),
					'linkShort' => __('Link', 'tnf-news-platform'),
					'clipTool' => __('Cut clip', 'tnf-news-platform'),
					'clipHint' => __('Drag to select area. Quick tap makes an auto crop.', 'tnf-news-platform'),
					'shareClip' => __('Share clip', 'tnf-news-platform'),
					'shareClipHint' => __('Pick a network below, or copy the link to share anywhere.', 'tnf-news-platform'),
					'openClip' => __('Open clip', 'tnf-news-platform'),
					'cancelClip' => __('Cancel clip', 'tnf-news-platform'),
					'open' => __('Open', 'tnf-news-platform'),
					'close' => __('Close', 'tnf-news-platform'),
					'viewFullEdition' => __('View full edition', 'tnf-news-platform'),
					'clipShareContext' => __('Shared clip from this edition.', 'tnf-news-platform'),
					'shareThisClip' => __('Share this clip', 'tnf-news-platform'),
					'shareOnWhatsApp' => __('Share on WhatsApp', 'tnf-news-platform'),
					'shareOnFacebook' => __('Share on Facebook', 'tnf-news-platform'),
					'shareOnX' => __('Share on X', 'tnf-news-platform'),
					'shareOnLinkedIn' => __('Share on LinkedIn', 'tnf-news-platform'),
					'pdfjsWorkerSrc' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js',
				)
			);
		}
	}
}

/**
 * Branded masthead image URL for the shared clip landing view (PNG in plugin assets).
 *
 * Plugins/themes may override the URL with the `tnf_epaper_clip_brand_image_url` filter.
 */
function tnf_epaper_clip_brand_image_url(): string {
	$rel = 'assets/images/tnf-clip-masthead.png';
	$abs = TNF_NEWS_PLATFORM_PATH . $rel;
	$def = ( is_readable($abs) && is_file($abs) ) ? TNF_NEWS_PLATFORM_URL . $rel : '';

	return (string) apply_filters('tnf_epaper_clip_brand_image_url', $def);
}

/**
 * Secret for signed clip preview URLs (optional override in wp-config.php).
 */
function tnf_epaper_clip_og_secret(): string {
	if (defined('TNF_EPAPER_CLIP_OG_SECRET') && is_string(TNF_EPAPER_CLIP_OG_SECRET) && TNF_EPAPER_CLIP_OG_SECRET !== '') {
		return (string) TNF_EPAPER_CLIP_OG_SECRET;
	}

	return wp_hash('tnf_epaper_clip_og_v1');
}

/**
 * Normalize clip rectangle (same rules as the e-paper viewer script).
 *
 * @return array{cx: float, cy: float, cw: float, ch: float}
 */
function tnf_epaper_clip_normalize_params(float $cx, float $cy, float $cw, float $ch): array {
	$cx = max(0.0, min(1.0, $cx));
	$cy = max(0.0, min(1.0, $cy));
	$cw = max(0.02, min(1.0, $cw));
	$ch = max(0.02, min(1.0, $ch));
	if ($cx + $cw > 1.0) {
		$cw = 1.0 - $cx;
	}
	if ($cy + $ch > 1.0) {
		$ch = 1.0 - $cy;
	}

	return array(
		'cx' => $cx,
		'cy' => $cy,
		'cw' => max(0.02, $cw),
		'ch' => max(0.02, $ch),
	);
}

/**
 * Canonical string for clip URL signatures.
 */
function tnf_epaper_clip_canonical_payload(int $post_id, int $pg, float $cx, float $cy, float $cw, float $ch, int $exp): string {
	$n = tnf_epaper_clip_normalize_params($cx, $cy, $cw, $ch);

	return sprintf(
		'v1|%d|%d|%s|%s|%s|%s|%d',
		$post_id,
		$pg,
		number_format($n['cx'], 4, '.', ''),
		number_format($n['cy'], 4, '.', ''),
		number_format($n['cw'], 4, '.', ''),
		number_format($n['ch'], 4, '.', ''),
		$exp
	);
}

/**
 * HMAC for clip preview URL.
 */
function tnf_epaper_clip_sign(int $post_id, int $pg, float $cx, float $cy, float $cw, float $ch, int $exp): string {
	$payload = tnf_epaper_clip_canonical_payload($post_id, $pg, $cx, $cy, $cw, $ch, $exp);

	return hash_hmac('sha256', $payload, tnf_epaper_clip_og_secret());
}

/**
 * Whether clip query params and signature are valid.
 */
function tnf_epaper_clip_verify(int $post_id, int $pg, float $cx, float $cy, float $cw, float $ch, int $exp, string $sig): bool {
	if ($exp < time() || $exp > time() + ( 86400 * 400 )) {
		return false;
	}
	if ($pg < 1) {
		return false;
	}
	$expected = tnf_epaper_clip_sign($post_id, $pg, $cx, $cy, $cw, $ch, $exp);

	return $sig !== '' && hash_equals($expected, $sig);
}

/**
 * Parse clip mode query string (tnf_clip=1 + bbox) from the current request.
 *
 * @return array{pg: int, cx: float, cy: float, cw: float, ch: float}|null
 */
function tnf_epaper_parse_clip_from_get(): ?array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only; output meta only.
	if (! isset($_GET['tnf_clip']) || (string) wp_unslash((string) $_GET['tnf_clip']) !== '1') {
		return null;
	}

	$pg = isset($_GET['tnf_pg']) ? absint((string) wp_unslash((string) $_GET['tnf_pg'])) : 0;
	if ($pg < 1) {
		return null;
	}

	if (! isset($_GET['tnf_cx'], $_GET['tnf_cy'], $_GET['tnf_cw'], $_GET['tnf_ch'])) {
		return null;
	}

	$cx = (float) wp_unslash((string) $_GET['tnf_cx']);
	$cy = (float) wp_unslash((string) $_GET['tnf_cy']);
	$cw = (float) wp_unslash((string) $_GET['tnf_cw']);
	$ch = (float) wp_unslash((string) $_GET['tnf_ch']);
	if (is_nan($cx) || is_nan($cy) || is_nan($cw) || is_nan($ch)) {
		return null;
	}
	if ($cw <= 0.0 || $ch <= 0.0) {
		return null;
	}

	return array(
		'pg' => $pg,
		'cx' => $cx,
		'cy' => $cy,
		'cw' => $cw,
		'ch' => $ch,
	);
}

/**
 * Absolute signed REST URL that returns a JPEG of the clip (for og:image).
 *
 * @param array{pg: int, cx: float, cy: float, cw: float, ch: float} $clip Clip params.
 */
function tnf_epaper_clip_signed_image_url(int $post_id, array $clip): string {
	$n = tnf_epaper_clip_normalize_params((float) $clip['cx'], (float) $clip['cy'], (float) $clip['cw'], (float) $clip['ch']);
	$ttl = (int) apply_filters('tnf_epaper_clip_sig_ttl', 90 * DAY_IN_SECONDS);
	$exp = time() + max((int) DAY_IN_SECONDS, $ttl);
	$sig = tnf_epaper_clip_sign($post_id, (int) $clip['pg'], $n['cx'], $n['cy'], $n['cw'], $n['ch'], $exp);

	return add_query_arg(
		array(
			'pg'  => (int) $clip['pg'],
			'cx'  => (float) number_format($n['cx'], 4, '.', ''),
			'cy'  => (float) number_format($n['cy'], 4, '.', ''),
			'cw'  => (float) number_format($n['cw'], 4, '.', ''),
			'ch'  => (float) number_format($n['ch'], 4, '.', ''),
			'exp' => $exp,
			'sig' => $sig,
		),
		rest_url('tnf/v1/pdf-report/' . $post_id . '/clip-og')
	);
}

/**
 * Signed clip preview URL for the current singular request, or empty string.
 */
function tnf_epaper_clip_og_url_for_request(): string {
	if (! is_singular('tnf_pdf_report')) {
		return '';
	}

	$post_id = (int) get_queried_object_id();
	if ($post_id <= 0) {
		return '';
	}

	$clip = tnf_epaper_parse_clip_from_get();
	if ($clip === null) {
		return '';
	}

	return tnf_epaper_clip_signed_image_url($post_id, $clip);
}

/**
 * Build JPEG bytes for a clip region (cached under uploads).
 *
 * @return string|WP_Error
 */
function tnf_epaper_clip_build_jpeg(int $post_id, int $pg, float $cx, float $cy, float $cw, float $ch) {
	$n = tnf_epaper_clip_normalize_params($cx, $cy, $cw, $ch);

	$pages = tnf_pdf_report_viewer_pages($post_id);
	$url   = '';
	foreach ($pages as $row) {
		if ((int) $row['page'] === $pg) {
			$url = $row['url'];
			break;
		}
	}
	if ($url === '') {
		return new WP_Error('no_page', __('Page image not found', 'tnf-news-platform'), array('status' => 404));
	}

	$upload = wp_upload_dir();
	if (! empty($upload['error'])) {
		return new WP_Error('upload_dir', (string) $upload['error'], array('status' => 500));
	}

	$cache_key = hash(
		'sha256',
		implode(
			'|',
			array(
				(string) $post_id,
				(string) $pg,
				number_format($n['cx'], 4, '.', ''),
				number_format($n['cy'], 4, '.', ''),
				number_format($n['cw'], 4, '.', ''),
				number_format($n['ch'], 4, '.', ''),
				$url,
			)
		)
	);
	$cache_dir  = trailingslashit($upload['basedir']) . 'tnf-epaper-clip-cache';
	$cache_file = '';
	if (wp_mkdir_p($cache_dir)) {
		$cache_file = trailingslashit($cache_dir) . $cache_key . '.jpg';
		if (is_readable($cache_file) && ( time() - (int) filemtime($cache_file) ) < 7 * DAY_IN_SECONDS) {
			$cached = file_get_contents($cache_file);
			if (is_string($cached) && $cached !== '') {
				return $cached;
			}
		}
	}

	$tmp = download_url(esc_url_raw($url), 60);
	if (is_wp_error($tmp)) {
		return $tmp;
	}

	$editor = wp_get_image_editor($tmp);
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink($tmp);
	if (is_wp_error($editor)) {
		return $editor;
	}

	$editor->set_quality(82);

	$size = $editor->get_size();
	if (! is_array($size) || empty($size['width']) || empty($size['height'])) {
		return new WP_Error('bad_image', __('Could not read image size', 'tnf-news-platform'), array('status' => 422));
	}

	$iw = (int) $size['width'];
	$ih = (int) $size['height'];

	$src_x = (int) floor($n['cx'] * $iw);
	$src_y = (int) floor($n['cy'] * $ih);
	$src_w = max(1, (int) round($n['cw'] * $iw));
	$src_h = max(1, (int) round($n['ch'] * $ih));
	$src_x = min($src_x, max(0, $iw - 1));
	$src_y = min($src_y, max(0, $ih - 1));
	$src_w = min($src_w, $iw - $src_x);
	$src_h = min($src_h, $ih - $src_y);

	$cropped = $editor->crop($src_x, $src_y, $src_w, $src_h);
	if (is_wp_error($cropped)) {
		return $cropped;
	}

	$dims   = $editor->get_size();
	$ow     = isset($dims['width']) ? (int) $dims['width'] : 0;
	$oh     = isset($dims['height']) ? (int) $dims['height'] : 0;
	$min_s  = (int) min($ow, $oh);
	$max_s  = (int) max($ow, $oh);
	if ($min_s > 0 && $min_s < 200) {
		$scale = 200 / $min_s;
		$nw    = (int) min(1200, ceil($ow * $scale));
		$nh    = (int) min(1200, ceil($oh * $scale));
		$editor->resize($nw, $nh, false);
	} elseif ($max_s > 1200) {
		$editor->resize(1200, 1200, false);
	}

	$out_path = trailingslashit(get_temp_dir()) . 'tnf-clip-' . wp_generate_password(16, false, false) . '.jpg';
	$saved    = $editor->save($out_path, 'image/jpeg');
	if (is_wp_error($saved)) {
		return $saved;
	}

	$path = isset($saved['path']) ? (string) $saved['path'] : '';
	if ($path === '' || ! is_readable($path)) {
		return new WP_Error('save', __('Could not save clip image', 'tnf-news-platform'), array('status' => 500));
	}

	$bytes = file_get_contents($path);
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@unlink($path);
	if (! is_string($bytes) || $bytes === '') {
		return new WP_Error('read', __('Could not read clip image', 'tnf-news-platform'), array('status' => 500));
	}

	if ($cache_file !== '') {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@file_put_contents($cache_file, $bytes);
	}

	return $bytes;
}
