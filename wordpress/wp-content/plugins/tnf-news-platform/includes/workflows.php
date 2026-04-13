<?php
/**
 * Submission workflow: pending → approve / reject.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register workflow hooks.
 */
function tnf_register_workflow_hooks(): void {
	add_action('save_post_tnf_user_submission', 'tnf_submission_default_pending', 10, 3);
}

/**
 * Force new submissions to pending review.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  Update flag.
 */
function tnf_submission_default_pending(int $post_id, WP_Post $post, bool $update): void {
	if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		return;
	}

	if ($update) {
		return;
	}

	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'pending',
		)
	);
	update_post_meta($post_id, 'tnf_submission_status', 'pending');
}
