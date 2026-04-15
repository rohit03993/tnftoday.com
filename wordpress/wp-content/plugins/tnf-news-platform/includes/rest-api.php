<?php
/**
 * REST API routes under tnf/v1.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register REST routes.
 */
function tnf_register_rest_routes(): void {
	$ns = 'tnf/v1';

	register_rest_route(
		$ns,
		'/news',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_news_list',
			'permission_callback' => '__return_true',
			'args'                => array(
				'page'     => array('default' => 1, 'sanitize_callback' => 'absint'),
				'per_page' => array('default' => 10, 'sanitize_callback' => 'absint'),
				'search'   => array('sanitize_callback' => 'sanitize_text_field'),
			),
		)
	);

	register_rest_route(
		$ns,
		'/news/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_news_single',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/pdfs',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_pdfs_list',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/pdfs/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_pdf_single',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/pdfs/(?P<id>\d+)/access',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_pdf_access',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		$ns,
		'/videos',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_videos_list',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/submissions',
		array(
			'methods'             => 'POST',
			'callback'            => 'tnf_rest_submissions_create',
			'permission_callback' => function () {
				return is_user_logged_in() && current_user_can('create_tnf_submissions');
			},
		)
	);

	register_rest_route(
		$ns,
		'/submissions/mine',
		array(
			'methods'             => 'GET',
			'callback'            => 'tnf_rest_submissions_mine',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);

	register_rest_route(
		$ns,
		'/submissions/(?P<id>\d+)/approve',
		array(
			'methods'             => 'POST',
			'callback'            => 'tnf_rest_submissions_approve',
			'permission_callback' => function () {
				return tnf_user_can_moderate_submissions();
			},
		)
	);

	register_rest_route(
		$ns,
		'/internal/pdf-job-complete',
		array(
			'methods'             => 'POST',
			'callback'            => 'tnf_rest_internal_pdf_job_complete',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/submissions/(?P<id>\d+)/reject',
		array(
			'methods'             => 'POST',
			'callback'            => 'tnf_rest_submissions_reject',
			'permission_callback' => function () {
				return tnf_user_can_moderate_submissions();
			},
		)
	);
}

/**
 * Serialize post for API.
 *
 * @param WP_Post $post Post.
 */
function tnf_rest_serialize_post(WP_Post $post): array {
	return array(
		'id'          => $post->ID,
		'title'       => get_the_title($post),
		'excerpt'     => wp_strip_all_tags(get_the_excerpt($post)),
		'content'     => apply_filters('the_content', $post->post_content),
		'date'        => $post->post_date_gmt,
		'slug'        => $post->post_name,
		'author_id'   => (int) $post->post_author,
		'featured'    => get_the_post_thumbnail_url($post->ID, 'large'),
	);
}

/**
 * News list.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_news_list(WP_REST_Request $req): WP_REST_Response {
	$q = new WP_Query(
		array(
			'post_type'      => 'tnf_news',
			'post_status'    => 'publish',
			'paged'          => $req->get_param('page'),
			'posts_per_page' => min(50, (int) $req->get_param('per_page')),
			's'              => $req->get_param('search'),
		)
	);

	$items = array_map(
		function (WP_Post $p) {
			return tnf_rest_serialize_post($p);
		},
		$q->posts
	);

	return new WP_REST_Response(
		array(
			'items' => $items,
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
		)
	);
}

/**
 * Single news.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_news_single(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$post = get_post((int) $req['id']);
	if (! $post || 'tnf_news' !== $post->post_type || 'publish' !== $post->post_status) {
		return new WP_Error('not_found', __('Not found', 'tnf-news-platform'), array('status' => 404));
	}
	return new WP_REST_Response(tnf_rest_serialize_post($post));
}

/**
 * PDF list.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_pdfs_list(WP_REST_Request $req): WP_REST_Response {
	$q = new WP_Query(
		array(
			'post_type'      => 'tnf_pdf_report',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'paged'          => max(1, (int) $req->get_param('page')),
		)
	);

	$items = array();
	foreach ($q->posts as $post) {
		$items[] = tnf_rest_serialize_pdf($post);
	}

	return new WP_REST_Response(array('items' => $items, 'total' => (int) $q->found_posts));
}

/**
 * PDF metadata for API.
 *
 * @param WP_Post $post Post.
 */
function tnf_rest_serialize_pdf(WP_Post $post): array {
	$data = tnf_rest_serialize_post($post);
	$data['restricted']          = (bool) get_post_meta($post->ID, 'tnf_restricted', true);
	$data['pdf_attachment_id']   = (int) get_post_meta($post->ID, 'tnf_pdf_attachment_id', true);
	$data['pdf_job_id']          = (string) get_post_meta($post->ID, 'tnf_pdf_job_id', true);
	$data['pdf_status']          = (string) get_post_meta($post->ID, 'tnf_pdf_status', true);
	$data['source_url']          = '';
	$aid = $data['pdf_attachment_id'];
	if ($aid) {
		$data['source_url'] = wp_get_attachment_url($aid) ?: '';
	}
	return $data;
}

/**
 * Single PDF.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_pdf_single(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$post = get_post((int) $req['id']);
	if (! $post || 'tnf_pdf_report' !== $post->post_type || 'publish' !== $post->post_status) {
		return new WP_Error('not_found', __('Not found', 'tnf-news-platform'), array('status' => 404));
	}
	return new WP_REST_Response(tnf_rest_serialize_pdf($post));
}

/**
 * PDF access: signed URL + manifest when allowed.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_pdf_access(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$post = get_post((int) $req['id']);
	if (! $post || 'tnf_pdf_report' !== $post->post_type || 'publish' !== $post->post_status) {
		return new WP_Error('not_found', __('Not found', 'tnf-news-platform'), array('status' => 404));
	}

	$restricted = (bool) get_post_meta($post->ID, 'tnf_restricted', true);
	$user_id    = get_current_user_id();
	$allowed    = ! $restricted || tnf_user_has_subscription($user_id);

	return new WP_REST_Response(
		array(
			'subscription_ok' => $allowed,
			'job_id'          => (string) get_post_meta($post->ID, 'tnf_pdf_job_id', true),
			'pdf_status'      => (string) get_post_meta($post->ID, 'tnf_pdf_status', true),
			'pdf_url'         => $allowed && get_post_meta($post->ID, 'tnf_pdf_attachment_id', true)
				? wp_get_attachment_url((int) get_post_meta($post->ID, 'tnf_pdf_attachment_id', true))
				: null,
			'pages'           => json_decode((string) get_post_meta($post->ID, 'tnf_pdf_pages_json', true), true) ?: array(),
		)
	);
}

/**
 * Videos list.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_videos_list(WP_REST_Request $req): WP_REST_Response {
	$q = new WP_Query(
		array(
			'post_type'      => 'tnf_video',
			'post_status'    => 'publish',
			'posts_per_page' => 30,
		)
	);

	$items = array();
	foreach ($q->posts as $post) {
		$row = tnf_rest_serialize_post($post);
		$row['embed_url'] = (string) get_post_meta($post->ID, 'tnf_embed_url', true);
		$items[]          = $row;
	}

	return new WP_REST_Response(array('items' => $items));
}

/**
 * Create submission.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_submissions_create(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$title     = sanitize_text_field($req->get_param('title'));
	$content   = wp_kses_post($req->get_param('content'));
	$video_url = esc_url_raw((string) $req->get_param('video_url'));
	if ($title === '') {
		return new WP_Error('bad_request', __('Title required', 'tnf-news-platform'), array('status' => 400));
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'tnf_user_submission',
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'pending',
			'post_author'  => get_current_user_id(),
		),
		true
	);

	if (is_wp_error($post_id)) {
		return $post_id;
	}

	update_post_meta($post_id, 'tnf_submission_status', 'pending');
	if ($video_url !== '') {
		update_post_meta((int) $post_id, 'tnf_embed_url', $video_url);
	}

	return new WP_REST_Response(
		array(
			'id'     => $post_id,
			'status' => 'pending',
		),
		201
	);
}

/**
 * Current user submissions.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_submissions_mine(WP_REST_Request $req): WP_REST_Response {
	$q = new WP_Query(
		array(
			'post_type'      => 'tnf_user_submission',
			'author'         => get_current_user_id(),
			'post_status'    => array('pending', 'draft', 'publish', 'private'),
			'posts_per_page' => 50,
		)
	);

	$items = array();
	foreach ($q->posts as $post) {
		$items[] = array(
			'id'     => $post->ID,
			'title'  => get_the_title($post),
			'status' => $post->post_status,
			'meta'   => get_post_meta($post->ID, 'tnf_submission_status', true),
		);
	}

	return new WP_REST_Response(array('items' => $items));
}

/**
 * Approve submission → create news article.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_submissions_approve(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$news_id = tnf_user_submission_approve((int) $req['id']);
	if (is_wp_error($news_id)) {
		return $news_id;
	}

	return new WP_REST_Response(
		array(
			'submission_id' => (int) $req['id'],
			'news_id'       => $news_id,
		)
	);
}

/**
 * FastAPI worker callback: mark PDF job complete and store page manifest.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_internal_pdf_job_complete(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$secret = defined('TNF_WP_CALLBACK_SECRET') ? (string) TNF_WP_CALLBACK_SECRET : '';
	$hdr    = $req->get_header('X-WP-Callback-Secret');
	if ($secret === '' || ! hash_equals($secret, (string) $hdr)) {
		return new WP_Error('forbidden', __('Invalid callback secret', 'tnf-news-platform'), array('status' => 403));
	}

	$json        = $req->get_json_params();
	$job_id      = sanitize_text_field((string) ( $json['job_id'] ?? $req->get_param('job_id') ));
	$external_id = sanitize_text_field((string) ( $json['external_id'] ?? $req->get_param('external_id') ));
	$status      = sanitize_text_field((string) ( $json['status'] ?? $req->get_param('status') ));
	$pages       = isset($json['pages'] ) ? $json['pages'] : $req->get_param('pages');

	if ($job_id === '' || $external_id === '' || ! is_array($pages)) {
		return new WP_Error('bad_request', __('job_id, external_id, pages required', 'tnf-news-platform'), array('status' => 400));
	}

	if (! preg_match('/^post-(\d+)$/', $external_id, $m)) {
		return new WP_Error('bad_request', __('Invalid external_id', 'tnf-news-platform'), array('status' => 400));
	}

	$post_id = (int) $m[1];
	$post    = get_post($post_id);
	if (! $post || 'tnf_pdf_report' !== $post->post_type) {
		return new WP_Error('not_found', __('Post not found', 'tnf-news-platform'), array('status' => 404));
	}

	update_post_meta($post_id, 'tnf_pdf_job_id', $job_id);
	update_post_meta($post_id, 'tnf_pdf_status', $status ?: 'ready');
	update_post_meta($post_id, 'tnf_pdf_pages_json', wp_json_encode($pages));
	delete_post_meta($post_id, 'tnf_pdf_error');
	tnf_pdf_report_maybe_set_featured_image_from_pages($post_id, $pages);

	return new WP_REST_Response(array('ok' => true, 'post_id' => $post_id));
}

/**
 * Best-effort: set featured image from first rendered page URL.
 *
 * @param int                                $post_id PDF report post ID.
 * @param array<int,array<string,mixed>> $pages   Worker page manifest.
 */
function tnf_pdf_report_maybe_set_featured_image_from_pages(int $post_id, array $pages): void {
	if ($post_id <= 0 || has_post_thumbnail($post_id)) {
		return;
	}

	$first_url = '';
	foreach ($pages as $row) {
		if (! is_array($row)) {
			continue;
		}
		$page_no = isset($row['page']) ? (int) $row['page'] : 0;
		$url     = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';
		if ($page_no < 1 || $url === '' || ! wp_http_validate_url($url)) {
			continue;
		}
		if ($first_url === '' || $page_no === 1) {
			$first_url = $url;
		}
		if ($page_no === 1) {
			break;
		}
	}

	if ($first_url === '') {
		return;
	}

	$tmp = download_url($first_url, 25);
	if (is_wp_error($tmp)) {
		return;
	}

	$filename = wp_basename((string) wp_parse_url($first_url, PHP_URL_PATH));
	if (! is_string($filename) || $filename === '') {
		$filename = 'epaper-page-1.png';
	}
	if (! preg_match('/\.(png|jpe?g|webp)$/i', $filename)) {
		$filename .= '.png';
	}

	$file_array = array(
		'name'     => sanitize_file_name('tnf-pdf-' . $post_id . '-' . $filename),
		'tmp_name' => $tmp,
	);

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = media_handle_sideload(
		$file_array,
		$post_id,
		__('ePaper first page thumbnail', 'tnf-news-platform')
	);

	@unlink($tmp); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if (is_wp_error($attachment_id) || ! is_int($attachment_id) || $attachment_id <= 0) {
		return;
	}

	set_post_thumbnail($post_id, $attachment_id);
}

/**
 * Reject submission.
 *
 * @param WP_REST_Request $req Request.
 */
function tnf_rest_submissions_reject(WP_REST_Request $req): WP_REST_Response|WP_Error {
	$reason = sanitize_text_field((string) $req->get_param('reason'));
	$result = tnf_user_submission_reject((int) $req['id'], $reason);
	if (is_wp_error($result)) {
		return $result;
	}

	return new WP_REST_Response(array('id' => (int) $req['id'], 'status' => 'rejected'));
}
