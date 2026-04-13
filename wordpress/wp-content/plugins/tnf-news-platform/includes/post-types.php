<?php
/**
 * Custom post types: news, PDF reports, videos, user submissions.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register all TNF CPTs.
 */
function tnf_register_post_types(): void {
	$labels_news = array(
		'name'               => __('News', 'tnf-news-platform'),
		'singular_name'      => __('News Article', 'tnf-news-platform'),
		'add_new'            => __('Add New', 'tnf-news-platform'),
		'add_new_item'       => __('Add News Article', 'tnf-news-platform'),
		'edit_item'          => __('Edit News Article', 'tnf-news-platform'),
		'new_item'           => __('New Article', 'tnf-news-platform'),
		'view_item'          => __('View Article', 'tnf-news-platform'),
		'search_items'       => __('Search News', 'tnf-news-platform'),
		'not_found'          => __('No articles found', 'tnf-news-platform'),
		'not_found_in_trash' => __('No articles in trash', 'tnf-news-platform'),
	);

	register_post_type(
		'tnf_news',
		array(
			'labels'              => $labels_news,
			'public'              => true,
			'show_in_rest'        => true,
			'rest_base'           => 'tnf-news',
			'has_archive'         => true,
			'menu_icon'           => 'dashicons-media-text',
			'supports'            => array('title', 'editor', 'thumbnail', 'excerpt', 'author'),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	// Use the same Categories as normal posts so editors see the category panel on News
	// and homepage blocks (Health, Sports, etc.) filter correctly by slug.
	register_taxonomy_for_object_type('category', 'tnf_news');
	register_taxonomy_for_object_type('post_tag', 'tnf_news');

	register_post_type(
		'tnf_pdf_report',
		array(
			'labels'              => array(
				'name'          => __('PDF Reports', 'tnf-news-platform'),
				'singular_name' => __('PDF Report', 'tnf-news-platform'),
			),
			'public'              => true,
			'show_in_rest'        => true,
			'rest_base'           => 'tnf-pdf-reports',
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'epaper',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-media-document',
			'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	register_post_type(
		'tnf_video',
		array(
			'labels'              => array(
				'name'          => __('Videos', 'tnf-news-platform'),
				'singular_name' => __('Video', 'tnf-news-platform'),
			),
			'public'              => true,
			'show_in_rest'        => true,
			'rest_base'           => 'tnf-videos',
			'has_archive'         => true,
			'rewrite'             => array(
				'slug'       => 'videos',
				'with_front' => false,
			),
			'menu_icon'           => 'dashicons-video-alt3',
			'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	register_taxonomy_for_object_type('category', 'tnf_video');
	register_taxonomy_for_object_type('post_tag', 'tnf_video');

	register_post_type(
		'tnf_user_submission',
		array(
			'labels'              => array(
				'name'          => __('User Submissions', 'tnf-news-platform'),
				'singular_name' => __('User Submission', 'tnf-news-platform'),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'        => false,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => array('title', 'editor', 'author'),
			'capability_type'     => array('tnf_submission', 'tnf_submissions'),
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'edit_post'          => 'edit_tnf_submission',
				'read_post'          => 'read_tnf_submission',
				'delete_post'        => 'delete_tnf_submission',
				'edit_posts'         => 'edit_tnf_submissions',
				'edit_others_posts'  => 'edit_others_tnf_submissions',
				'publish_posts'      => 'publish_tnf_submissions',
				'read_private_posts' => 'read_private_tnf_submissions',
				'create_posts'       => 'create_tnf_submissions',
			),
		)
	);

	register_post_meta(
		'tnf_pdf_report',
		'tnf_pdf_attachment_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_pdf_report',
		'tnf_restricted',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => false,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_pdf_report',
		'tnf_pdf_job_id',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_pdf_report',
		'tnf_pdf_status',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => 'idle',
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_pdf_report',
		'tnf_pdf_error',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_video',
		'tnf_embed_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_user_submission',
		'tnf_submission_status',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'pending',
			'auth_callback'     => function () {
				return current_user_can('read');
			},
		)
	);

	register_post_meta(
		'tnf_pdf_report',
		'tnf_pdf_pages_json',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);

	register_post_meta(
		'tnf_user_submission',
		'tnf_promoted_news_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'auth_callback'     => function () {
				return current_user_can('edit_posts');
			},
		)
	);
}

/**
 * Create category terms used in nav/homepage URLs if missing (prevents 404 on /category/{slug}/).
 */
function tnf_ensure_editorial_category_terms(): void {
	if (! taxonomy_exists('category')) {
		return;
	}

	$map = array(
		'national'      => __( 'National', 'tnf-news-platform' ),
		'health'        => __( 'Health', 'tnf-news-platform' ),
		'religion'      => __( 'Religion', 'tnf-news-platform' ),
		'entertainment' => __( 'Entertainment', 'tnf-news-platform' ),
		'tech'          => __( 'Tech', 'tnf-news-platform' ),
		'politics'      => __( 'Politics', 'tnf-news-platform' ),
		'sports'        => __( 'Sports', 'tnf-news-platform' ),
		'business'      => __( 'Business', 'tnf-news-platform' ),
		'exclusive'     => __( 'Exclusive', 'tnf-news-platform' ),
		'lifestyle'     => __( 'Lifestyle', 'tnf-news-platform' ),
		'cultural'      => __( 'Cultural', 'tnf-news-platform' ),
		'crime'         => __( 'Crime', 'tnf-news-platform' ),
	);

	foreach ( $map as $slug => $label ) {
		if ( term_exists( $slug, 'category' ) ) {
			continue;
		}
		$inserted = wp_insert_term( $label, 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $inserted ) ) {
			continue;
		}
	}
}
add_action( 'init', 'tnf_ensure_editorial_category_terms', 11 );

/**
 * Category and tag archives: list TNF news only, latest first.
 *
 * @param WP_Query $query Main query.
 */
function tnf_news_main_query_tax_archives( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_category() || $query->is_tag() ) {
		$query->set( 'post_type', 'tnf_news' );
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
		$query->set( 'posts_per_page', 20 );
	}
}
add_action( 'pre_get_posts', 'tnf_news_main_query_tax_archives' );

