<?php
/**
 * Capacitor Android app integration (live server WebView).
 *
 * Detects the native shell via User-Agent (see mobile-app/capacitor.config.ts appendUserAgent)
 * or ?tnf_app=1 for QA. Does not alter backend APIs, CPTs, or auth handlers.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/** Capacitor UA token — keep in sync with capacitor.config.ts android.appendUserAgent */
define('TNF_CAPACITOR_UA_TOKEN', 'TNFTodayCapacitor');

/**
 * Register mobile responsive + Capacitor app hooks.
 */
function tnf_register_mobile_app(): void {
	add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_mobile_styles', 40);
	add_filter('body_class', 'tnf_mobile_app_body_class');
	add_action('wp_enqueue_scripts', 'tnf_enqueue_page_navigation_loader', 44);
	add_action('wp_enqueue_scripts', 'tnf_enqueue_mobile_app_assets', 45);
	add_action('admin_enqueue_scripts', 'tnf_enqueue_admin_page_navigation_loader', 44);
	add_action('template_redirect', 'tnf_mobile_app_persist_preview_cookie', 0);
	add_action('wp_footer', 'tnf_mobile_app_render_page_loader_shell', 0);
	add_action('admin_footer', 'tnf_mobile_app_render_page_loader_shell', 0);
	add_action('admin_head', 'tnf_mobile_app_resume_loader_script', 2);
	add_action('wp_footer', 'tnf_mobile_app_render_bottom_nav', 5);
	add_action('wp_footer', 'tnf_mobile_app_render_offline_shell', 1);
	add_action('wp_head', 'tnf_mobile_app_viewport_meta', 1);
	add_action('wp_head', 'tnf_mobile_app_resume_loader_script', 2);
	add_filter('tnf_mobile_app_enabled', 'tnf_mobile_app_default_enabled', 10, 0);
	add_action('template_redirect', 'tnf_mobile_app_block_wp_admin', 1);
}

/**
 * True when request is from the Capacitor Android shell (or forced for testing).
 */
/**
 * Remember ?tnf_app=1 for local/browser mobile testing (not the real APK UA).
 */
function tnf_mobile_app_persist_preview_cookie(): void {
	if (! isset($_GET['tnf_app']) || '1' !== (string) wp_unslash($_GET['tnf_app'])) {
		return;
	}
	if (headers_sent()) {
		return;
	}
	$path   = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
	$domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
	setcookie('tnf_app_preview', '1', time() + 14 * DAY_IN_SECONDS, $path, $domain, is_ssl(), true);
}

/**
 * @return bool
 */
function tnf_mobile_app_preview_cookie_active(): bool {
	return isset($_COOKIE['tnf_app_preview']) && '1' === (string) wp_unslash($_COOKIE['tnf_app_preview']);
}

function tnf_is_capacitor_app(): bool {
	if (isset($_GET['tnf_app']) && '1' === (string) wp_unslash($_GET['tnf_app'])) {
		return true;
	}
	if (tnf_mobile_app_preview_cookie_active()) {
		return true;
	}

	$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if ($ua === '') {
		return false;
	}

	return str_contains($ua, TNF_CAPACITOR_UA_TOKEN);
}

/**
 * Allow disabling via filter (e.g. staging).
 */
function tnf_mobile_app_default_enabled(): bool {
	return tnf_is_capacitor_app();
}

/**
 * Whether mobile-app chrome should load.
 */
function tnf_mobile_app_active(): bool {
	return (bool) apply_filters('tnf_mobile_app_enabled', tnf_is_capacitor_app());
}

/**
 * Unified mobile CSS for all front-end pages (phones + app WebView).
 */
function tnf_enqueue_frontend_mobile_styles(): void {
	if (is_admin()) {
		return;
	}

	if (! wp_is_mobile() && ! tnf_mobile_app_active()) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-mobile.css';
	if (! is_readable($path)) {
		return;
	}

	$deps = array('tnf-frontend-chrome');
	if (wp_style_is('tnf-child-home-news', 'registered') || wp_style_is('tnf-child-home-news', 'enqueued')) {
		$deps[] = 'tnf-child-home-news';
	}

	wp_enqueue_style(
		'tnf-frontend-mobile',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-mobile.css',
		$deps,
		(string) filemtime($path)
	);
}

/**
 * Body classes for app shell styling.
 *
 * @param array<int,string> $classes Classes.
 * @return array<int,string>
 */
