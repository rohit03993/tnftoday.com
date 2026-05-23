<?php
/**
 * Mobile wp-admin — app-style editorial UI (phones / narrow screens).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register mobile admin hooks.
 */
function tnf_register_admin_mobile(): void {
	add_filter('admin_body_class', 'tnf_admin_mobile_body_class');
	add_filter('use_block_editor_for_post_type', 'tnf_admin_disable_block_editor_on_mobile', 100, 2);
	add_filter('use_block_editor_for_post', 'tnf_admin_disable_block_editor_for_post_mobile', 100, 2);
	add_action('admin_enqueue_scripts', 'tnf_admin_enqueue_mobile_styles', 1000);
	add_action('admin_footer', 'tnf_admin_mobile_footer_shell', 5);
	add_action('admin_footer', 'tnf_admin_mobile_inline_menu_boot', 20);
	add_action('admin_head', 'tnf_admin_mobile_head', 99);
}

/**
 * Phone / tablet CMS context (real device or ?tnf_mob=1 for narrow-window QA).
 */
function tnf_admin_is_mobile_cms_context(): bool {
	if (isset($_GET['tnf_mob']) && '1' === (string) wp_unslash($_GET['tnf_mob'])) {
		return true;
	}
	return tnf_admin_is_mobile_view();
}

/**
 * TNF editorial post types edited in wp-admin.
 *
 * @return array<int, string>
 */
function tnf_admin_mobile_editor_post_types(): array {
	return array('tnf_news', 'tnf_pdf_report', 'tnf_video', 'post');
}

/**
 * TNF newsroom uses classic editor (works on phone + desktop). Gutenberg stacks toolbars on mobile.
 *
 * @param bool   $use_block  Use block editor.
 * @param string $post_type  Post type.
 */
function tnf_admin_disable_block_editor_on_mobile(bool $use_block, string $post_type): bool {
	if (in_array($post_type, array('tnf_news', 'tnf_pdf_report', 'tnf_video'), true)) {
		return false;
	}
	if (tnf_admin_is_mobile_cms_context() && in_array($post_type, tnf_admin_mobile_editor_post_types(), true)) {
		return false;
	}
	return $use_block;
}

/**
 * @param bool    $use_block Use block editor.
 * @param WP_Post $post      Post.
 */
function tnf_admin_disable_block_editor_for_post_mobile(bool $use_block, WP_Post $post): bool {
	if ($post instanceof WP_Post) {
		if (in_array($post->post_type, array('tnf_news', 'tnf_pdf_report', 'tnf_video'), true)) {
			return false;
		}
		if (tnf_admin_is_mobile_cms_context() && in_array($post->post_type, tnf_admin_mobile_editor_post_types(), true)) {
			return false;
		}
	}
	return $use_block;
}

/**
 * True when we should apply the mobile CMS shell.
 */
function tnf_admin_is_mobile_view(): bool {
	if (function_exists('wp_is_mobile') && wp_is_mobile()) {
		return true;
	}
	return false;
}

/**
 * @param string $classes Admin body classes.
 */
function tnf_admin_mobile_body_class(string $classes): string {
	$classes .= ' tnf-admin-mobile-ready';
	if (tnf_admin_is_mobile_cms_context()) {
		$classes .= ' tnf-admin-mobile';
	}
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && in_array($screen->base, array('post', 'post-new'), true)) {
		$classes .= ' tnf-mob-is-editor';
	}
	return $classes;
}

/**
 * Enqueue mobile admin CSS on all wp-admin screens for editorial users.
 *
 * @param string $hook_suffix Hook suffix.
 */
function tnf_admin_enqueue_mobile_styles(string $hook_suffix): void {
	unset($hook_suffix);
	if (! current_user_can('edit_posts')) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/tnf-admin-mobile.css';
	if (! is_readable($path)) {
		return;
	}

	wp_enqueue_style(
		'tnf-admin-mobile',
		TNF_NEWS_PLATFORM_URL . 'assets/css/tnf-admin-mobile.css',
		array('tnf-admin-theme', 'tnf-admin-dashboard'),
		(string) filemtime($path)
	);

	$js_path = TNF_NEWS_PLATFORM_PATH . 'assets/js/admin-mobile.js';
	if (is_readable($js_path)) {
		wp_enqueue_script(
			'tnf-admin-mobile',
			TNF_NEWS_PLATFORM_URL . 'assets/js/admin-mobile.js',
			array(),
			(string) filemtime($js_path),
			true
		);
	}
}

/**
 * Hide WordPress update marketing on mobile dashboard (saves vertical space).
 */
