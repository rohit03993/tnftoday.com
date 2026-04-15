<?php
/**
 * Plugin Name: TNF News Platform
 * Description: Headless news CMS — CPTs, workflows, REST API, PDF dispatch, push notifications.
 * Version: 1.0.0
 * Author: TNF
 * Text Domain: tnf-news-platform
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

define('TNF_NEWS_PLATFORM_VERSION', '1.0.0');
define('TNF_NEWS_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('TNF_NEWS_PLATFORM_URL', plugin_dir_url(__FILE__));

require_once TNF_NEWS_PLATFORM_PATH . 'includes/block-patterns.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/post-types.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/roles.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/workflows.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/rest-api.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/services/class-pdf-service-client.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/services/class-push-notifications.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/pdf-integration.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/footer-settings.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/admin-ui.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/frontend-display.php';
require_once TNF_NEWS_PLATFORM_PATH . 'includes/frontend-auth.php';

/**
 * Bootstrap: CPTs and rewrites must run on `init`, not `plugins_loaded`.
 * REST routes must register on `rest_api_init` so core REST is ready.
 */
add_action('plugins_loaded', 'tnf_news_platform_bootstrap');

/**
 * Wire hooks at correct lifecycle points.
 */
function tnf_news_platform_bootstrap(): void {
	add_action('init', 'tnf_register_post_types', 0);
	add_action('init', 'tnf_handle_frontend_auth_forms', 25);
	add_action('init', 'tnf_register_roles', 5);
	add_action('init', 'tnf_register_workflow_hooks', 8);
	add_action('init', 'tnf_register_pdf_integration', 8);
	add_action('init', 'tnf_register_admin_ui', 9);
	add_action('init', 'tnf_register_frontend_auth', 9);
	add_action('rest_api_init', 'tnf_register_rest_routes');
	add_action('transition_post_status', 'tnf_on_transition_publish_notification', 10, 3);
}

register_activation_hook(__FILE__, 'tnf_news_platform_activate');

/**
 * Flush rewrite on activate.
 */
function tnf_news_platform_activate(): void {
	tnf_register_post_types();
	tnf_register_roles();
	update_option('tnf_rewrite_rules_version', 'epaper-videos-1');
	flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'tnf_news_platform_deactivate');

/**
 * Flush rewrite on deactivate.
 */
function tnf_news_platform_deactivate(): void {
	flush_rewrite_rules();
}
