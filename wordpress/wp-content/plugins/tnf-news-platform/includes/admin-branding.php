<?php
/**
 * Branded wp-admin (TNF CMS) — UI/UX only; all menus and features unchanged.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register admin branding hooks.
 */
function tnf_register_admin_branding(): void {
	add_action('admin_enqueue_scripts', 'tnf_admin_enqueue_branding', 999);
	add_action('login_enqueue_scripts', 'tnf_admin_enqueue_login_branding');
	add_filter('login_headerurl', 'tnf_admin_login_header_url');
	add_filter('login_headertext', 'tnf_admin_login_header_text');
	add_action('admin_bar_menu', 'tnf_admin_bar_brand', 1);
	add_action('admin_bar_menu', 'tnf_admin_bar_cleanup', 999);
	add_action('wp_dashboard_setup', 'tnf_admin_customize_dashboard', 1);
	add_action('admin_notices', 'tnf_admin_dashboard_hero', 0);
	add_filter('admin_footer_text', 'tnf_admin_footer_text');
	add_filter('update_footer', 'tnf_admin_update_footer', 50);
	add_action('admin_head', 'tnf_admin_head_vars', 1);
	add_filter('admin_body_class', 'tnf_admin_body_class');
	add_filter('get_user_option_screen_layout_dashboard', 'tnf_admin_force_dashboard_one_column');
	add_action('admin_init', 'tnf_admin_register_color_scheme');
	add_action('admin_init', 'tnf_admin_maybe_apply_color_scheme', 5);
	add_action('user_register', 'tnf_admin_set_default_color_scheme');
	add_action('wp_login', 'tnf_admin_set_default_color_scheme_on_login', 10, 2);
}

/**
 * Use TNF admin colors for editorial users (overrides default "fresh" / light).
 */
function tnf_admin_maybe_apply_color_scheme(): void {
	if (! is_user_logged_in() || ! is_admin()) {
		return;
	}
	if (! current_user_can('edit_posts')) {
		return;
	}

	$user_id = get_current_user_id();
	$current = (string) get_user_meta($user_id, 'admin_color', true);
	$legacy  = array('', 'fresh', 'light', 'blue', 'coffee', 'ectoplasm', 'midnight', 'ocean', 'sunrise');
	if (in_array($current, $legacy, true)) {
		update_user_meta($user_id, 'admin_color', 'tnf');
	}
}

/**
 * @param string $classes Space-separated admin body classes.
 */
function tnf_admin_body_class(string $classes): string {
	return $classes . ' tnf-admin-branded';
}

/**
 * Single full-width dashboard column (no empty right "Drag boxes here").
 *
 * @param mixed $result User option value.
 * @return int
 */
function tnf_admin_force_dashboard_one_column($result) {
	return 1;
}

/**
 * @return string
 */
function tnf_admin_login_header_url(): string {
	return home_url('/');
}

/**
 * @return string
 */
function tnf_admin_login_header_text(): string {
	return __('TNF Today CMS', 'tnf-news-platform');
}

/**
 * Register TNF admin color scheme (WordPress native picker).
 */
function tnf_admin_register_color_scheme(): void {
	wp_admin_css_color(
		'tnf',
		__('TNF Today', 'tnf-news-platform'),
		TNF_NEWS_PLATFORM_URL . 'assets/css/tnf-admin-color-scheme.css',
		array('#12141c', '#bc1e38', '#d42036', '#8f1428'),
		array(
			'base'    => '#e4e8f0',
			'focus'   => '#fff',
			'current' => '#fff',
		)
	);
}

/**
 * Default new users to TNF scheme.
 *
 * @param int $user_id New user ID.
 */
function tnf_admin_set_default_color_scheme(int $user_id): void {
	if ($user_id > 0) {
		update_user_meta($user_id, 'admin_color', 'tnf');
	}
}

/**
 * @param string  $user_login Username.
 * @param WP_User $user       User.
 */
