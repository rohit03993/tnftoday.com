<?php
/**
 * TNF CMS dashboard — publication KPIs and staff / personal stats.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Whether the current user sees newsroom-wide stats (editors / admins).
 */
function tnf_admin_user_sees_org_stats(): bool {
	return current_user_can('edit_others_posts') || current_user_can('manage_options');
}

/**
 * Friendly display name (never raw email when avoidable).
 *
 * @param WP_User $user User object.
 */
function tnf_admin_user_greeting_name(WP_User $user): string {
	$first = trim((string) $user->first_name);
	$last  = trim((string) $user->last_name);
	if ($first !== '' || $last !== '') {
		return trim($first . ' ' . $last);
	}

	$nick = trim((string) $user->nickname);
	if ($nick !== '' && ! is_email($nick)) {
		return $nick;
	}

	$display = trim((string) $user->display_name);
	if ($display !== '' && ! is_email($display) && $display !== $user->user_login) {
		return $display;
	}

	if ($user->user_login !== '' && ! is_email($user->user_login)) {
		return $user->user_login;
	}

	if (is_email($user->user_email)) {
		$local = strstr($user->user_email, '@', true);
		if (is_string($local) && $local !== '') {
			$pretty = str_replace(array('.', '_', '-'), ' ', $local);
			return ucwords($pretty);
		}
	}

	return __('Editor', 'tnf-news-platform');
}

/**
 * Role label for dashboard subtitle.
 *
 * @param WP_User $user User.
 */
function tnf_admin_user_role_label(WP_User $user): string {
	if (user_can($user, 'manage_options')) {
		return __('Administrator', 'tnf-news-platform');
	}
	if (user_can($user, 'edit_others_posts')) {
		return __('Head editor', 'tnf-news-platform');
	}
	if (in_array('author', (array) $user->roles, true)) {
		return __('Author', 'tnf-news-platform');
	}
	if (in_array('contributor', (array) $user->roles, true)) {
		return __('Contributor', 'tnf-news-platform');
	}
	return __('Editorial team', 'tnf-news-platform');
}

/**
 * Flush cached dashboard aggregates.
 */
function tnf_admin_flush_dashboard_stats_cache(): void {
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like('_transient_tnf_dash_') . '%',
			$wpdb->esc_like('_transient_timeout_tnf_dash_') . '%'
		)
	);
}

/**
 * @param string  $post_type Post type.
 * @param int     $author_id 0 = all authors with edit_others, else filter.
 * @param string  $status    Post status.
 */
function tnf_admin_count_posts(string $post_type, int $author_id = 0, string $status = 'publish'): int {
	if ($author_id <= 0 && post_type_exists($post_type)) {
		$counts = wp_count_posts($post_type, 'readable');
		if (is_object($counts) && isset($counts->{$status})) {
			return (int) $counts->{$status};
		}
	}

	$args = array(
		'post_type'              => $post_type,
		'post_status'            => $status,
		'posts_per_page'         => 1,
		'fields'                 => 'ids',
		'no_found_rows'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);
	if ($author_id > 0) {
		$args['author'] = $author_id;
	}
	$q = new WP_Query($args);
	return (int) $q->found_posts;
}

/**
 * Count category terms (editorial sections).
 */
function tnf_admin_count_categories(): int {
	if (! taxonomy_exists('category')) {
		return 0;
	}
	$n = wp_count_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
		)
	);
	return is_wp_error($n) ? 0 : (int) $n;
}

/**
 * Published news with a featured image set.
 *
 * @param int $author_id 0 = all.
 */
function tnf_admin_count_news_with_featured_image(int $author_id = 0): int {
	global $wpdb;

	$sql = "
		SELECT COUNT(DISTINCT p.ID)
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
		WHERE p.post_type = 'tnf_news'
		AND p.post_status = 'publish'
		AND pm.meta_value > 0
	";
	$prepare = array();
	if ($author_id > 0) {
		$sql      .= ' AND p.post_author = %d';
		$prepare[] = $author_id;
	}

	if ($prepare) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var($wpdb->prepare($sql, ...$prepare));
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return (int) $wpdb->get_var($sql);
}