function tnf_mobile_app_body_class(array $classes): array {
	if (! tnf_mobile_app_active()) {
		return $classes;
	}

	$classes[] = 'tnf-capacitor-app';
	$classes[] = 'tnf-mobile-app-shell';

	if (is_user_logged_in()) {
		$classes[] = 'tnf-mobile-app--logged-in';
		if (current_user_can('edit_posts')) {
			$classes[] = 'tnf-mobile-app--editor';
		}
	} else {
		$classes[] = 'tnf-mobile-app--guest';
	}

	if (is_front_page() || is_home()) {
		$classes[] = 'tnf-mobile-app--home';
	}
	if (is_page('epaper') || is_singular('tnf_pdf_report') || is_post_type_archive('tnf_pdf_report')) {
		$classes[] = 'tnf-mobile-app--epaper';
	}
	if (is_page('videos') || is_singular('tnf_video') || is_post_type_archive('tnf_video')) {
		$classes[] = 'tnf-mobile-app--videos';
	}
	if (tnf_is_auth_page()) {
		$classes[] = 'tnf-mobile-app--auth';
	}
	if (is_page('my-account')) {
		$classes[] = 'tnf-mobile-app--dashboard';
	}

	return $classes;
}

/**
 * Strong viewport for WebView (safe-area, no accidental zoom on inputs).
 */
function tnf_mobile_app_viewport_meta(): void {
	if (! tnf_mobile_app_active()) {
		return;
	}
	echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=5, user-scalable=yes" />' . "\n";
}

/**
 * Whether the logo page loader runs (default on for local QA; filter to disable in production).
 */
function tnf_page_navigation_loader_enabled(): bool {
	$default = tnf_mobile_app_active() || (is_admin() && current_user_can('edit_posts'));
	return (bool) apply_filters('tnf_page_navigation_loader_enabled', $default);
}

/**
 * Enqueue loader CSS + bridge on the public site (all viewports).
 */
function tnf_enqueue_page_navigation_loader(): void {
	if (is_admin() || ! tnf_page_navigation_loader_enabled()) {
		return;
	}
	tnf_enqueue_navigation_loader_assets(tnf_mobile_app_active());
}

/**
 * Enqueue loader in wp-admin for editorial users (desktop + mobile CMS).
 *
 * @param string $hook_suffix Hook suffix.
 */
function tnf_enqueue_admin_page_navigation_loader(string $hook_suffix): void {
	unset($hook_suffix);
	if (! current_user_can('edit_posts') || ! tnf_page_navigation_loader_enabled()) {
		return;
	}
	tnf_enqueue_navigation_loader_assets(false);
}

/**
 * Shared loader assets (frontend and wp-admin).
 *
 * @param bool $is_app True when Capacitor / ?tnf_app=1 app chrome should run.
 */
function tnf_enqueue_navigation_loader_assets(bool $is_app): void {
	$css_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/tnf-page-loader.css';
	if (is_readable($css_path)) {
		wp_enqueue_style(
			'tnf-page-loader',
			TNF_NEWS_PLATFORM_URL . 'assets/css/tnf-page-loader.css',
			array(),
			(string) filemtime($css_path)
		);
	}

	$js_path = TNF_NEWS_PLATFORM_PATH . 'assets/js/mobile-app-bridge.js';
	if (! is_readable($js_path)) {
		return;
	}

	wp_enqueue_script(
		'tnf-mobile-app-bridge',
		TNF_NEWS_PLATFORM_URL . 'assets/js/mobile-app-bridge.js',
		array(),
		(string) filemtime($js_path),
		true
	);

	$is_editor = is_user_logged_in() && current_user_can('edit_posts');
	wp_localize_script(
		'tnf-mobile-app-bridge',
		'tnfMobileApp',
		array(
			'homeUrl'     => home_url('/'),
			'epaperUrl'   => home_url('/epaper/'),
			'videosUrl'   => home_url('/videos/'),
			'loginUrl'    => function_exists('tnf_auth_page_url') ? tnf_auth_page_url('login') : home_url('/login/'),
			'accountUrl'  => function_exists('tnf_auth_page_url') ? tnf_auth_page_url('my-account') : home_url('/my-account/'),
			'isLoggedIn'  => is_user_logged_in(),
			'isEditor'    => $is_editor,
			'cmsUrl'      => $is_editor ? admin_url() : '',
			'addNewsUrl'  => $is_editor ? admin_url('post-new.php?post_type=tnf_news') : '',
			'pullRefresh' => $is_app,
			'logoUrl'     => tnf_mobile_app_logo_url(),
			'appQuery'    => $is_app ? 'tnf_app=1' : '',
			'isApp'       => $is_app,
			'i18n'        => array(
				'loading'      => __('Loading…', 'tnf-news-platform'),
				'offlineTitle' => __('No internet connection', 'tnf-news-platform'),
				'offlineBody'  => __('Check your connection and try again.', 'tnf-news-platform'),
				'retry'        => __('Retry', 'tnf-news-platform'),
				'navHome'      => __('Home', 'tnf-news-platform'),
				'navEpaper'    => __('ePaper', 'tnf-news-platform'),
				'navVideos'    => __('Videos', 'tnf-news-platform'),
				'navMenu'      => __('Menu', 'tnf-news-platform'),
				'navAccount'   => __('Account', 'tnf-news-platform'),
			),
		)
	);
}