function tnf_admin_set_default_color_scheme_on_login(string $user_login, WP_User $user): void {
	if (! $user instanceof WP_User) {
		return;
	}
	if (! user_can($user, 'edit_posts')) {
		return;
	}
	$current = get_user_meta($user->ID, 'admin_color', true);
	if ($current === '' || $current === 'fresh') {
		update_user_meta($user->ID, 'admin_color', 'tnf');
	}
}

/**
 * CSS variables on all wp-admin screens.
 */
function tnf_admin_head_vars(): void {
	?>
	<style id="tnf-admin-vars">
		:root {
			--tnf-admin-red: #bc1e38;
			--tnf-admin-red-dark: #8f1428;
			--tnf-admin-sidebar: #12141c;
			--tnf-admin-sidebar-hover: #1c2230;
			--tnf-admin-surface: #ffffff;
			--tnf-admin-bg: #eef1f6;
		}
	</style>
	<?php
}

/**
 * Enqueue branded admin styles (late priority beats core admin CSS).
 *
 * @param string $hook_suffix Current admin page hook.
 */
function tnf_admin_enqueue_branding(string $hook_suffix): void {
	$deps = array();
	if (wp_style_is('common', 'registered')) {
		$deps[] = 'common';
	}
	if (wp_style_is('wp-admin', 'registered')) {
		$deps[] = 'wp-admin';
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/tnf-admin-theme.css';
	if (is_readable($path)) {
		wp_enqueue_style(
			'tnf-admin-theme',
			TNF_NEWS_PLATFORM_URL . 'assets/css/tnf-admin-theme.css',
			$deps,
			(string) filemtime($path)
		);
	}

	$dash_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/tnf-admin-dashboard.css';
	if (is_readable($dash_path)) {
		wp_enqueue_style(
			'tnf-admin-dashboard',
			TNF_NEWS_PLATFORM_URL . 'assets/css/tnf-admin-dashboard.css',
			array('tnf-admin-theme'),
			(string) filemtime($dash_path)
		);
	}

	$logo_url = tnf_admin_brand_logo_url();
	if ($logo_url !== '') {
		wp_add_inline_style(
			'tnf-admin-theme',
			'body.login h1 a { background-image: url("' . esc_url($logo_url) . '") !important; background-size: contain !important; }'
		);
	}
}

/**
 * Login screen branding.
 */
function tnf_admin_enqueue_login_branding(): void {
	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/tnf-admin-theme.css';
	if (is_readable($path)) {
		wp_enqueue_style(
			'tnf-admin-theme-login',
			TNF_NEWS_PLATFORM_URL . 'assets/css/tnf-admin-theme.css',
			array('login'),
			(string) filemtime($path)
		);
	}

	$login_path = TNF_NEWS_PLATFORM_PATH . 'assets/css/wp-login.css';
	if (is_readable($login_path)) {
		wp_enqueue_style(
			'tnf-wp-login',
			TNF_NEWS_PLATFORM_URL . 'assets/css/wp-login.css',
			array('login'),
			(string) filemtime($login_path)
		);
	}
}

/**
 * Site logo URL when custom logo exists.
 */
function tnf_admin_brand_logo_url(): string {
	if (! function_exists('get_theme_mod')) {
		return '';
	}
	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id <= 0) {
		return '';
	}
	$url = wp_get_attachment_image_url($logo_id, 'medium');
	return is_string($url) ? $url : '';
}

/**
 * Admin bar: TNF brand node.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function tnf_admin_bar_brand(WP_Admin_Bar $bar): void {
	$bar->remove_node('wp-logo');
	if (! $bar->get_node('tnf-cms-brand')) {
		$bar->add_node(
			array(
				'id'    => 'tnf-cms-brand',
				'title' => '<span class="tnf-admin-bar-brand">TNF Today</span>',
				'href'  => admin_url(),
				'meta'  => array(
					'class' => 'tnf-admin-bar-brand-wrap',
				),
			)
		);
	}
}

/**
 * Remove WordPress.org links from admin bar.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 */
function tnf_admin_bar_cleanup(WP_Admin_Bar $bar): void {
	$bar->remove_node('wp-logo');
	$bar->remove_node('about');
}

/**
 * Dashboard widgets: TNF hub first; hide WordPress marketing clutter.
 */
