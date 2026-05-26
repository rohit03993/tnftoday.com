<?php
/**
 * Security hardening: OG rate limits, response headers, registration gate.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register security hooks.
 */
function tnf_register_security(): void {
	add_action('send_headers', 'tnf_security_send_headers', 1);
	add_filter('the_generator', '__return_empty_string');
	add_filter('xmlrpc_enabled', '__return_false');
	add_action('init', 'tnf_security_disable_frontend_heartbeat', 1);
}

/**
 * Reduce admin-ajax noise on public pages.
 */
function tnf_security_disable_frontend_heartbeat(): void {
	if (is_admin() || ( function_exists('wp_doing_ajax') && wp_doing_ajax() )) {
		return;
	}
	wp_deregister_script('heartbeat');
}

/**
 * Whether front-end registration is allowed (override in wp-config.php).
 */
function tnf_allow_public_registration(): bool {
	if (defined('TNF_ALLOW_PUBLIC_REGISTRATION')) {
		return (bool) TNF_ALLOW_PUBLIC_REGISTRATION;
	}

	return (bool) apply_filters('tnf_allow_public_registration', true);
}

/**
 * Client IP for rate limiting (best effort behind proxies).
 */
function tnf_security_client_ip(): string {
	$candidates = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
	foreach ($candidates as $key) {
		if (empty($_SERVER[ $key ])) {
			continue;
		}
		$raw = sanitize_text_field((string) wp_unslash($_SERVER[ $key ]));
		if ($key === 'HTTP_X_FORWARDED_FOR') {
			$parts = explode(',', $raw);
			$raw   = trim((string) ( $parts[0] ?? '' ));
		}
		if (filter_var($raw, FILTER_VALIDATE_IP)) {
			return $raw;
		}
	}

	return '0.0.0.0';
}

/**
 * Rate-limit expensive OG image REST endpoints (abuse / DoS mitigation).
 *
 * @param string $bucket Endpoint bucket name.
 * @param int    $limit  Max requests per window.
 * @param int    $window Window length in seconds.
 */
function tnf_security_og_rate_limit(string $bucket, int $limit = 40, int $window = 60): bool {
	$ip  = tnf_security_client_ip();
	$key = 'tnf_og_rl_' . md5($bucket . '|' . $ip);
	$hit = (int) get_transient($key);

	if ($hit >= $limit) {
		return false;
	}

	set_transient($key, $hit + 1, $window);

	return true;
}

/**
 * @return WP_Error|null Null when allowed; WP_Error when rate limited.
 */
function tnf_security_og_rate_limit_error(string $bucket): ?WP_Error {
	if (tnf_security_og_rate_limit($bucket)) {
		return null;
	}

	return new WP_Error(
		'rate_limited',
		__('Too many requests. Please try again later.', 'tnf-news-platform'),
		array('status' => 429)
	);
}

/**
 * Security headers on public pages (skip when explicitly disabled).
 */
function tnf_security_send_headers(): void {
	if (is_admin() || ( defined('TNF_DISABLE_SECURITY_HEADERS') && TNF_DISABLE_SECURITY_HEADERS )) {
		return;
	}

	if (headers_sent()) {
		return;
	}

	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: SAMEORIGIN');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

	if (is_ssl()) {
		header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
	}
}
