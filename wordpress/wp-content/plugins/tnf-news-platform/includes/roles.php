<?php
/**
 * Custom roles and caps.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register subscriber role and capabilities.
 */
function tnf_register_roles(): void {
	$submission_caps = array(
		'create_tnf_submissions',
		'edit_tnf_submissions',
		'edit_tnf_submission',
		'read_tnf_submission',
		'delete_tnf_submission',
	);

	$subscriber = get_role('subscriber');
	if ($subscriber) {
		foreach ($submission_caps as $c) {
			if (! $subscriber->has_cap($c)) {
				$subscriber->add_cap($c);
			}
		}
		if (! $subscriber->has_cap('tnf_submit_news')) {
			$subscriber->add_cap('tnf_submit_news');
		}
	}

	$editor = get_role('editor');
	if ($editor) {
		$editor->add_cap('edit_others_tnf_submissions');
		$editor->add_cap('publish_tnf_submissions');
		$editor->add_cap('delete_tnf_submissions');
		$editor->add_cap('read_private_tnf_submissions');
	}

	$admin = get_role('administrator');
	if ($admin) {
		foreach (array_merge($submission_caps, array('edit_others_tnf_submissions', 'publish_tnf_submissions', 'delete_tnf_submissions', 'read_private_tnf_submissions', 'tnf_submit_news', 'tnf_read_premium')) as $c) {
			$admin->add_cap($c);
		}
	}

	if (get_role('tnf_subscriber')) {
		return;
	}

	$caps = array_merge(
		array(
			'read'               => true,
			'tnf_submit_news'    => true,
			'tnf_read_premium'   => true,
		),
		array_fill_keys($submission_caps, true)
	);

	add_role(
		'tnf_subscriber',
		__('TNF Subscriber', 'tnf-news-platform'),
		$caps
	);
}

/**
 * Whether user has active subscription (meta or capability).
 *
 * @param int $user_id User ID.
 */
function tnf_user_has_subscription(int $user_id): bool {
	if (user_can($user_id, 'manage_options')) {
		return true;
	}
	if (user_can($user_id, 'edit_others_posts')) {
		return true;
	}
	if (user_can($user_id, 'tnf_read_premium')) {
		return true;
	}
	return (bool) get_user_meta($user_id, 'tnf_subscription_active', true);
}
