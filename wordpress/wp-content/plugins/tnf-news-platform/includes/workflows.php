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
	add_action('save_post_tnf_user_submission', 'tnf_submission_notify_admin_on_new', 5, 3);
}

/**
 * Editors/admins who can approve or reject user submissions (matches REST + admin UI).
 */
function tnf_user_can_moderate_submissions(): bool {
	return current_user_can('edit_others_tnf_submissions') || current_user_can('publish_tnf_submissions');
}

/**
 * Email site admin(s) when a new submission is created (first save only).
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function tnf_submission_notify_admin_on_new(int $post_id, WP_Post $post, bool $update): void {
	if ($update || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		return;
	}

	$to = apply_filters(
		'tnf_submission_admin_notification_email',
		get_option('admin_email'),
		$post_id,
		$post
	);
	if (! is_string($to) || ! is_email($to)) {
		return;
	}

	$author_id = (int) $post->post_author;
	if (
		$author_id > 0
		&& ( user_can($author_id, 'edit_others_tnf_submissions') || user_can($author_id, 'publish_tnf_submissions') )
	) {
		return;
	}

	$edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
	$author    = get_userdata((int) $post->post_author);
	$who       = $author ? $author->user_login : (string) $post->post_author;
	$subject   = sprintf(
		/* translators: %s: site name */
		__('[%s] New user news submission', 'tnf-news-platform'),
		wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES)
	);
	$body = sprintf(
		/* translators: 1: submission title, 2: author login, 3: edit URL */
		__(
			"A contributor submitted news for review.\n\nTitle: %1\$s\nAuthor: %2\$s\n\nReview in the dashboard:\n%3\$s\n",
			'tnf-news-platform'
		),
		(string) $post->post_title,
		$who,
		$edit_link
	);

	wp_mail($to, $subject, $body);
}

/**
 * Approve a user submission: create published news, link and archive submission.
 *
 * @param int $submission_id Submission post ID.
 * @return int|WP_Error New tnf_news post ID or error.
 */
function tnf_user_submission_approve(int $submission_id) {
	$sub = get_post($submission_id);
	if (! $sub instanceof WP_Post || $sub->post_type !== 'tnf_user_submission') {
		return new WP_Error('not_found', __('Submission not found.', 'tnf-news-platform'), array('status' => 404));
	}

	$existing_news = (int) get_post_meta($submission_id, 'tnf_promoted_news_id', true);
	if ($existing_news > 0 && get_post($existing_news) instanceof WP_Post) {
		return new WP_Error('already_approved', __('This submission was already published.', 'tnf-news-platform'), array('status' => 400));
	}

	$news_id = wp_insert_post(
		array(
			'post_type'    => 'tnf_news',
			'post_title'   => $sub->post_title,
			'post_content' => $sub->post_content,
			'post_status'  => 'publish',
			'post_author'  => (int) $sub->post_author,
		),
		true
	);

	if (is_wp_error($news_id)) {
		return $news_id;
	}
	if (! is_int($news_id) || $news_id <= 0) {
		return new WP_Error('create_failed', __('Could not create the news post.', 'tnf-news-platform'), array('status' => 500));
	}

	$thumb_id = (int) get_post_thumbnail_id($submission_id);
	if ($thumb_id > 0 && get_post($thumb_id) instanceof WP_Post) {
		set_post_thumbnail($news_id, $thumb_id);
	}

	$embed = (string) get_post_meta($submission_id, 'tnf_embed_url', true);
	if ($embed !== '') {
		update_post_meta($news_id, 'tnf_embed_url', esc_url_raw($embed));
	}

	update_post_meta($submission_id, 'tnf_submission_status', 'approved');
	update_post_meta($submission_id, 'tnf_promoted_news_id', $news_id);
	wp_update_post(
		array(
			'ID'          => $submission_id,
			'post_status' => 'private',
		)
	);

	return $news_id;
}

/**
 * Reject a user submission.
 *
 * @param int    $submission_id Submission post ID.
 * @param string $reason        Optional note for the contributor (stored as meta).
 * @return true|WP_Error
 */
function tnf_user_submission_reject(int $submission_id, string $reason = '') {
	$sub = get_post($submission_id);
	if (! $sub instanceof WP_Post || $sub->post_type !== 'tnf_user_submission') {
		return new WP_Error('not_found', __('Submission not found.', 'tnf-news-platform'), array('status' => 404));
	}

	$published_id = (int) get_post_meta($submission_id, 'tnf_promoted_news_id', true);
	if ($published_id > 0 && get_post($published_id) instanceof WP_Post) {
		return new WP_Error('cannot_reject', __('This submission was already published as news.', 'tnf-news-platform'), array('status' => 400));
	}

	$reason = sanitize_text_field($reason);
	update_post_meta($submission_id, 'tnf_rejection_reason', $reason);
	update_post_meta($submission_id, 'tnf_submission_status', 'rejected');
	wp_update_post(
		array(
			'ID'          => $submission_id,
			'post_status' => 'draft',
		)
	);

	return true;
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