/**
 * News / Video / PDF archives: latest first, consistent page size.
 *
 * @param WP_Query $query Main query.
 */
function tnf_cpt_archive_main_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! $query->is_post_type_archive( array( 'tnf_news', 'tnf_video', 'tnf_pdf_report' ) ) ) {
		return;
	}

	$query->set( 'orderby', 'date' );
	$query->set( 'order', 'DESC' );
	$query->set( 'posts_per_page', 20 );
}
add_action( 'pre_get_posts', 'tnf_cpt_archive_main_query' );

/**
 * Public URLs: /epaper/ (PDF reports), /videos/ (tnf_video).
 *
 * @param array<int, string> $classes Body classes.
 * @return array<int, string>
 */
function tnf_body_class_cpt_archives( array $classes ): array {
	if ( is_post_type_archive( 'tnf_pdf_report' ) ) {
		$classes[] = 'tnf-epaper-archive';
	}
	if ( is_post_type_archive( 'tnf_video' ) ) {
		$classes[] = 'tnf-videos-archive';
	}
	return $classes;
}
add_filter( 'body_class', 'tnf_body_class_cpt_archives' );

/**
 * Shorter archive titles for video & ePaper hubs (used by query-title and headings).
 *
 * @param string $title     Default title.
 * @param string $post_type Post type key.
 */
function tnf_filter_post_type_archive_title( string $title, string $post_type ): string {
	if ( 'tnf_pdf_report' === $post_type ) {
		return __( 'ePaper', 'tnf-news-platform' );
	}
	if ( 'tnf_video' === $post_type ) {
		return __( 'Video news', 'tnf-news-platform' );
	}
	return $title;
}
add_filter( 'post_type_archive_title', 'tnf_filter_post_type_archive_title', 10, 2 );