/**
 * Media attachments uploaded by a user (images only).
 *
 * @param int $user_id User ID.
 */
function tnf_admin_count_user_image_uploads(int $user_id): int {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_status = 'inherit'
			AND post_author = %d
			AND post_mime_type LIKE %s",
			$user_id,
			'image/%'
		)
	);
}

/**
 * Staff rows: published news + featured images per author.
 *
 * @return array<int, array{user_id: int, name: string, role: string, news: int, with_image: int, uploads: int}>
 */
function tnf_admin_get_staff_stats_rows(): array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		"
		SELECT p.post_author AS user_id,
			COUNT(DISTINCT p.ID) AS news_count,
			SUM(CASE WHEN pm.meta_value IS NOT NULL AND pm.meta_value != '' AND pm.meta_value != '0' THEN 1 ELSE 0 END) AS with_image
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
		WHERE p.post_type = 'tnf_news'
		AND p.post_status = 'publish'
		GROUP BY p.post_author
		HAVING news_count > 0
		ORDER BY news_count DESC
		LIMIT 25
		",
		ARRAY_A
	);

	if (! is_array($rows)) {
		return array();
	}

	$out = array();
	foreach ($rows as $row) {
		$user_id = (int) $row['user_id'];
		if ($user_id <= 0) {
			continue;
		}
		$user = get_userdata($user_id);
		if (! $user instanceof WP_User) {
			continue;
		}
		$out[] = array(
			'user_id'    => $user_id,
			'name'       => tnf_admin_user_greeting_name($user),
			'role'       => tnf_admin_user_role_label($user),
			'news'       => (int) $row['news_count'],
			'with_image' => (int) $row['with_image'],
			'uploads'    => tnf_admin_count_user_image_uploads($user_id),
		);
	}

	return $out;
}

/**
 * Aggregate KPIs for dashboard cards.
 *
 * @param int  $user_id   Scope user (0 = site-wide).
 * @param bool $org_wide  Site-wide metrics.
 * @return array<string, mixed>
 */
function tnf_admin_get_dashboard_kpis(int $user_id, bool $org_wide): array {
	$scope = $org_wide ? 'org' : 'user_' . $user_id;
	$key   = 'tnf_dash_kpi_' . $scope;
	$cached = get_transient($key);
	if (is_array($cached)) {
		return $cached;
	}

	$author_filter = $org_wide ? 0 : $user_id;

	$data = array(
		'news_published'   => tnf_admin_count_posts('tnf_news', $author_filter, 'publish'),
		'news_drafts'      => tnf_admin_count_posts('tnf_news', $author_filter, 'draft'),
		'news_pending'     => tnf_admin_count_posts('tnf_news', $author_filter, 'pending'),
		'epaper_published' => tnf_admin_count_posts('tnf_pdf_report', $org_wide ? 0 : $author_filter, 'publish'),
		'videos_published' => tnf_admin_count_posts('tnf_video', $org_wide ? 0 : $author_filter, 'publish'),
		'categories'       => $org_wide ? tnf_admin_count_categories() : 0,
		'news_with_image'  => tnf_admin_count_news_with_featured_image($author_filter),
		'media_uploads'    => $user_id > 0 ? tnf_admin_count_user_image_uploads($user_id) : 0,
		'pending_subs'     => $org_wide ? tnf_admin_pending_submission_count_global() : 0,
	);

	set_transient($key, $data, 5 * MINUTE_IN_SECONDS);

	return $data;
}

/**
 * Pending submissions (global).
 */
function tnf_admin_pending_submission_count_global(): int {
	$q = new WP_Query(
		array(
			'post_type'      => 'tnf_user_submission',
			'post_status'    => 'pending',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		)
	);
	return (int) $q->found_posts;
}