function tnf_admin_mobile_head(): void {
	if (! current_user_can('edit_posts')) {
		return;
	}

	add_filter('screen_options_show_screen', 'tnf_admin_hide_screen_options', 10, 2);

	if (tnf_admin_is_mobile_view()) {
		remove_action('admin_notices', 'update_nag', 3);
		remove_action('network_admin_notices', 'update_nag', 3);
	}

	?>
	<script id="tnf-admin-mobile-viewport">
	(function () {
		if (window.matchMedia('(max-width: 782px)').matches && window.location.search.indexOf('tnf_mob=1') === -1) {
			var b = document.body;
			if (
				b &&
				(b.classList.contains('post-type-tnf_news') ||
					b.classList.contains('post-type-tnf_pdf_report') ||
					b.classList.contains('post-type-tnf_video') ||
					b.classList.contains('post-php') ||
					b.classList.contains('post-new-php'))
			) {
				var u = new URL(window.location.href);
				u.searchParams.set('tnf_mob', '1');
				window.location.replace(u.toString());
			}
		}
	})();
	</script>
	<style id="tnf-admin-mobile-critical">
		@media screen and (max-width: 782px) {
			#wpbody-content {
				padding-top: calc(56px + env(safe-area-inset-top, 0px) + 6px) !important;
			}
			.wrap > h1 {
				margin-top: 0 !important;
				padding-top: 2px !important;
				line-height: 1.35 !important;
			}
			#screen-meta-links,
			#screen-meta,
			#wp-responsive-toggle,
			.wrap > .notice.update-nag,
			#wpbody-content > .update-nag {
				display: none !important;
				height: 0 !important;
				max-height: 0 !important;
				margin: 0 !important;
				padding: 0 !important;
				overflow: hidden !important;
				visibility: hidden !important;
				pointer-events: none !important;
			}
		}
	</style>
	<?php
}

/**
 * Hide Screen Options tab on phones (desktop editors keep it).
 *
 * @param bool   $show       Whether to show.
 * @param WP_Screen $screen Screen.
 */
function tnf_admin_hide_screen_options(bool $show, $screen): bool {
	unset($screen);
	if (function_exists('wp_is_mobile') && wp_is_mobile()) {
		return false;
	}
	return $show;
}

/**
 * Fixed top bar + bottom tab bar for mobile editorial navigation.
 */
