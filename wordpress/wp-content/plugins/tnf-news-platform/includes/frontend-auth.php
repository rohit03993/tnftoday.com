<?php
/**
 * Front-end auth shortcodes and handlers.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register frontend auth features.
 */
function tnf_register_frontend_auth(): void {
	add_shortcode('tnf_login_form', 'tnf_sc_login_form');
	add_shortcode('tnf_register_form', 'tnf_sc_register_form');
	add_shortcode('tnf_forgot_password_form', 'tnf_sc_forgot_password_form');
	add_shortcode('tnf_account_box', 'tnf_sc_account_box');
	add_shortcode('tnf_logout_link', 'tnf_sc_logout_link');
	add_action('init', 'tnf_handle_frontend_auth_forms');
	add_action('init', 'tnf_ensure_auth_pages');
	add_action('wp_enqueue_scripts', 'tnf_enqueue_frontend_auth_styles', 25);
	add_action('template_redirect', 'tnf_auth_protect_restricted_pdf_pages', 11);
	add_action('template_redirect', 'tnf_auth_handle_missing_auth_slug', 12);
	add_action('login_init', 'tnf_auth_redirect_wp_login_to_frontend', 1);
	add_action('login_enqueue_scripts', 'tnf_auth_enqueue_wp_login_styles');
	add_filter('login_redirect', 'tnf_auth_login_redirect_by_role', 10, 3);
	add_action('admin_init', 'tnf_auth_block_wp_admin_for_members');
	add_filter('show_admin_bar', 'tnf_auth_hide_admin_bar_for_members');
	add_filter('body_class', 'tnf_auth_body_class');
}

/**
 * Redirect wp-login.php UI to branded frontend auth pages.
 */
function tnf_auth_redirect_wp_login_to_frontend(): void {
	if (is_user_logged_in()) {
		return;
	}

	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
	if ($method !== 'GET') {
		return;
	}

	$action = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : 'login';
	$target = '';

	if ($action === 'lostpassword' || $action === 'retrievepassword' || $action === 'rp' || $action === 'resetpass') {
		$target = tnf_auth_page_url('forgot-password');
	} elseif ($action === 'register') {
		$target = tnf_auth_page_url('register');
	} elseif ($action === 'login' || $action === '') {
		$target = tnf_auth_page_url('login');
	}

	if ($target === '') {
		return;
	}

	$redirect_to = isset($_REQUEST['redirect_to']) ? rawurldecode((string) wp_unslash($_REQUEST['redirect_to'])) : '';
	if ($redirect_to !== '') {
		$target = add_query_arg(
			array(
				'redirect_to' => rawurlencode(esc_url_raw($redirect_to)),
			),
			$target
		);
	}

	wp_safe_redirect($target, 302);
	exit;
}

/**
 * Build URL with tnf auth status.
 *
 * @param string $status Status key.
 * @param string $msg    Message key.
 */
function tnf_auth_status_url(string $status, string $msg): string {
	$base = wp_get_referer() ?: home_url('/');
	return add_query_arg(
		array(
			'tnf_auth_status' => $status,
			'tnf_auth_msg'    => $msg,
		),
		$base
	);
}

/**
 * Human-readable auth message from query args.
 */