/**
 * Recent published news lines for dashboard list.
 *
 * @param int  $user_id  Author filter; 0 = all.
 * @param int  $limit    Max items.
 * @return array<int, array{title: string, date: string, edit_url: string}>
 */
function tnf_admin_get_recent_news_lines(int $user_id, int $limit = 6): array {
	$args = array(
		'post_type'              => 'tnf_news',
		'post_status'            => 'publish',
		'posts_per_page'         => $limit,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
	);
	if ($user_id > 0) {
		$args['author'] = $user_id;
	}

	$q = new WP_Query($args);
	$lines = array();
	foreach ($q->posts as $post) {
		if (! $post instanceof WP_Post) {
			continue;
		}
		$lines[] = array(
			'title'    => get_the_title($post),
			'date'     => get_the_date('', $post),
			'edit_url' => get_edit_post_link($post->ID, 'raw'),
		);
	}
	return $lines;
}

/**
 * KPI overview widget.
 */
function tnf_admin_render_stats_overview_widget(): void {
	$user_id  = get_current_user_id();
	$org_wide = tnf_admin_user_sees_org_stats();
	$kpis     = tnf_admin_get_dashboard_kpis($user_id, $org_wide);

	echo '<div class="tnf-dash-kpi-grid">';

	if ($org_wide) {
		tnf_admin_render_kpi_card(
			__('News published', 'tnf-news-platform'),
			(string) (int) $kpis['news_published'],
			admin_url('edit.php?post_type=tnf_news'),
			__('Live articles on site', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('Categories', 'tnf-news-platform'),
			(string) (int) $kpis['categories'],
			admin_url('edit-tags.php?taxonomy=category'),
			__('Sections (Health, Sports, …)', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('ePaper editions', 'tnf-news-platform'),
			(string) (int) $kpis['epaper_published'],
			admin_url('edit.php?post_type=tnf_pdf_report'),
			__('PDF reports published', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('Videos', 'tnf-news-platform'),
			(string) (int) $kpis['videos_published'],
			admin_url('edit.php?post_type=tnf_video'),
			__('Published video posts', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('News with image', 'tnf-news-platform'),
			(string) (int) $kpis['news_with_image'],
			admin_url('edit.php?post_type=tnf_news'),
			__('Featured image set', 'tnf-news-platform')
		);
		if ((int) $kpis['pending_subs'] > 0) {
			tnf_admin_render_kpi_card(
				__('Pending submissions', 'tnf-news-platform'),
				(string) (int) $kpis['pending_subs'],
				admin_url('edit.php?post_type=tnf_user_submission&post_status=pending'),
				__('Awaiting moderation', 'tnf-news-platform'),
				true
			);
		}
		if ((int) $kpis['news_drafts'] > 0 || (int) $kpis['news_pending'] > 0) {
			tnf_admin_render_kpi_card(
				__('In workflow', 'tnf-news-platform'),
				(string) ((int) $kpis['news_drafts'] + (int) $kpis['news_pending']),
				admin_url('edit.php?post_type=tnf_news&post_status=draft'),
				sprintf(
					/* translators: 1: drafts 2: pending */
					__('%1$d drafts · %2$d pending review', 'tnf-news-platform'),
					(int) $kpis['news_drafts'],
					(int) $kpis['news_pending']
				)
			);
		}
	} else {
		tnf_admin_render_kpi_card(
			__('Your news published', 'tnf-news-platform'),
			(string) (int) $kpis['news_published'],
			admin_url('edit.php?post_type=tnf_news&author=' . $user_id),
			__('Articles live under your name', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('With featured image', 'tnf-news-platform'),
			(string) (int) $kpis['news_with_image'],
			admin_url('edit.php?post_type=tnf_news&author=' . $user_id),
			__('Stories with a lead image', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('Images uploaded', 'tnf-news-platform'),
			(string) (int) $kpis['media_uploads'],
			admin_url('upload.php'),
			__('Your media library images', 'tnf-news-platform')
		);
		tnf_admin_render_kpi_card(
			__('Your drafts', 'tnf-news-platform'),
			(string) (int) $kpis['news_drafts'],
			admin_url('edit.php?post_type=tnf_news&post_status=draft&author=' . $user_id),
			__('Not published yet', 'tnf-news-platform')
		);
		if ((int) $kpis['news_pending'] > 0) {
			tnf_admin_render_kpi_card(
				__('Pending review', 'tnf-news-platform'),
				(string) (int) $kpis['news_pending'],
				admin_url('edit.php?post_type=tnf_news&post_status=pending&author=' . $user_id),
				__('Waiting for editor approval', 'tnf-news-platform'),
				true
			);
		}
	}

	echo '</div>';
}

/**
 * @param string $label Card title.
 * @param string $value Main number.
 * @param string $url   Link target.
 * @param string $hint  Subtitle.
 * @param bool   $alert Highlight as attention.
 */
function tnf_admin_render_kpi_card(string $label, string $value, string $url, string $hint, bool $alert = false): void {
	$class = 'tnf-dash-kpi' . ($alert ? ' is-alert' : '');
	echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">';
	echo '<span class="tnf-dash-kpi__value">' . esc_html($value) . '</span>';
	echo '<span class="tnf-dash-kpi__label">' . esc_html($label) . '</span>';
	echo '<span class="tnf-dash-kpi__hint">' . esc_html($hint) . '</span>';
	echo '</a>';
}

/**
 * Team performance table (editors / admins).
 */
function tnf_admin_render_staff_performance_widget(): void {
	$rows = tnf_admin_get_staff_stats_rows();
	if ($rows === array()) {
		echo '<p class="tnf-dash-empty">' . esc_html__('No published news yet. Stats appear after the first article goes live.', 'tnf-news-platform') . '</p>';
		return;
	}

	echo '<div class="tnf-dash-table-wrap"><table class="tnf-dash-table widefat striped">';
	echo '<thead><tr>';
	echo '<th scope="col">' . esc_html__('Staff', 'tnf-news-platform') . '</th>';
	echo '<th scope="col" class="num">' . esc_html__('News', 'tnf-news-platform') . '</th>';
	echo '<th scope="col" class="num">' . esc_html__('With image', 'tnf-news-platform') . '</th>';
	echo '<th scope="col" class="num">' . esc_html__('Uploads', 'tnf-news-platform') . '</th>';
	echo '</tr></thead><tbody>';

	foreach ($rows as $row) {
		$profile = get_edit_user_link($row['user_id']);
		echo '<tr>';
		echo '<td>';
		if (is_string($profile) && $profile !== '') {
			echo '<a href="' . esc_url($profile) . '"><strong>' . esc_html($row['name']) . '</strong></a>';
		} else {
			echo '<strong>' . esc_html($row['name']) . '</strong>';
		}
		echo '<br /><span class="tnf-dash-muted">' . esc_html($row['role']) . '</span>';
		echo '</td>';
		echo '<td class="num" data-label="' . esc_attr__('News', 'tnf-news-platform') . '">' . esc_html((string) $row['news']) . '</td>';
		echo '<td class="num" data-label="' . esc_attr__('With image', 'tnf-news-platform') . '">' . esc_html((string) $row['with_image']) . '</td>';
		echo '<td class="num" data-label="' . esc_attr__('Uploads', 'tnf-news-platform') . '">' . esc_html((string) $row['uploads']) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table></div>';
	echo '<p class="tnf-dash-footnote">' . esc_html__('“With image” = published news that has a featured image. “Uploads” = image files in Media Library by that user.', 'tnf-news-platform') . '</p>';
}

/**
 * Personal breakdown for authors / contributors.
 *
 * @param bool $include_recent Show latest articles list (off when unified dashboard has its own column).
 */
function tnf_admin_render_my_performance_widget(bool $include_recent = true): void {
	$user_id = get_current_user_id();
	$kpis    = tnf_admin_get_dashboard_kpis($user_id, false);

	echo '<ul class="tnf-dash-personal">';
	printf(
		'<li><span>%s</span><strong>%d</strong></li>',
		esc_html__('Published news', 'tnf-news-platform'),
		(int) $kpis['news_published']
	);
	printf(
		'<li><span>%s</span><strong>%d</strong></li>',
		esc_html__('With featured image', 'tnf-news-platform'),
		(int) $kpis['news_with_image']
	);
	printf(
		'<li><span>%s</span><strong>%d</strong></li>',
		esc_html__('Image uploads', 'tnf-news-platform'),
		(int) $kpis['media_uploads']
	);
	printf(
		'<li><span>%s</span><strong>%d</strong></li>',
		esc_html__('Drafts', 'tnf-news-platform'),
		(int) $kpis['news_drafts']
	);
	echo '</ul>';

	if ($include_recent) {
		$lines = tnf_admin_get_recent_news_lines($user_id, 5);
		if ($lines !== array()) {
			echo '<h4 class="tnf-dash-subhead">' . esc_html__('Your latest published', 'tnf-news-platform') . '</h4>';
			echo '<ul class="tnf-dash-recent">';
			foreach ($lines as $line) {
				$url = is_string($line['edit_url']) ? $line['edit_url'] : '';
				echo '<li>';
				if ($url !== '') {
					echo '<a href="' . esc_url($url) . '">' . esc_html($line['title']) . '</a>';
				} else {
					echo esc_html($line['title']);
				}
				echo ' <time>' . esc_html($line['date']) . '</time>';
				echo '</li>';
			}
			echo '</ul>';
		}
	}

	echo '<p class="tnf-dash-actions">';
	echo '<a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=tnf_news')) . '">' . esc_html__('Write news', 'tnf-news-platform') . '</a> ';
	echo '<a class="button" href="' . esc_url(admin_url('upload.php')) . '">' . esc_html__('Media', 'tnf-news-platform') . '</a>';
	echo '</p>';
}

/**
 * Recent newsroom activity (org view).
 */
function tnf_admin_render_recent_newsroom_widget(): void {
	$scope_user = tnf_admin_user_sees_org_stats() ? 0 : get_current_user_id();
	$lines      = tnf_admin_get_recent_news_lines($scope_user, 8);
	if ($lines === array()) {
		echo '<p class="tnf-dash-empty">' . esc_html__('No published news yet.', 'tnf-news-platform') . '</p>';
		return;
	}
	echo '<ul class="tnf-dash-recent">';
	foreach ($lines as $line) {
		$url = is_string($line['edit_url']) ? $line['edit_url'] : '';
		echo '<li>';
		if ($url !== '') {
			echo '<a href="' . esc_url($url) . '">' . esc_html($line['title']) . '</a>';
		} else {
			echo esc_html($line['title']);
		}
		echo ' <time>' . esc_html($line['date']) . '</time>';
		echo '</li>';
	}
	echo '</ul>';
	$all_url = tnf_admin_user_sees_org_stats()
		? admin_url('edit.php?post_type=tnf_news')
		: admin_url('edit.php?post_type=tnf_news&author=' . get_current_user_id());
	echo '<p class="tnf-dash-more"><a href="' . esc_url($all_url) . '">' . esc_html__('All news →', 'tnf-news-platform') . '</a></p>';
}

/**
 * Invalidate stats cache when TNF content changes.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function tnf_admin_maybe_flush_stats_on_save(int $post_id, WP_Post $post): void {
	if (wp_is_post_revision($post_id)) {
		return;
	}
	$types = array('tnf_news', 'tnf_pdf_report', 'tnf_video', 'tnf_user_submission', 'attachment');
	if (in_array($post->post_type, $types, true)) {
		tnf_admin_flush_dashboard_stats_cache();
	}
}

add_action('save_post', 'tnf_admin_maybe_flush_stats_on_save', 20, 2);