/**
 * Enqueue app-only CSS (served from production like all plugin assets).
 */
function tnf_enqueue_mobile_app_assets(): void {
	if (is_admin() || ! tnf_mobile_app_active()) {
		return;
	}

	$css_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-mobile-app.css';
	if (is_readable($css_path)) {
		wp_enqueue_style(
			'tnf-mobile-app',
			TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-mobile-app.css',
			array('tnf-frontend-mobile', 'tnf-page-loader'),
			(string) filemtime($css_path)
		);
	}

	$ux_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-app-experience.css';
	if (is_readable($ux_path)) {
		wp_enqueue_style(
			'tnf-app-experience',
			TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-app-experience.css',
			array('tnf-mobile-app'),
			(string) filemtime($ux_path)
		);
	}
}

/**
 * Bottom tab bar (app shell only; desktop unchanged).
 */
function tnf_mobile_app_render_bottom_nav(): void {
	if (! tnf_mobile_app_active()) {
		return;
	}

	$epaper_url = get_post_type_archive_link('tnf_pdf_report');
	$epaper_url = is_string($epaper_url) && $epaper_url !== '' ? $epaper_url : home_url('/pdf-reports/');
	$videos_url = get_post_type_archive_link('tnf_video');
	$videos_url = is_string($videos_url) && $videos_url !== '' ? $videos_url : home_url('/videos/');

	$home_raw    = home_url('/');
	$epaper_raw  = $epaper_url;
	$videos_raw  = $videos_url;
	$account_raw = function_exists('tnf_auth_page_url')
		? tnf_auth_page_url(is_user_logged_in() ? 'my-account' : 'login')
		: home_url(is_user_logged_in() ? '/my-account/' : '/login/');

	$home    = esc_url(tnf_mobile_app_preserve_query($home_raw));
	$epaper  = esc_url(tnf_mobile_app_preserve_query($epaper_raw));
	$videos  = esc_url(tnf_mobile_app_preserve_query($videos_raw));
	$account = esc_url(tnf_mobile_app_preserve_query($account_raw));

	$active = 'home';
	if (is_page('epaper') || is_singular('tnf_pdf_report') || is_post_type_archive('tnf_pdf_report')) {
		$active = 'epaper';
	} elseif (is_page('videos') || is_singular('tnf_video') || is_post_type_archive('tnf_video')) {
		$active = 'videos';
	} elseif (is_page('my-account') || tnf_is_auth_page()) {
		$active = 'account';
	}

	$account_label = __('Sign In', 'tnf-news-platform');
	if (is_user_logged_in()) {
		$user = wp_get_current_user();
		$name = function_exists('tnf_admin_user_greeting_name')
			? tnf_admin_user_greeting_name($user)
			: ($user->display_name ?: $user->user_login);
		$account_label = $name !== '' ? $name : __('Account', 'tnf-news-platform');
	}

	$icon = static function (string $name): string {
		return function_exists('tnf_chrome_icon_svg') ? tnf_chrome_icon_svg($name) : '';
	};

	?>
	<nav class="tnf-app-bottom-nav" aria-label="<?php esc_attr_e('App navigation', 'tnf-news-platform'); ?>">
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'home' ? ' is-active' : ''; ?>" href="<?php echo $home; ?>" data-tnf-nav="home">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true"><?php echo $icon('home'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Home', 'tnf-news-platform'); ?></span>
		</a>
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'epaper' ? ' is-active' : ''; ?>" href="<?php echo $epaper; ?>" data-tnf-nav="epaper">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true"><?php echo $icon('epaper'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('ePaper', 'tnf-news-platform'); ?></span>
		</a>
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'videos' ? ' is-active' : ''; ?>" href="<?php echo $videos; ?>" data-tnf-nav="videos">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true"><?php echo $icon('video'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Videos', 'tnf-news-platform'); ?></span>
		</a>
		<button type="button" class="tnf-app-bottom-nav__item" data-tnf-app-menu="1" aria-label="<?php esc_attr_e('Open menu', 'tnf-news-platform'); ?>">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true"><?php echo $icon('menu'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Menu', 'tnf-news-platform'); ?></span>
		</button>
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'account' ? ' is-active' : ''; ?><?php echo is_user_logged_in() ? ' is-signed-in' : ''; ?>" href="<?php echo $account; ?>" data-tnf-nav="account">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true"><?php echo $icon('account'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="tnf-app-bottom-nav__label"><?php echo esc_html($account_label); ?></span>
		</a>
	</nav>
	<?php
}

/**
 * Keep ?tnf_app=1 on internal app links during browser QA.
 *
 * @param string $url Target URL.
 */
function tnf_mobile_app_preserve_query(string $url): string {
	if (! tnf_mobile_app_preview_cookie_active() && ! isset($_GET['tnf_app'])) {
		return $url;
	}
	return add_query_arg('tnf_app', '1', $url);
}

/**
 * Site / theme logo for app loading screen.
 */
function tnf_mobile_app_logo_url(): string {
	if (function_exists('tnf_admin_brand_logo_url')) {
		$url = tnf_admin_brand_logo_url();
		if ($url !== '') {
			return $url;
		}
	}
	if (function_exists('get_theme_mod')) {
		$logo_id = (int) get_theme_mod('custom_logo');
		if ($logo_id > 0) {
			$url = wp_get_attachment_image_url($logo_id, 'medium');
			if (is_string($url) && $url !== '') {
				return $url;
			}
		}
	}
	$icon_id = (int) get_option('site_icon');
	if ($icon_id > 0) {
		$url = wp_get_attachment_image_url($icon_id, 'medium');
		if (is_string($url) && $url !== '') {
			return $url;
		}
	}
	return '';
}

/**
 * Full-screen logo loader (top of body for fast paint on navigation).
 */
function tnf_mobile_app_render_page_loader_shell(): void {
	if (! tnf_page_navigation_loader_enabled()) {
		return;
	}
	if (is_admin() && ! current_user_can('edit_posts')) {
		return;
	}
	$logo = tnf_mobile_app_logo_url();
	?>
	<div id="tnf-app-page-loader" class="tnf-app-page-loader" hidden aria-live="polite" aria-busy="false" aria-label="<?php esc_attr_e('Loading', 'tnf-news-platform'); ?>">
		<div class="tnf-app-page-loader__inner">
			<div class="tnf-app-page-loader__stage">
				<div class="tnf-app-page-loader__orbit" aria-hidden="true">
					<span class="tnf-app-page-loader__ring tnf-app-page-loader__ring--outer"></span>
					<span class="tnf-app-page-loader__ring tnf-app-page-loader__ring--mid"></span>
					<span class="tnf-app-page-loader__ring tnf-app-page-loader__ring--track"></span>
					<span class="tnf-app-page-loader__glow"></span>
				</div>
				<div class="tnf-app-page-loader__logo-wrap">
					<?php if ($logo !== '') : ?>
						<img class="tnf-app-page-loader__logo" src="<?php echo esc_url($logo); ?>" alt="" width="140" height="56" decoding="async" />
					<?php else : ?>
						<p class="tnf-app-page-loader__brand"><?php esc_html_e('TNF Today', 'tnf-news-platform'); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<span class="tnf-app-page-loader__sr"><?php esc_html_e('Loading', 'tnf-news-platform'); ?></span>
		</div>
	</div>
	<?php
}

/**
 * If previous tap started navigation, show loader before main paint.
 */
function tnf_mobile_app_resume_loader_script(): void {
	if (! tnf_page_navigation_loader_enabled()) {
		return;
	}
	if (is_admin() && ! current_user_can('edit_posts')) {
		return;
	}
	?>
	<script id="tnf-app-loader-resume">
	(function () {
		try {
			if (sessionStorage.getItem('tnf_app_nav') === '1') {
				document.documentElement.classList.add('tnf-app-nav-pending');
			}
		} catch (e) {}
	})();
	</script>
	<?php
}

/**
 * Offline overlay markup (shown/hidden by mobile-app-bridge.js).
 */
function tnf_mobile_app_render_offline_shell(): void {
	if (! tnf_mobile_app_active()) {
		return;
	}
	?>
	<div id="tnf-app-offline" class="tnf-app-offline" hidden>
		<div class="tnf-app-offline__card">
			<p class="tnf-app-offline__title"><?php esc_html_e('No internet connection', 'tnf-news-platform'); ?></p>
			<p class="tnf-app-offline__body"><?php esc_html_e('Check your connection and try again.', 'tnf-news-platform'); ?></p>
			<button type="button" class="tnf-app-offline__retry" data-tnf-offline-retry="1"><?php esc_html_e('Retry', 'tnf-news-platform'); ?></button>
		</div>
	</div>
	<div id="tnf-app-refresh" class="tnf-app-refresh" aria-hidden="true" hidden>
		<span class="tnf-app-refresh__spinner"></span>
	</div>
	<?php
}

/**
 * Keep wp-admin out of the main WebView (broken UX). Editors open CMS via in-app browser from Account.
 */
function tnf_mobile_app_block_wp_admin(): void {
	if (! tnf_mobile_app_active()) {
		return;
	}

	$uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
	if ($uri === '' || stripos($uri, '/wp-admin') === false) {
		return;
	}

	// Allow REST/AJAX if ever hit from WebView.
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return;
	}
	if (wp_doing_ajax()) {
		return;
	}

	$dest = function_exists('tnf_auth_page_url') && is_user_logged_in()
		? tnf_auth_page_url('my-account')
		: (function_exists('tnf_auth_page_url') ? tnf_auth_page_url('login') : home_url('/'));

	wp_safe_redirect(add_query_arg('tnf_cms', '1', $dest), 302);
	exit;
}

/**
 * Editorial quick links for native app Account screen (admins / editors).
 *
 * @return array<int, array{label: string, url: string, desc: string}>
 */
function tnf_mobile_app_editorial_links(): array {
	if (! is_user_logged_in() || ! current_user_can('edit_posts')) {
		return array();
	}

	$links = array(
		array(
			'label' => __('CMS dashboard', 'tnf-news-platform'),
			'url'   => admin_url(),
			'desc'  => __('Stats, team, publish news', 'tnf-news-platform'),
		),
		array(
			'label' => __('Add news', 'tnf-news-platform'),
			'url'   => admin_url('post-new.php?post_type=tnf_news'),
			'desc'  => __('New article', 'tnf-news-platform'),
		),
		array(
			'label' => __('All news', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_news'),
			'desc'  => __('Edit published stories', 'tnf-news-platform'),
		),
		array(
			'label' => __('ePaper', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_pdf_report'),
			'desc'  => __('PDF editions', 'tnf-news-platform'),
		),
	);

	if (current_user_can('edit_others_tnf_submissions') || current_user_can('manage_options')) {
		$links[] = array(
			'label' => __('Submissions', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_user_submission'),
			'desc'  => __('Review member posts', 'tnf-news-platform'),
		);
	}

	return $links;
}

/**
 * Render editorial hub on My Account when in Capacitor app.
 */
function tnf_mobile_app_render_account_editorial_hub(): void {
	if (! tnf_mobile_app_active() || ! is_user_logged_in()) {
		return;
	}

	$links = tnf_mobile_app_editorial_links();
	if ($links === array()) {
		return;
	}

	$name = function_exists('tnf_admin_user_greeting_name')
		? tnf_admin_user_greeting_name(wp_get_current_user())
		: (wp_get_current_user()->display_name ?: '');
	?>
	<section class="tnf-app-editorial-hub" aria-labelledby="tnf-app-editorial-heading">
		<h2 id="tnf-app-editorial-heading" class="tnf-app-editorial-hub__title">
			<?php esc_html_e('Editorial workspace', 'tnf-news-platform'); ?>
		</h2>
		<?php if ($name !== '') : ?>
			<p class="tnf-app-editorial-hub__lead">
				<?php
				printf(
					/* translators: %s: name */
					esc_html__('Signed in as %s. CMS opens in a secure editor view (not inside the news feed).', 'tnf-news-platform'),
					esc_html($name)
				);
				?>
			</p>
		<?php endif; ?>
		<div class="tnf-app-editorial-hub__grid">
			<?php foreach ($links as $link) : ?>
				<a class="tnf-app-editorial-hub__card" href="<?php echo esc_url($link['url']); ?>" data-tnf-open-browser="1">
					<span class="tnf-app-editorial-hub__label"><?php echo esc_html($link['label']); ?></span>
					<span class="tnf-app-editorial-hub__desc"><?php echo esc_html($link['desc']); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
