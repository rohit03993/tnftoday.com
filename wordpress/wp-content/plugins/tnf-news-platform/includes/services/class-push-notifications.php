<?php
/**
 * OneSignal REST integration.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Send OneSignal notification if configured.
 *
 * @param string $title   Title.
 * @param string $message Body.
 * @param string $url     Deep link URL.
 */
function tnf_onesignal_send(string $title, string $message, string $url): void {
	$app_id = defined('TNF_ONESIGNAL_APP_ID') ? TNF_ONESIGNAL_APP_ID : '';
	$key    = defined('TNF_ONESIGNAL_REST_KEY') ? TNF_ONESIGNAL_REST_KEY : '';
	if ($app_id === '' || $key === '') {
		return;
	}

	$payload = array(
		'app_id'            => $app_id,
		'included_segments' => array('Subscribed Users'),
		'headings'          => array('en' => $title),
		'contents'          => array('en' => $message),
		'url'               => $url,
	);

	$response = wp_remote_post(
		'https://onesignal.com/api/v1/notifications',
		array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Key ' . $key,
			),
			'body'    => wp_json_encode($payload),
		)
	);

	if (is_wp_error($response)) {
		error_log('TNF OneSignal: ' . $response->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * On publish transition: notify for news, PDF, video.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 */
function tnf_on_transition_publish_notification(string $new_status, string $old_status, WP_Post $post): void {
	if ('publish' !== $new_status || 'publish' === $old_status) {
		return;
	}

	$allowed = array('tnf_news', 'tnf_pdf_report', 'tnf_video');
	if (! in_array($post->post_type, $allowed, true)) {
		return;
	}

	$frontend = defined('TNF_FRONTEND_URL') ? rtrim(TNF_FRONTEND_URL, '/') : home_url();
	$path     = tnf_frontend_path_for_post($post);
	$url      = $frontend . $path;

	$title   = get_the_title($post);
	$message = wp_strip_all_tags(get_the_excerpt($post));
	if ($message === '') {
		$message = __('New content is available.', 'tnf-news-platform');
	}

	tnf_onesignal_send($title, $message, $url);
}

/**
 * Frontend path for deep link.
 *
 * @param WP_Post $post Post.
 */
function tnf_frontend_path_for_post(WP_Post $post): string {
	switch ($post->post_type) {
		case 'tnf_news':
			return '/news/' . $post->ID;
		case 'tnf_pdf_report':
			return '/pdfs/' . $post->ID;
		case 'tnf_video':
			return '/videos/' . $post->ID;
		default:
			return '/';
	}
}
