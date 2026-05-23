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
	add_action('template_redirect', 'tnf_mobile_app_block_wp_admin', 1);
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

	$ux_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-app-experience.css';
	if (is_readable($ux_path)) {
		wp_enqueue_style(
			'tnf-app-experience',
			TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-app-experience.css',
			array('tnf-mobile-app'),
			(string) filemtime($ux_path)
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
	} elseif (is_page('my-account') || tnf_is_auth_page()) {
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