function tnf_auth_flash_message_html(): string {
	$status = isset($_GET['tnf_auth_status']) ? sanitize_key((string) wp_unslash($_GET['tnf_auth_status'])) : '';
	$msg    = isset($_GET['tnf_auth_msg']) ? sanitize_key((string) wp_unslash($_GET['tnf_auth_msg'])) : '';
	if ($status === '' || $msg === '') {
		return '';
	}

	$map = array(
		'login_ok'       => __('Login successful.', 'tnf-news-platform'),
		'login_failed'   => __('Invalid username/email or password.', 'tnf-news-platform'),
		'register_ok'    => __('Account created successfully. You are now logged in.', 'tnf-news-platform'),
		'register_failed'=> __('Could not create account. Please try again.', 'tnf-news-platform'),
		'password_sent'  => __('Password reset email sent. Please check your inbox.', 'tnf-news-platform'),
		'password_failed'=> __('Could not start password reset. Check email/username and try again.', 'tnf-news-platform'),
		'logout_ok'      => __('You are logged out.', 'tnf-news-platform'),
		'missing_fields' => __('Please fill all required fields.', 'tnf-news-platform'),
		'password_mismatch' => __('Passwords do not match.', 'tnf-news-platform'),
		'invalid_nonce'  => __('Security check failed. Please retry.', 'tnf-news-platform'),
		'premium_required' => __('Please login with an active premium account to access this report.', 'tnf-news-platform'),
		'submission_ok'    => __('Your news submission was sent for review.', 'tnf-news-platform'),
		'submission_failed'=> __('Could not submit news. Please try again.', 'tnf-news-platform'),
	);

	$text = $map[$msg] ?? '';
	if ($text === '') {
		return '';
	}

	$class = ($status === 'ok') ? 'notice notice-success' : 'notice notice-error';
	return '<div class="' . esc_attr($class) . '" style="padding:10px;margin:0 0 12px;"><p style="margin:0;">' . esc_html($text) . '</p></div>';
}

/**
 * Handle posted auth forms.
 */