function tnf_admin_customize_dashboard(): void {
	remove_action('welcome_panel', 'wp_welcome_panel');
	remove_meta_box('dashboard_primary', 'dashboard', 'side');
	remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
	remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
	remove_meta_box('dashboard_activity', 'dashboard', 'normal');
	remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
	remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');

	wp_add_dashboard_widget(
		'tnf_newsroom_dashboard',
		__('Newsroom dashboard', 'tnf-news-platform'),
		'tnf_admin_render_unified_dashboard_widget',
		null,
		null,
		'normal',
		'high'
	);
}

/**
 * Full-width dashboard: KPIs + team + latest + quick actions in one view.
 */
function tnf_admin_render_unified_dashboard_widget(): void {
	$org = tnf_admin_user_sees_org_stats();
	?>
	<div class="tnf-dash-unified">
		<section class="tnf-dash-unified__section" aria-labelledby="tnf-dash-kpi-heading">
			<h3 id="tnf-dash-kpi-heading" class="tnf-dash-section-title"><?php esc_html_e('Publication overview', 'tnf-news-platform'); ?></h3>
			<?php tnf_admin_render_stats_overview_widget(); ?>
		</section>

		<div class="tnf-dash-unified__grid">
			<?php if ($org) : ?>
				<section class="tnf-dash-unified__panel" aria-labelledby="tnf-dash-team-heading">
					<h3 id="tnf-dash-team-heading" class="tnf-dash-section-title"><?php esc_html_e('Team performance', 'tnf-news-platform'); ?></h3>
					<?php tnf_admin_render_staff_performance_widget(); ?>
				</section>
				<section class="tnf-dash-unified__panel" aria-labelledby="tnf-dash-recent-heading">
					<h3 id="tnf-dash-recent-heading" class="tnf-dash-section-title"><?php esc_html_e('Latest published news', 'tnf-news-platform'); ?></h3>
					<?php tnf_admin_render_recent_newsroom_widget(); ?>
				</section>
			<?php else : ?>
				<section class="tnf-dash-unified__panel" aria-labelledby="tnf-dash-mine-heading">
					<h3 id="tnf-dash-mine-heading" class="tnf-dash-section-title"><?php esc_html_e('Your contribution', 'tnf-news-platform'); ?></h3>
					<?php tnf_admin_render_my_performance_widget(false); ?>
				</section>
				<section class="tnf-dash-unified__panel" aria-labelledby="tnf-dash-recent-heading">
					<h3 id="tnf-dash-recent-heading" class="tnf-dash-section-title"><?php esc_html_e('Your latest published', 'tnf-news-platform'); ?></h3>
					<?php tnf_admin_render_recent_newsroom_widget(); ?>
				</section>
			<?php endif; ?>

			<section class="tnf-dash-unified__panel tnf-dash-unified__panel--actions" aria-labelledby="tnf-dash-hub-heading">
				<h3 id="tnf-dash-hub-heading" class="tnf-dash-section-title"><?php esc_html_e('Quick actions', 'tnf-news-platform'); ?></h3>
				<?php tnf_admin_render_editorial_hub_widget(); ?>
			</section>
		</div>
	</div>
	<?php
}

/**
 * Hero banner on dashboard.
 */
