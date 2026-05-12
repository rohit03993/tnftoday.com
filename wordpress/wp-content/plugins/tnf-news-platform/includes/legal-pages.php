<?php
/**
 * Bootstrap static pages linked from the site footer (About, Contact, Privacy, Terms).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * First administrator user ID for post_author (fallback 1).
 */
function tnf_legal_pages_default_author_id(): int {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => array('ID'),
		)
	);
	if (! empty($admins) && isset($admins[0]->ID)) {
		return (int) $admins[0]->ID;
	}
	return 1;
}

/**
 * Whether a published page exists for this URL slug (visitors can open it).
 */
function tnf_legal_page_is_published_for_slug(string $slug): bool {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return false;
	}
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return ! empty($found);
}

/**
 * Permanently remove trashed pages that still hold this slug so a new published page can use it.
 */
function tnf_legal_pages_delete_trashed_slug(string $slug): void {
	$slug = sanitize_title($slug);
	if ($slug === '') {
		return;
	}
	$ids = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'page',
			'post_status'    => 'trash',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	foreach ($ids as $id) {
		wp_delete_post((int) $id, true);
	}
}

/**
 * Default block markup for each legal / info page (no duplicate H1 — template outputs title).
 *
 * @return array<string, array{title: string, content: string}>
 */
function tnf_legal_pages_definitions(): array {
	$site = get_bloginfo('name');
	if (! is_string($site) || trim($site) === '') {
		$site = 'TNF Today';
	}

	$email = sanitize_email((string) get_option('admin_email'));
	if (! is_email($email)) {
		$email = 'contact@tnftoday.com';
	}
	$email_e = esc_html($email);
	$email_a = esc_attr($email);

	$p1_about = esc_html(sprintf(__('Welcome to %s.', 'tnf-news-platform'), $site));
	$p2_about = esc_html(__('We publish timely news and analysis for readers in India and beyond. Editorial teams work to verify facts and present clear context.', 'tnf-news-platform'));
	$p3_about = esc_html(__('Content on this site is for general information. For corrections or story tips, use the contact page.', 'tnf-news-platform'));

	$p1_contact = esc_html(__('Reach the team using the email below. We read every message; response time may vary by volume.', 'tnf-news-platform'));
	$p2_contact = esc_html(__('For advertising or partnerships, mention it in the subject line so we can route your note correctly.', 'tnf-news-platform'));

	$p1_priv = esc_html(sprintf(__('This policy describes how %s handles information when you use our website.', 'tnf-news-platform'), $site));
	$p2_priv  = esc_html(__('We may collect standard server logs and analytics needed to run and improve the service. If you contact us, we keep your message only as long as needed to respond.', 'tnf-news-platform'));
	$p3_priv  = esc_html(__('We may use cookies or similar technologies for preferences, security, and measurement. You can control cookies through your browser settings.', 'tnf-news-platform'));
	$p4_priv  = esc_html(sprintf(__('Questions about privacy: %s', 'tnf-news-platform'), $email));

	$p1_terms = esc_html(sprintf(__('By using %s, you agree to these terms. If you do not agree, please do not use the site.', 'tnf-news-platform'), $site));
	$p2_terms = esc_html(__('Articles, images, and branding are protected by applicable law. You may not copy or redistribute our content for commercial use without permission.', 'tnf-news-platform'));
	$p3_terms = esc_html(__('News is provided for general information. We strive for accuracy but make no warranties; use at your own risk.', 'tnf-news-platform'));

	return array(
		'about-us'       => array(
			'title'   => __('About Us', 'tnf-news-platform'),
			'content' => '<!-- wp:paragraph -->
<p>' . $p1_about . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Our mission', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $p2_about . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>' . $p3_about . '</p>
<!-- /wp:paragraph -->',
		),
		'contact-us'     => array(
			'title'   => __('Contact Us', 'tnf-news-platform'),
			'content' => '<!-- wp:paragraph -->
<p>' . $p1_contact . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Email', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><a href="mailto:' . $email_a . '">' . $email_e . '</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>' . $p2_contact . '</p>
<!-- /wp:paragraph -->',
		),
		'privacy-policy' => array(
			'title'   => __('Privacy Policy', 'tnf-news-platform'),
			'content' => '<!-- wp:paragraph -->
<p>' . $p1_priv . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Information we collect', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $p2_priv . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Cookies', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $p3_priv . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Contact', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $p4_priv . '</p>
<!-- /wp:paragraph -->',
		),
		'terms-of-use'   => array(
			'title'   => __('Terms of Use', 'tnf-news-platform'),
			'content' => '<!-- wp:paragraph -->
<p>' . $p1_terms . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Use of content', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $p2_terms . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html(__('Disclaimer', 'tnf-news-platform')) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . $p3_terms . '</p>
<!-- /wp:paragraph -->',
		),
	);
}

/**
 * Create missing footer-linked published pages (self-heals bad boot state / trashed slug holders).
 */
function tnf_ensure_legal_pages(): void {
	if (wp_installing()) {
		return;
	}

	$defs = tnf_legal_pages_definitions();

	if (get_option('tnf_legal_pages_boot_v1', '') === 'yes') {
		$still_ok = true;
		foreach (array_keys($defs) as $slug) {
			if (! tnf_legal_page_is_published_for_slug($slug)) {
				$still_ok = false;
				break;
			}
		}
		if ($still_ok) {
			return;
		}
		delete_option('tnf_legal_pages_boot_v1');
	}

	foreach ($defs as $slug => $row) {
		if (tnf_legal_page_is_published_for_slug($slug)) {
			continue;
		}
		tnf_legal_pages_delete_trashed_slug($slug);

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => $row['title'],
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $row['content'],
					'post_author'  => tnf_legal_pages_default_author_id(),
				)
			),
			true
		);
		if (is_wp_error($post_id)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log('tnf_ensure_legal_pages: ' . $post_id->get_error_message());
			}
		}
	}

	$all_ok = true;
	foreach (array_keys($defs) as $slug) {
		if (! tnf_legal_page_is_published_for_slug($slug)) {
			$all_ok = false;
			break;
		}
	}

	if ($all_ok) {
		update_option('tnf_legal_pages_boot_v1', 'yes', false);
		if (get_option('tnf_legal_pages_rewrite_flushed', '') !== 'yes') {
			flush_rewrite_rules(false);
			update_option('tnf_legal_pages_rewrite_flushed', 'yes', false);
		}
	}
}

add_action('init', 'tnf_ensure_legal_pages', 100);
