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
		if (! $subscriber->has_cap('upload_files')) {
			$subscriber->add_cap('upload_files');
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

	// Always (re)sync TNF Subscriber caps. Early-return here used to skip updates once the role
	// existed, so missing `read` or submission caps could strand real users after plugin changes.
	$tnf_required = array_merge(
		array('read', 'tnf_submit_news', 'tnf_read_premium', 'upload_files'),
		$submission_caps
	);

	$tnf_role = get_role('tnf_subscriber');
	if (! $tnf_role) {
		add_role(
			'tnf_subscriber',
			__('TNF Subscriber', 'tnf-news-platform'),
			array_fill_keys($tnf_required, true)
		);
	} else {
		foreach ($tnf_required as $cap) {
			if (! $tnf_role->has_cap($cap)) {
				$tnf_role->add_cap($cap);
			}
		}
	}
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
