<?php
/**
 * PDF publish → enqueue FastAPI processing.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register PDF hooks.
 */
function tnf_register_pdf_integration(): void {
	add_action('save_post_tnf_pdf_report', 'tnf_pdf_report_maybe_enqueue', 20, 3);
}

/**
 * When PDF attachment or post is updated, trigger processing.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  Whether update.
 */
function tnf_pdf_report_maybe_enqueue(int $post_id, WP_Post $post, bool $update): void {
	if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
		return;
	}

	$aid = (int) get_post_meta($post_id, 'tnf_pdf_attachment_id', true);
	if (! $aid) {
		return;
	}

	$url = wp_get_attachment_url($aid);
	if (! $url) {
		return;
	}

	$file  = get_attached_file($aid);
	$mtime = ($file && is_readable($file)) ? (string) filemtime($file) : (string) time();
	$sig   = md5($url . $mtime);
	$last_sig  = get_post_meta($post_id, '_tnf_pdf_last_sig', true);
	if ($last_sig === $sig) {
		// Keep card thumbnail recoverable even when PDF bytes did not change.
		if (! has_post_thumbnail($post_id)) {
			$pages_existing = json_decode((string) get_post_meta($post_id, 'tnf_pdf_pages_json', true), true);
			if (is_array($pages_existing) && function_exists('tnf_pdf_report_maybe_set_featured_image_from_pages')) {
				tnf_pdf_report_maybe_set_featured_image_from_pages($post_id, $pages_existing);
			}
		}
		// If we still don't have a thumbnail, force a fresh processing job so we get
		// new page URLs and can set the featured image from page 1.
		if (has_post_thumbnail($post_id)) {
			return;
		}
	}

	update_post_meta($post_id, 'tnf_pdf_status', 'queued');
	update_post_meta($post_id, '_tnf_pdf_last_sig', $sig);
	delete_post_meta($post_id, '_tnf_social_og_sig');
	if (function_exists('tnf_pdf_report_social_og_upload_paths')) {
		$og_paths = tnf_pdf_report_social_og_upload_paths($post_id);
		if (is_array($og_paths) && isset($og_paths['file']) && is_string($og_paths['file']) && is_readable($og_paths['file'])) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink($og_paths['file']);
		}
	}

	$internal = tnf_pdf_internal_file_url($url);
	$ext_id   = 'post-' . $post_id;

	$result = tnf_pdf_enqueue_process($internal, $ext_id);
	if (is_string($result)) {
		update_post_meta($post_id, 'tnf_pdf_status', 'failed');
		update_post_meta($post_id, 'tnf_pdf_error', $result);
		return;
	}

	if (isset($result['job_id'])) {
		update_post_meta($post_id, 'tnf_pdf_job_id', $result['job_id']);
		update_post_meta($post_id, 'tnf_pdf_status', $result['status'] ?? 'processing');
	}
}
