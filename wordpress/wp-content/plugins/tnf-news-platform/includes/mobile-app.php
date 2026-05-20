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
	add_action('wp_enqueue_scripts', 'tnf_enqueue_mobile_app_assets', 45);
	add_action('wp_footer', 'tnf_mobile_app_render_bottom_nav', 5);
	add_action('wp_footer', 'tnf_mobile_app_render_offline_shell', 1);
	add_action('wp_head', 'tnf_mobile_app_viewport_meta', 1);
	add_filter('tnf_mobile_app_enabled', 'tnf_mobile_app_default_enabled', 10, 0);
}

/**
 * True when request is from the Capacitor Android shell (or forced for testing).
 */
function tnf_is_capacitor_app(): bool {
	if (isset($_GET['tnf_app']) && '1' === (string) wp_unslash($_GET['tnf_app'])) {
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
 * Enqueue app-only CSS/JS (served from production like all plugin assets).
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
			array('tnf-frontend-mobile'),
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
			'pullRefresh' => true,
			'i18n'        => array(
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
 * Bottom tab bar (app shell only; desktop unchanged).
 */
function tnf_mobile_app_render_bottom_nav(): void {
	if (! tnf_mobile_app_active()) {
		return;
	}

	$home    = esc_url(home_url('/'));
	$epaper  = esc_url(home_url('/epaper/'));
	$videos  = esc_url(home_url('/videos/'));
	$account = function_exists('tnf_auth_page_url')
		? esc_url(tnf_auth_page_url(is_user_logged_in() ? 'my-account' : 'login'))
		: esc_url(home_url(is_user_logged_in() ? '/my-account/' : '/login/'));

	$active = 'home';
	if (is_page('epaper') || is_singular('tnf_pdf_report')) {
		$active = 'epaper';
	} elseif (is_page('videos') || is_singular('tnf_video')) {
		$active = 'videos';
	} elseif (tnf_is_auth_page()) {
		$active = 'account';
	}

	?>
	<nav class="tnf-app-bottom-nav" aria-label="<?php esc_attr_e('App navigation', 'tnf-news-platform'); ?>">
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'home' ? ' is-active' : ''; ?>" href="<?php echo $home; ?>" data-tnf-nav="home">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true">⌂</span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Home', 'tnf-news-platform'); ?></span>
		</a>
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'epaper' ? ' is-active' : ''; ?>" href="<?php echo $epaper; ?>" data-tnf-nav="epaper">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true">▤</span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('ePaper', 'tnf-news-platform'); ?></span>
		</a>
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'videos' ? ' is-active' : ''; ?>" href="<?php echo $videos; ?>" data-tnf-nav="videos">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true">▶</span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Videos', 'tnf-news-platform'); ?></span>
		</a>
		<button type="button" class="tnf-app-bottom-nav__item" data-tnf-app-menu="1" aria-label="<?php esc_attr_e('Open menu', 'tnf-news-platform'); ?>">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true">☰</span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Menu', 'tnf-news-platform'); ?></span>
		</button>
		<a class="tnf-app-bottom-nav__item<?php echo $active === 'account' ? ' is-active' : ''; ?>" href="<?php echo $account; ?>" data-tnf-nav="account">
			<span class="tnf-app-bottom-nav__icon" aria-hidden="true">●</span>
			<span class="tnf-app-bottom-nav__label"><?php esc_html_e('Account', 'tnf-news-platform'); ?></span>
		</a>
	</nav>
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