function tnf_admin_dashboard_hero(): void {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (! $screen || $screen->id !== 'dashboard') {
		return;
	}

	$user = wp_get_current_user();
	$name = tnf_admin_user_greeting_name($user);
	$role = tnf_admin_user_role_label($user);
	$pending = tnf_admin_pending_submission_count_global();
	$org = tnf_admin_user_sees_org_stats();
	?>
	<div class="tnf-admin-hero" role="region" aria-label="<?php esc_attr_e('TNF CMS overview', 'tnf-news-platform'); ?>">
		<div class="tnf-admin-hero__text">
			<p class="tnf-admin-hero__kicker"><?php esc_html_e('TNF Today CMS', 'tnf-news-platform'); ?></p>
			<h2 class="tnf-admin-hero__title">
				<?php
				printf(
					/* translators: %s: display name */
					esc_html__('Hello, %s', 'tnf-news-platform'),
					esc_html($name)
				);
				?>
			</h2>
			<p class="tnf-admin-hero__role"><?php echo esc_html($role); ?></p>
			<p class="tnf-admin-hero__lead">
				<?php
				if ($org) {
					esc_html_e('Newsroom overview: categories, ePaper, published news, and team output in one place.', 'tnf-news-platform');
				} else {
					esc_html_e('Your workspace: track your published stories, images, and drafts.', 'tnf-news-platform');
				}
				?>
			</p>
			<?php if ($pending > 0) : ?>
				<p class="tnf-admin-hero__alert">
					<a href="<?php echo esc_url(admin_url('edit.php?post_type=tnf_user_submission&post_status=pending')); ?>">
						<?php
						printf(
							/* translators: %d: pending count */
							esc_html(_n('%d submission awaiting review', '%d submissions awaiting review', $pending, 'tnf-news-platform')),
							(int) $pending
						);
						?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<div class="tnf-admin-hero__actions">
			<a class="tnf-admin-hero__btn is-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=tnf_news')); ?>">
				<?php esc_html_e('Add news', 'tnf-news-platform'); ?>
			</a>
			<a class="tnf-admin-hero__btn" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e('View site', 'tnf-news-platform'); ?>
			</a>
		</div>
	</div>
	<?php
}

/**
 * Quick-action cards inside dashboard widget.
 */
function tnf_admin_render_editorial_hub_widget(): void {
	$cards = array(
		array(
			'label' => __('News', 'tnf-news-platform'),
			'desc'  => __('Stories & categories', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_news'),
			'new'   => admin_url('post-new.php?post_type=tnf_news'),
			'icon'  => 'N',
		),
		array(
			'label' => __('PDF / ePaper', 'tnf-news-platform'),
			'desc'  => __('Editions & access', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_pdf_report'),
			'new'   => admin_url('post-new.php?post_type=tnf_pdf_report'),
			'icon'  => 'P',
		),
		array(
			'label' => __('Videos', 'tnf-news-platform'),
			'desc'  => __('Clips & embeds', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_video'),
			'new'   => admin_url('post-new.php?post_type=tnf_video'),
			'icon'  => 'V',
		),
		array(
			'label' => __('Submissions', 'tnf-news-platform'),
			'desc'  => __('Contributor queue', 'tnf-news-platform'),
			'url'   => admin_url('edit.php?post_type=tnf_user_submission'),
			'new'   => '',
			'icon'  => 'S',
			'badge' => tnf_admin_pending_submission_count_global(),
		),
	);

	echo '<div class="tnf-admin-hub-grid">';
	foreach ($cards as $card) {
		echo '<article class="tnf-admin-hub-card">';
		echo '<a class="tnf-admin-hub-card__main" href="' . esc_url($card['url']) . '">';
		echo '<span class="tnf-admin-hub-card__icon" aria-hidden="true">' . esc_html($card['icon']) . '</span>';
		echo '<span class="tnf-admin-hub-card__label">' . esc_html($card['label']) . '</span>';
		echo '<span class="tnf-admin-hub-card__desc">' . esc_html($card['desc']) . '</span>';
		if (! empty($card['badge'])) {
			echo '<span class="tnf-admin-hub-card__badge">' . esc_html((string) (int) $card['badge']) . '</span>';
		}
		echo '</a>';
		if ($card['new'] !== '') {
			echo '<a class="tnf-admin-hub-card__new" href="' . esc_url($card['new']) . '">' . esc_html__('+ New', 'tnf-news-platform') . '</a>';
		}
		echo '</article>';
	}
	echo '</div>';
}

/**
 * @param string $text Footer text.
 */
function tnf_admin_footer_text(string $text): string {
	return sprintf(
		/* translators: %s: site name */
		__('TNF Today CMS — %s', 'tnf-news-platform'),
		esc_html(get_bloginfo('name'))
	);
}

/**
 * @param string $content Version footer.
 */
function tnf_admin_update_footer(string $content): string {
	return __('Editorial workspace', 'tnf-news-platform');
}