function tnf_handle_frontend_auth_forms(): void {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}
	if (! isset($_POST['tnf_auth_action'])) {
		return;
	}

	$action = sanitize_key((string) wp_unslash($_POST['tnf_auth_action']));
	$nonce  = isset($_POST['tnf_auth_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['tnf_auth_nonce'])) : '';
	if (! wp_verify_nonce($nonce, 'tnf_auth_' . $action)) {
		wp_safe_redirect(tnf_auth_status_url('err', 'invalid_nonce'));
		exit;
	}

	if ($action === 'login') {
		tnf_auth_handle_login();
	}
	if ($action === 'register') {
		tnf_auth_handle_register();
	}
	if ($action === 'forgot') {
		tnf_auth_handle_forgot_password();
	}
	if ($action === 'submit_news') {
		tnf_auth_handle_submit_news();
	}
}

/**
 * Process login submit.
 */
function tnf_auth_handle_login(): void {
	$login = isset($_POST['tnf_login']) ? sanitize_text_field((string) wp_unslash($_POST['tnf_login'])) : '';
	$pass  = isset($_POST['tnf_password']) ? (string) wp_unslash($_POST['tnf_password']) : '';
	$redir = isset($_POST['tnf_redirect_to']) ? esc_url_raw((string) wp_unslash($_POST['tnf_redirect_to'])) : '';

	if ($login === '' || $pass === '') {
		wp_safe_redirect(tnf_auth_status_url('err', 'missing_fields'));
		exit;
	}

	$username = $login;
	if (is_email($login)) {
		$user = get_user_by('email', $login);
		if ($user) {
			$username = $user->user_login;
		}
	}

	$remember = ! empty($_POST['tnf_remember']);
	$auth = wp_signon(
		array(
			'user_login'    => $username,
			'user_password' => $pass,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if (is_wp_error($auth)) {
		wp_safe_redirect(tnf_auth_status_url('err', 'login_failed'));
		exit;
	}

	$target = $redir !== '' ? $redir : tnf_auth_default_redirect_for_user((int) $auth->ID);
	wp_safe_redirect($target);
	exit;
}

/**
 * Process register submit.
 */
function tnf_auth_handle_register(): void {
	$username = isset($_POST['tnf_username']) ? sanitize_user((string) wp_unslash($_POST['tnf_username']), true) : '';
	$email    = isset($_POST['tnf_email']) ? sanitize_email((string) wp_unslash($_POST['tnf_email'])) : '';
	$pass1    = isset($_POST['tnf_password_1']) ? (string) wp_unslash($_POST['tnf_password_1']) : '';
	$pass2    = isset($_POST['tnf_password_2']) ? (string) wp_unslash($_POST['tnf_password_2']) : '';

	if ($username === '' || $email === '' || $pass1 === '' || $pass2 === '') {
		wp_safe_redirect(tnf_auth_status_url('err', 'missing_fields'));
		exit;
	}
	if ($pass1 !== $pass2) {
		wp_safe_redirect(tnf_auth_status_url('err', 'password_mismatch'));
		exit;
	}
	if (username_exists($username) || email_exists($email)) {
		wp_safe_redirect(tnf_auth_status_url('err', 'register_failed'));
		exit;
	}

	$user_id = wp_create_user($username, $pass1, $email);
	if (is_wp_error($user_id) || ! $user_id) {
		wp_safe_redirect(tnf_auth_status_url('err', 'register_failed'));
		exit;
	}

	$user = get_userdata($user_id);
	if ($user && in_array('subscriber', (array) $user->roles, true)) {
		$user->set_role('tnf_subscriber');
	}

	wp_set_current_user((int) $user_id);
	wp_set_auth_cookie((int) $user_id, true, is_ssl());

	$target = add_query_arg(
		array(
			'tnf_auth_status' => 'ok',
			'tnf_auth_msg'    => 'register_ok',
		),
		tnf_auth_page_url('my-account')
	);
	wp_safe_redirect($target);
	exit;
}

/**
 * Process forgot password submit.
 */
function tnf_auth_handle_forgot_password(): void {
	$login_or_email = isset($_POST['tnf_login_or_email']) ? sanitize_text_field((string) wp_unslash($_POST['tnf_login_or_email'])) : '';
	if ($login_or_email === '') {
		wp_safe_redirect(tnf_auth_status_url('err', 'missing_fields'));
		exit;
	}

	$user_data = get_user_by('login', $login_or_email);
	if (! $user_data && is_email($login_or_email)) {
		$user_data = get_user_by('email', $login_or_email);
	}
	if (! $user_data) {
		wp_safe_redirect(tnf_auth_status_url('err', 'password_failed'));
		exit;
	}

	$ret = retrieve_password($user_data->user_login);
	if ($ret !== true) {
		wp_safe_redirect(tnf_auth_status_url('err', 'password_failed'));
		exit;
	}

	wp_safe_redirect(tnf_auth_status_url('ok', 'password_sent'));
	exit;
}

/**
 * Handle front-end user submission form.
 */
function tnf_auth_handle_submit_news(): void {
	if (! is_user_logged_in() || ! current_user_can('create_tnf_submissions')) {
		wp_safe_redirect(tnf_auth_status_url('err', 'submission_failed'));
		exit;
	}

	$title   = isset($_POST['tnf_submission_title']) ? sanitize_text_field((string) wp_unslash($_POST['tnf_submission_title'])) : '';
	$content = isset($_POST['tnf_submission_content']) ? wp_kses_post((string) wp_unslash($_POST['tnf_submission_content'])) : '';

	if ($title === '' || $content === '') {
		wp_safe_redirect(tnf_auth_status_url('err', 'missing_fields'));
		exit;
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

	if (is_wp_error($post_id) || ! $post_id) {
		wp_safe_redirect(tnf_auth_status_url('err', 'submission_failed'));
		exit;
	}

	update_post_meta((int) $post_id, 'tnf_submission_status', 'pending');
	wp_safe_redirect(tnf_auth_status_url('ok', 'submission_ok'));
	exit;
}

/**
 * Login form shortcode.
 */
function tnf_sc_login_form(): string {
	if (is_user_logged_in()) {
		return tnf_sc_account_box();
	}
	$redir = home_url('/');
	if (isset($_GET['redirect_to'])) {
		$decoded = rawurldecode((string) wp_unslash($_GET['redirect_to']));
		$decoded = esc_url_raw($decoded);
		if ($decoded !== '') {
			$redir = $decoded;
		}
	}
	ob_start();
	echo tnf_auth_flash_message_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<div class="tnf-auth-shell">
		<div class="tnf-auth-card">
		<h1 class="tnf-auth-title"><?php esc_html_e('Login', 'tnf-news-platform'); ?></h1>
	<form method="post" class="tnf-auth-form tnf-auth-form-login">
		<p><label><?php esc_html_e('Username or Email', 'tnf-news-platform'); ?><br /><input type="text" name="tnf_login" required /></label></p>
		<p><label><?php esc_html_e('Password', 'tnf-news-platform'); ?><br /><input type="password" name="tnf_password" required /></label></p>
		<p><label><input type="checkbox" name="tnf_remember" value="1" /> <?php esc_html_e('Remember me', 'tnf-news-platform'); ?></label></p>
		<input type="hidden" name="tnf_auth_action" value="login" />
		<input type="hidden" name="tnf_redirect_to" value="<?php echo esc_attr($redir); ?>" />
		<?php wp_nonce_field('tnf_auth_login', 'tnf_auth_nonce'); ?>
		<p><button type="submit"><?php esc_html_e('Login', 'tnf-news-platform'); ?></button></p>
		<p class="tnf-auth-links"><a href="<?php echo esc_url(tnf_auth_page_url('forgot-password')); ?>"><?php esc_html_e('Forgot password?', 'tnf-news-platform'); ?></a> · <a href="<?php echo esc_url(tnf_auth_page_url('register')); ?>"><?php esc_html_e('Create account', 'tnf-news-platform'); ?></a></p>
	</form>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Register form shortcode.
 */
function tnf_sc_register_form(): string {
	if (is_user_logged_in()) {
		return tnf_sc_account_box();
	}
	ob_start();
	echo tnf_auth_flash_message_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<div class="tnf-auth-shell">
		<div class="tnf-auth-card">
		<h1 class="tnf-auth-title"><?php esc_html_e('Create Account', 'tnf-news-platform'); ?></h1>
	<form method="post" class="tnf-auth-form tnf-auth-form-register">
		<p><label><?php esc_html_e('Username', 'tnf-news-platform'); ?><br /><input type="text" name="tnf_username" required /></label></p>
		<p><label><?php esc_html_e('Email', 'tnf-news-platform'); ?><br /><input type="email" name="tnf_email" required /></label></p>
		<p><label><?php esc_html_e('Password', 'tnf-news-platform'); ?><br /><input type="password" name="tnf_password_1" required /></label></p>
		<p><label><?php esc_html_e('Confirm Password', 'tnf-news-platform'); ?><br /><input type="password" name="tnf_password_2" required /></label></p>
		<input type="hidden" name="tnf_auth_action" value="register" />
		<?php wp_nonce_field('tnf_auth_register', 'tnf_auth_nonce'); ?>
		<p><button type="submit"><?php esc_html_e('Create Account', 'tnf-news-platform'); ?></button></p>
		<p class="tnf-auth-links"><a href="<?php echo esc_url(tnf_auth_page_url('login')); ?>"><?php esc_html_e('Already have an account? Login', 'tnf-news-platform'); ?></a></p>
	</form>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Forgot password shortcode.
 */
function tnf_sc_forgot_password_form(): string {
	if (is_user_logged_in()) {
		return tnf_sc_account_box();
	}
	ob_start();
	echo tnf_auth_flash_message_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<div class="tnf-auth-shell">
		<div class="tnf-auth-card">
		<h1 class="tnf-auth-title"><?php esc_html_e('Forgot Password', 'tnf-news-platform'); ?></h1>
	<form method="post" class="tnf-auth-form tnf-auth-form-forgot">
		<p><label><?php esc_html_e('Username or Email', 'tnf-news-platform'); ?><br /><input type="text" name="tnf_login_or_email" required /></label></p>
		<input type="hidden" name="tnf_auth_action" value="forgot" />
		<?php wp_nonce_field('tnf_auth_forgot', 'tnf_auth_nonce'); ?>
		<p><button type="submit"><?php esc_html_e('Send Reset Link', 'tnf-news-platform'); ?></button></p>
		<p class="tnf-auth-links"><a href="<?php echo esc_url(tnf_auth_page_url('login')); ?>"><?php esc_html_e('Back to login', 'tnf-news-platform'); ?></a></p>
	</form>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Account info box shortcode.
 */
function tnf_sc_account_box(): string {
	if (! is_user_logged_in()) {
		return '';
	}
	$user = wp_get_current_user();
	if (! $user || ! $user->ID) {
		return '';
	}
	$is_premium = tnf_user_has_subscription((int) $user->ID);
	$is_admin   = user_can($user, 'manage_options');
	$role_label = $is_admin ? __('Administrator', 'tnf-news-platform') : __('Contributor Member', 'tnf-news-platform');
	$logout_url = wp_logout_url(home_url('/'));
	$sub_q = new WP_Query(
		array(
			'post_type'      => 'tnf_user_submission',
			'post_status'    => array('pending', 'draft', 'private', 'publish'),
			'author'         => (int) $user->ID,
			'posts_per_page' => 8,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$submission_counts = tnf_auth_submission_counts_for_user((int) $user->ID);
	ob_start();
	echo '<div class="tnf-auth-shell tnf-account-shell">';
	echo tnf_auth_flash_message_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="tnf-account-box tnf-auth-card">';
	echo '<h1 class="tnf-auth-title">' . esc_html__('My Account', 'tnf-news-platform') . '</h1>';
	echo '<div class="tnf-kpi-grid">';
	echo '<article class="tnf-kpi-card"><span class="tnf-kpi-label">' . esc_html__('Total Submissions', 'tnf-news-platform') . '</span><strong class="tnf-kpi-value">' . esc_html((string) $submission_counts['total']) . '</strong></article>';
	echo '<article class="tnf-kpi-card"><span class="tnf-kpi-label">' . esc_html__('Approved', 'tnf-news-platform') . '</span><strong class="tnf-kpi-value">' . esc_html((string) $submission_counts['approved']) . '</strong></article>';
	echo '<article class="tnf-kpi-card"><span class="tnf-kpi-label">' . esc_html__('Pending', 'tnf-news-platform') . '</span><strong class="tnf-kpi-value">' . esc_html((string) $submission_counts['pending']) . '</strong></article>';
	echo '<article class="tnf-kpi-card"><span class="tnf-kpi-label">' . esc_html__('Rejected', 'tnf-news-platform') . '</span><strong class="tnf-kpi-value">' . esc_html((string) $submission_counts['rejected']) . '</strong></article>';
	echo '</div>';
	echo '<div class="tnf-account-grid">';
	echo '<section class="tnf-dash-card">';
	echo '<h3>' . esc_html__('Profile', 'tnf-news-platform') . '</h3>';
	echo '<p><strong>' . esc_html($user->display_name ?: $user->user_login) . '</strong><br />' . esc_html($user->user_email) . '</p>';
	echo '<p>' . esc_html__('Role:', 'tnf-news-platform') . ' <span class="tnf-role-badge">' . esc_html($role_label) . '</span></p>';
	echo '<p>' . esc_html__('Subscription:', 'tnf-news-platform') . ' <span class="tnf-sub-badge ' . esc_attr($is_premium ? 'is-active' : 'is-inactive') . '">' . esc_html($is_premium ? __('Active', 'tnf-news-platform') : __('Inactive', 'tnf-news-platform')) . '</span></p>';
	echo '<p><a href="' . esc_url(home_url('/news/')) . '">' . esc_html__('Browse News', 'tnf-news-platform') . '</a></p>';
	echo '<p><a href="' . esc_url($logout_url) . '">' . esc_html__('Logout', 'tnf-news-platform') . '</a></p>';
	echo '</section>';
	echo '</div>';

	if (current_user_can('create_tnf_submissions')) {
		echo '<section class="tnf-dash-card tnf-submit-card">';
		echo '<h3>' . esc_html__('Submit News', 'tnf-news-platform') . '</h3>';
		echo '<p class="tnf-dash-note">' . esc_html__('Share your verified news. Our editorial team will review and publish approved submissions.', 'tnf-news-platform') . '</p>';
		echo '<form method="post" class="tnf-auth-form tnf-submit-form">';
		echo '<p><label>' . esc_html__('Title', 'tnf-news-platform') . '<br /><input type="text" name="tnf_submission_title" required /></label></p>';
		echo '<p><label>' . esc_html__('Content', 'tnf-news-platform') . '<br /><textarea name="tnf_submission_content" rows="5" required></textarea></label></p>';
		echo '<input type="hidden" name="tnf_auth_action" value="submit_news" />';
		wp_nonce_field('tnf_auth_submit_news', 'tnf_auth_nonce');
		echo '<p><button type="submit">' . esc_html__('Send for Review', 'tnf-news-platform') . '</button></p>';
		echo '</form>';
		echo '</section>';
	}

	echo '<section class="tnf-dash-card tnf-submissions-card">';
	echo '<h3>' . esc_html__('My Submissions', 'tnf-news-platform') . '</h3>';
	if ($sub_q->have_posts()) {
		echo '<ul class="tnf-submissions-list">';
		foreach ($sub_q->posts as $p) {
			$status = (string) get_post_meta((int) $p->ID, 'tnf_submission_status', true);
			if ($status === '') {
				$status = (string) $p->post_status;
			}
			$status_key = sanitize_html_class(strtolower($status));
			$meta_line = get_the_date('', $p);
			echo '<li><div><strong>' . esc_html(get_the_title($p)) . '</strong><span class="tnf-submissions-meta">' . esc_html($meta_line) . '</span></div><span class="tnf-sub-status tnf-sub-status--' . esc_attr($status_key) . '">' . esc_html(ucfirst($status)) . '</span></li>';
		}
		echo '</ul>';
	} else {
		echo '<p>' . esc_html__('No submissions yet.', 'tnf-news-platform') . '</p>';
	}
	echo '</section>';

	echo '</div></div>';
	wp_reset_postdata();
	$html = (string) ob_get_clean();
	return $html;
}

/**
 * Logout link shortcode.
 */
function tnf_sc_logout_link(): string {
	if (! is_user_logged_in()) {
		return '';
	}
	$logout_url = wp_logout_url(home_url('/'));
	return '<a href="' . esc_url($logout_url) . '">' . esc_html__('Logout', 'tnf-news-platform') . '</a>';
}

/**
 * Resolve a configured auth page URL by slug fallback.
 *
 * @param string $slug Page slug.
 */
function tnf_auth_page_url(string $slug): string {
	$page = tnf_auth_get_page_by_slug($slug);
	if ($page) {
		$link = get_permalink($page);
		if (is_string($link) && $link !== '') {
			return $link;
		}
	}

	// Hard fallbacks when pretty permalink auth pages are unavailable.
	if ($slug === 'login') {
		return wp_login_url();
	}
	if ($slug === 'register') {
		return wp_registration_url();
	}
	if ($slug === 'forgot-password') {
		return wp_lostpassword_url();
	}

	return home_url('/' . trim($slug, '/') . '/');
}

/**
 * Resolve auth page by exact slug across statuses.
 *
 * @param string $slug Page slug.
 */
function tnf_auth_get_page_by_slug(string $slug): ?WP_Post {
	$page = get_page_by_path($slug);
	if ($page instanceof WP_Post) {
		return $page;
	}

	$posts = get_posts(
		array(
			'post_type'        => 'page',
			'name'             => $slug,
			'post_status'      => array('publish', 'private', 'draft', 'pending', 'trash'),
			'numberposts'      => 1,
			'suppress_filters' => true,
		)
	);

	if (! empty($posts) && $posts[0] instanceof WP_Post) {
		return $posts[0];
	}

	return null;
}

/**
 * Ensure required frontend auth pages exist.
 */
function tnf_ensure_auth_pages(): void {
	$pages = array(
		'login'           => array('title' => __('Login', 'tnf-news-platform'), 'content' => '[tnf_login_form]'),
		'register'        => array('title' => __('Register', 'tnf-news-platform'), 'content' => '[tnf_register_form]'),
		'forgot-password' => array('title' => __('Forgot Password', 'tnf-news-platform'), 'content' => '[tnf_forgot_password_form]'),
		'my-account'      => array('title' => __('My Account', 'tnf-news-platform'), 'content' => '[tnf_account_box]'),
	);

	foreach ($pages as $slug => $def) {
		$existing = tnf_auth_get_page_by_slug($slug);
		if ($existing instanceof WP_Post) {
			// Recover trashed auth pages to prevent slug collisions like register-2.
			if ($existing->post_status === 'trash') {
				wp_untrash_post((int) $existing->ID);
			}

			$update = array(
				'ID'          => (int) $existing->ID,
				'post_status' => 'publish',
			);

			// Keep shortcode content consistent for required auth pages.
			if (strpos((string) $existing->post_content, $def['content']) === false) {
				$update['post_content'] = $def['content'];
			}

			wp_update_post($update);
			update_post_meta((int) $existing->ID, '_wp_page_template', 'page-no-title.html');
			continue;
		}
		$new_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $def['title'],
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => $def['content'],
			)
		);
		if (is_int($new_id) && $new_id > 0) {
			update_post_meta($new_id, '_wp_page_template', 'page-no-title.html');
		}
	}
}

/**
 * Enqueue auth page styles.
 */
function tnf_enqueue_frontend_auth_styles(): void {
	if (is_admin()) {
		return;
	}

	if (tnf_is_auth_page()) {
		$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-auth.css';
		$ver  = is_readable($path) ? (string) filemtime($path) : TNF_NEWS_PLATFORM_VERSION;
		wp_enqueue_style(
			'tnf-auth-pages',
			TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-auth.css',
			array('tnf-frontend-chrome'),
			$ver
		);
		return;
	}

	$post = get_post();
	if (! $post instanceof WP_Post) {
		return;
	}

	$needs = has_shortcode($post->post_content, 'tnf_login_form')
		|| has_shortcode($post->post_content, 'tnf_register_form')
		|| has_shortcode($post->post_content, 'tnf_forgot_password_form')
		|| has_shortcode($post->post_content, 'tnf_account_box');

	if (! $needs) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-auth.css';
	$ver  = is_readable($path) ? (string) filemtime($path) : TNF_NEWS_PLATFORM_VERSION;
	wp_enqueue_style(
		'tnf-auth-pages',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-auth.css',
		array('tnf-frontend-chrome'),
		$ver
	);
}

/**
 * True when current page is an auth page.
 */
function tnf_is_auth_page(): bool {
	if (! is_page()) {
		return false;
	}
	return is_page(array('login', 'register', 'forgot-password', 'my-account'));
}

/**
 * Body class hook for auth pages.
 *
 * @param array<int,string> $classes Existing classes.
 * @return array<int,string>
 */
function tnf_auth_body_class(array $classes): array {
	if (tnf_is_auth_page()) {
		$classes[] = 'tnf-auth-page';
	}
	return $classes;
}

/**
 * Protect restricted PDF report single pages and redirect to login/account flow.
 */
function tnf_auth_protect_restricted_pdf_pages(): void {
	if (! is_singular('tnf_pdf_report')) {
		return;
	}

	$post_id = get_queried_object_id();
	if (! $post_id) {
		return;
	}

	$restricted = (bool) get_post_meta((int) $post_id, 'tnf_restricted', true);
	if (! $restricted) {
		return;
	}

	$current_url = (is_ssl() ? 'https://' : 'http://') . (string) ($_SERVER['HTTP_HOST'] ?? '') . (string) ($_SERVER['REQUEST_URI'] ?? '/');

	if (! is_user_logged_in()) {
		$login_url = add_query_arg(
			array(
				'redirect_to'     => rawurlencode($current_url),
				'tnf_auth_status' => 'err',
				'tnf_auth_msg'    => 'premium_required',
			),
			tnf_auth_page_url('login')
		);
		wp_safe_redirect($login_url);
		exit;
	}

	if (! tnf_user_has_subscription(get_current_user_id())) {
		$account_url = add_query_arg(
			array(
				'tnf_auth_status' => 'err',
				'tnf_auth_msg'    => 'premium_required',
			),
			tnf_auth_page_url('my-account')
		);
		wp_safe_redirect($account_url);
		exit;
	}
}

/**
 * If auth slug URL 404s (e.g., plain permalink mode), redirect to actual page permalink.
 */
function tnf_auth_handle_missing_auth_slug(): void {
	if (is_admin() || ! is_404()) {
		return;
	}

	$request_path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
	$path = trim((string) wp_parse_url($request_path, PHP_URL_PATH), '/');
	if ($path === '') {
		return;
	}

	$known = array('login', 'register', 'forgot-password', 'my-account');
	if (! in_array($path, $known, true)) {
		return;
	}

	// Ensure page exists before resolving permalink.
	tnf_ensure_auth_pages();

	$target = tnf_auth_page_url($path);
	if ($target !== '') {
		wp_safe_redirect($target, 302);
		exit;
	}
}

/**
 * Apply modern TNF styling to wp-login.php.
 */
function tnf_auth_enqueue_wp_login_styles(): void {
	wp_enqueue_style(
		'tnf-wp-login',
		TNF_NEWS_PLATFORM_URL . 'assets/css/wp-login.css',
		array(),
		TNF_NEWS_PLATFORM_VERSION
	);
}

/**
 * Redirect by role after wp-login.php sign-in.
 *
 * @param string           $redirect_to Requested redirect URL.
 * @param string           $requested_redirect_to Raw requested redirect.
 * @param WP_User|WP_Error $user Auth result.
 */
function tnf_auth_login_redirect_by_role(string $redirect_to, string $requested_redirect_to, $user): string {
	if (is_wp_error($user) || ! ($user instanceof WP_User)) {
		return $redirect_to;
	}

	if ($requested_redirect_to !== '') {
		$admin_root = admin_url();
		$is_admin_target = strpos($requested_redirect_to, $admin_root) === 0;
		if ($is_admin_target && ! tnf_auth_user_can_access_admin((int) $user->ID)) {
			return tnf_auth_page_url('my-account');
		}
		return $requested_redirect_to;
	}

	return tnf_auth_default_redirect_for_user((int) $user->ID);
}

/**
 * Default post-login destination by role.
 *
 * @param int $user_id User ID.
 */
function tnf_auth_default_redirect_for_user(int $user_id): string {
	if (tnf_auth_user_can_access_admin($user_id)) {
		return admin_url();
	}
	return tnf_auth_page_url('my-account');
}

/**
 * Admin/editor capability check for wp-admin landing.
 *
 * @param int $user_id User ID.
 */
function tnf_auth_user_can_access_admin(int $user_id): bool {
	if ($user_id <= 0) {
		return false;
	}
	if (user_can($user_id, 'manage_options')) {
		return true;
	}
	return user_can($user_id, 'edit_others_posts');
}

/**
 * Block wp-admin for normal registered users.
 */
function tnf_auth_block_wp_admin_for_members(): void {
	if (! is_user_logged_in()) {
		return;
	}

	$user_id = get_current_user_id();
	if (tnf_auth_user_can_access_admin($user_id)) {
		return;
	}

	// Keep frontend async requests working.
	if ((defined('DOING_AJAX') && DOING_AJAX) || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
		return;
	}

	wp_safe_redirect(tnf_auth_page_url('my-account'));
	exit;
}

/**
 * Hide top admin bar for normal members.
 *
 * @param bool $show Existing show flag.
 */
function tnf_auth_hide_admin_bar_for_members(bool $show): bool {
	if (! is_user_logged_in()) {
		return $show;
	}
	return tnf_auth_user_can_access_admin(get_current_user_id());
}

/**
 * Get compact submission stats for account dashboard.
 *
 * @param int $user_id User ID.
 * @return array<string,int>
 */
function tnf_auth_submission_counts_for_user(int $user_id): array {
	$counts = array(
		'total'    => 0,
		'approved' => 0,
		'pending'  => 0,
		'rejected' => 0,
	);
	if ($user_id <= 0) {
		return $counts;
	}

	$q = new WP_Query(
		array(
			'post_type'      => 'tnf_user_submission',
			'post_status'    => array('pending', 'draft', 'private', 'publish', 'trash'),
			'author'         => $user_id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	if (empty($q->posts)) {
		return $counts;
	}

	$counts['total'] = count($q->posts);
	foreach ($q->posts as $submission_id) {
		$status = (string) get_post_meta((int) $submission_id, 'tnf_submission_status', true);
		if ($status === '') {
			$status = (string) get_post_status((int) $submission_id);
		}
		$status = strtolower($status);

		if (in_array($status, array('publish', 'approved'), true)) {
			$counts['approved']++;
			continue;
		}
		if (in_array($status, array('pending', 'draft'), true)) {
			$counts['pending']++;
			continue;
		}
		if (in_array($status, array('rejected', 'trash'), true)) {
			$counts['rejected']++;
		}
	}

	return $counts;
}