function tnf_admin_mobile_footer_shell(): void {
	if (! current_user_can('edit_posts')) {
		return;
	}

	$user = wp_get_current_user();
	$name = function_exists('tnf_admin_user_greeting_name')
		? tnf_admin_user_greeting_name($user)
		: ($user->display_name ?: $user->user_login);

	$dash   = admin_url('index.php');
	$news   = admin_url('edit.php?post_type=tnf_news');
	$add    = admin_url('post-new.php?post_type=tnf_news');
	$epaper = admin_url('edit.php?post_type=tnf_pdf_report');
	$site   = home_url('/');

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	$active = 'dash';
	if ($screen) {
		if ($screen->id === 'dashboard') {
			$active = 'dash';
		} elseif ($screen->post_type === 'tnf_news' && $screen->base === 'edit') {
			$active = 'news';
		} elseif ($screen->post_type === 'tnf_news' && $screen->base === 'post') {
			$active = 'add';
		} elseif ($screen->post_type === 'tnf_pdf_report') {
			$active = 'epaper';
		} elseif ($screen->post_type === 'tnf_video') {
			$active = 'videos';
		}
	}
	?>
	<header class="tnf-mob-admin-top" id="tnf-mob-admin-top">
		<button type="button" class="tnf-mob-admin-top__menu" id="tnf-mob-admin-menu-btn" aria-expanded="false" aria-controls="adminmenuwrap">
			<span aria-hidden="true">☰</span>
			<span class="screen-reader-text"><?php esc_html_e('Menu', 'tnf-news-platform'); ?></span>
		</button>
		<div class="tnf-mob-admin-top__brand">
			<span class="tnf-mob-admin-top__kicker"><?php esc_html_e('TNF CMS', 'tnf-news-platform'); ?></span>
			<strong class="tnf-mob-admin-top__name"><?php echo esc_html($name); ?></strong>
		</div>
		<?php
		$is_editor_screen = $screen && in_array($screen->base, array('post', 'post-new'), true);
		if ($is_editor_screen && $screen->post_type) {
			$top_right_url   = admin_url('edit.php?post_type=' . $screen->post_type);
			$top_right_label = __('Back', 'tnf-news-platform');
			$top_right_class = 'tnf-mob-admin-top__action';
		} elseif ($active === 'dash') {
			$top_right_url   = $add;
			$top_right_label = __('Add', 'tnf-news-platform');
			$top_right_class = 'tnf-mob-admin-top__action';
		} elseif ($active === 'news') {
			$top_right_url   = $add;
			$top_right_label = __('Add', 'tnf-news-platform');
			$top_right_class = 'tnf-mob-admin-top__action';
		} elseif ($active === 'epaper') {
			$top_right_url   = admin_url('post-new.php?post_type=tnf_pdf_report');
			$top_right_label = __('Add', 'tnf-news-platform');
			$top_right_class = 'tnf-mob-admin-top__action';
		} else {
			$top_right_url   = $site;
			$top_right_label = __('Site', 'tnf-news-platform');
			$top_right_class = 'tnf-mob-admin-top__site';
		}
		?>
		<a class="<?php echo esc_attr($top_right_class); ?>" href="<?php echo esc_url($top_right_url); ?>"<?php echo $top_right_class === 'tnf-mob-admin-top__site' ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<?php echo esc_html($top_right_label); ?>
		</a>
	</header>
	<div class="tnf-mob-admin-backdrop" id="tnf-mob-admin-backdrop" hidden></div>
	<nav class="tnf-mob-admin-tabs" aria-label="<?php esc_attr_e('Editorial navigation', 'tnf-news-platform'); ?>">
		<a class="tnf-mob-admin-tabs__item<?php echo $active === 'dash' ? ' is-active' : ''; ?>" href="<?php echo esc_url($dash); ?>">
			<span aria-hidden="true">⌂</span>
			<?php esc_html_e('Home', 'tnf-news-platform'); ?>
		</a>
		<a class="tnf-mob-admin-tabs__item<?php echo $active === 'news' ? ' is-active' : ''; ?>" href="<?php echo esc_url($news); ?>">
			<span aria-hidden="true">▤</span>
			<?php esc_html_e('News', 'tnf-news-platform'); ?>
		</a>
		<a class="tnf-mob-admin-tabs__item tnf-mob-admin-tabs__item--accent<?php echo $active === 'add' ? ' is-active' : ''; ?>" href="<?php echo esc_url($add); ?>">
			<span aria-hidden="true">＋</span>
			<?php esc_html_e('Add', 'tnf-news-platform'); ?>
		</a>
		<a class="tnf-mob-admin-tabs__item<?php echo $active === 'epaper' ? ' is-active' : ''; ?>" href="<?php echo esc_url($epaper); ?>">
			<span aria-hidden="true">📄</span>
			<?php esc_html_e('ePaper', 'tnf-news-platform'); ?>
		</a>
		<button type="button" class="tnf-mob-admin-tabs__item" id="tnf-mob-admin-tabs-menu" aria-label="<?php esc_attr_e('More menu', 'tnf-news-platform'); ?>">
			<span aria-hidden="true">⋯</span>
			<?php esc_html_e('More', 'tnf-news-platform'); ?>
		</button>
	</nav>
	<?php
}

/**
 * Inline menu toggle — must run even if admin-mobile.js is cached or loads late.
 */
function tnf_admin_mobile_inline_menu_boot(): void {
	if (! current_user_can('edit_posts')) {
		return;
	}
	?>
	<script id="tnf-mob-admin-boot">
	(function () {
		function setMenu(open) {
			document.body.classList.toggle('tnf-mob-menu-open', open);
			document.documentElement.classList.toggle('tnf-mob-menu-open', open);
			var wpbody = document.getElementById('wpbody');
			if (wpbody) {
				wpbody.classList.toggle('wp-responsive-open', open);
			}
			var wrap = document.getElementById('adminmenuwrap');
			if (wrap) {
				wrap.style.display = 'block';
			}
			var back = document.getElementById('tnf-mob-admin-backdrop');
			if (back) {
				if (open) {
					back.removeAttribute('hidden');
				} else {
					back.setAttribute('hidden', '');
				}
			}
			var btn = document.getElementById('tnf-mob-admin-menu-btn');
			if (btn) {
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
		}
		window.tnfMobAdminToggleMenu = function (force) {
			var open = typeof force === 'boolean' ? force : !document.body.classList.contains('tnf-mob-menu-open');
			setMenu(open);
		};
		function bind(id, handler) {
			var el = document.getElementById(id);
			if (el) {
				el.addEventListener('click', handler, false);
			}
		}
		bind('tnf-mob-admin-menu-btn', function (e) {
			e.preventDefault();
			e.stopImmediatePropagation();
			window.tnfMobAdminToggleMenu();
		});
		bind('tnf-mob-admin-tabs-menu', function (e) {
			e.preventDefault();
			window.tnfMobAdminToggleMenu(true);
		});
		bind('tnf-mob-admin-backdrop', function () {
			window.tnfMobAdminToggleMenu(false);
		});
	})();
	</script>
	<?php
}
