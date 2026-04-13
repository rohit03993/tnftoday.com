<?php
/**
 * Title: News blog with sidebar
 * Slug: twentytwentyfive/template-home-with-sidebar-news-blog
 * Template Types: front-page, index, home
 * Viewport width: 1400
 * Inserter: no
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */

if ( ! function_exists( 'twentytwentyfive_tnf_render_news_cards' ) ) {
	/**
	 * Render compact TNF news cards for a category slug.
	 *
	 * @param string $slug  Category slug.
	 * @param int    $count Number of cards.
	 * @param string $title Section title.
	 */
	function twentytwentyfive_tnf_render_news_cards( string $slug, int $count, string $title, string $extra_class = '' ): void {
		$query = new WP_Query(
			array(
				'post_type'      => 'tnf_news',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'category_name'  => $slug,
			)
		);
		if ( ! $query->have_posts() ) {
			return;
		}
		?>
		<section class="tnf-cat-block <?php echo esc_attr( $extra_class ); ?>">
			<div class="tnf-cat-head">
				<h3><?php echo esc_html( $title ); ?></h3>
				<a href="<?php echo esc_url( home_url( '/category/' . $slug . '/' ) ); ?>"><?php esc_html_e( 'See More', 'twentytwentyfive' ); ?></a>
			</div>
			<div class="tnf-cat-grid">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					?>
					<article class="tnf-news-mini-card">
						<a class="tnf-news-mini-card__thumb" href="<?php the_permalink(); ?>">
							<img src="<?php echo esc_url( twentytwentyfive_tnf_news_thumbnail_url( (int) get_the_ID() ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
						</a>
						<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
						<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'twentytwentyfive_tnf_render_recent_news_grid' ) ) {
	/**
	 * Render recent news grid.
	 *
	 * @param int $count Number of posts.
	 */
	function twentytwentyfive_tnf_render_recent_news_grid( int $count = 9 ): void {
		$query = new WP_Query(
			array(
				'post_type'      => 'tnf_news',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
			)
		);
		if ( ! $query->have_posts() ) {
			return;
		}
		?>
		<section class="tnf-card tnf-recent-news">
			<div class="tnf-cat-head">
				<h3><?php esc_html_e( 'Recent News', 'twentytwentyfive' ); ?></h3>
			</div>
			<div class="tnf-recent-grid">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$terms = get_the_terms( get_the_ID(), 'category' );
					$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : __( 'News', 'twentytwentyfive' );
					?>
					<article class="tnf-recent-card">
						<a class="tnf-recent-card__thumb" href="<?php the_permalink(); ?>">
							<img src="<?php echo esc_url( twentytwentyfive_tnf_news_thumbnail_url( (int) get_the_ID() ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
						</a>
						<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
						<div class="tnf-recent-card__meta">
							<span class="tnf-recent-card__cat"><?php echo esc_html( $cat ); ?></span>
							<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
						</div>
					</article>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
		<?php
	}
}

if ( ! function_exists( 'twentytwentyfive_tnf_news_thumbnail_url' ) ) {
	/**
	 * Resolve fallback thumbnail URL for tnf_news.
	 *
	 * @param int $post_id News post ID.
	 */
	function twentytwentyfive_tnf_news_thumbnail_url( int $post_id ): string {
		$thumb = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		if ( is_string( $thumb ) && $thumb !== '' ) {
			return $thumb;
		}

		return 'https://picsum.photos/seed/tnf-news-' . $post_id . '/640/360';
	}
}

if ( ! function_exists( 'twentytwentyfive_tnf_video_thumbnail_url' ) ) {
	/**
	 * Resolve best-effort thumbnail for tnf_video.
	 *
	 * @param int $post_id Video post ID.
	 */
	function twentytwentyfive_tnf_video_thumbnail_url( int $post_id ): string {
		if ( function_exists( 'tnf_video_card_thumbnail_url' ) ) {
			$u = tnf_video_card_thumbnail_url( $post_id );
			if ( is_string( $u ) && $u !== '' ) {
				return $u;
			}
		}

		return 'https://picsum.photos/seed/tnf-video-' . $post_id . '/640/360';
	}
}
?>
<div class="tnf-home-news">
	<div class="tnf-top-utility">
		<div class="tnf-shell">
			<div class="tnf-top-utility__left">
				<?php
				$u_videos = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( 'tnf_video' ) : '';
				$u_epaper = function_exists( 'get_post_type_archive_link' ) ? get_post_type_archive_link( 'tnf_pdf_report' ) : '';
				if ( is_string( $u_videos ) && $u_videos !== '' ) :
					?>
				<a href="<?php echo esc_url( $u_videos ); ?>"><?php esc_html_e( 'Videos', 'twentytwentyfive' ); ?></a>
					<?php
				endif;
				if ( is_string( $u_epaper ) && $u_epaper !== '' ) :
					?>
				<a href="<?php echo esc_url( $u_epaper ); ?>"><?php esc_html_e( 'ePaper', 'twentytwentyfive' ); ?></a>
					<?php
				endif;
				?>
				<a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php esc_html_e( 'About Us', 'twentytwentyfive' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'twentytwentyfive' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'twentytwentyfive' ); ?></a>
			</div>
			<div class="tnf-top-utility__right"><?php echo esc_html( wp_date( 'l, d M Y' ) ); ?></div>
		</div>
	</div>

	<header class="tnf-masthead">
		<div class="tnf-shell tnf-masthead-inner">
			<div class="tnf-logo-wrap">
				<?php if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) : ?>
					<div class="tnf-logo-image"><?php the_custom_logo(); ?></div>
				<?php endif; ?>
				<div class="tnf-brand"><?php bloginfo( 'name' ); ?></div>
				<div class="tnf-meta"><?php esc_html_e( 'Hindi News, Latest Updates', 'twentytwentyfive' ); ?></div>
			</div>
			<div class="tnf-head-ad">
				<span><?php esc_html_e( 'Top Banner Space', 'twentytwentyfive' ); ?></span>
			</div>
			<div class="tnf-account-wrap">
				<?php if ( is_user_logged_in() ) : ?>
					<a class="tnf-auth-nav-btn" href="<?php echo esc_url( home_url( '/my-account/' ) ); ?>"><?php esc_html_e( 'My Account', 'twentytwentyfive' ); ?></a>
				<?php else : ?>
					<a class="tnf-auth-nav-btn" href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Login', 'twentytwentyfive' ); ?></a>
					<a class="tnf-auth-nav-btn tnf-auth-nav-btn--secondary" href="<?php echo esc_url( function_exists( 'tnf_auth_page_url' ) ? tnf_auth_page_url( 'register' ) : home_url( '/register/' ) ); ?>"><?php esc_html_e( 'Register', 'twentytwentyfive' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<div class="tnf-top-nav">
		<div class="tnf-shell">
			<a class="tnf-nav-quicklink" href="<?php echo esc_url( get_post_type_archive_link( 'tnf_pdf_report' ) ?: home_url( '/pdf-reports/' ) ); ?>">
				<?php esc_html_e( 'ePaper', 'twentytwentyfive' ); ?>
			</a>
			<button
				type="button"
				class="tnf-nav-toggle"
				aria-expanded="false"
				aria-controls="tnf-main-menu"
				aria-label="<?php esc_attr_e( 'Toggle sections menu', 'twentytwentyfive' ); ?>"
			>
				<span class="tnf-nav-toggle__icon" aria-hidden="true"></span>
				<span class="tnf-nav-toggle__text"><?php esc_html_e( 'Menu', 'twentytwentyfive' ); ?></span>
			</button>
			<nav id="tnf-main-menu" class="tnf-main-menu">
				<?php
				if ( function_exists( 'tnf_news_nav_items' ) ) :
					foreach ( tnf_news_nav_items() as $item ) :
						?>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
						<?php
					endforeach;
				endif;
				?>
			</nav>
		</div>
	</div>

	<?php
	$breaking_inner = '';
	if ( function_exists( 'tnf_news_breaking_ticker_inner_html' ) ) {
		$breaking_inner = tnf_news_breaking_ticker_inner_html();
	} else {
		$breaking_n = 14;
		$breaking   = new WP_Query(
			array(
				'post_type'      => 'tnf_news',
				'post_status'    => 'publish',
				'posts_per_page' => 14,
			)
		);
		$breaking_parts = array();
		if ( $breaking->have_posts() ) {
			while ( $breaking->have_posts() ) {
				$breaking->the_post();
				$breaking_parts[] = '<a class="tnf-breaking-link" href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
			}
			wp_reset_postdata();
		}
		if ( $breaking_parts !== array() ) {
			$breaking_inner = implode( '<span class="tnf-breaking-sep" aria-hidden="true">◆</span>', $breaking_parts );
		}
	}

	if ( $breaking_inner !== '' ) :
		?>
	<div class="tnf-breaking" role="region" aria-label="<?php esc_attr_e( 'Breaking news ticker', 'twentytwentyfive' ); ?>">
		<div class="tnf-shell tnf-breaking-inner">
			<div class="tnf-breaking-badge">
				<span class="tnf-breaking-badge__dot" aria-hidden="true"></span>
				<span class="tnf-breaking-badge__text"><?php esc_html_e( 'Live Breaking', 'twentytwentyfive' ); ?></span>
			</div>
			<div class="tnf-breaking-viewport">
				<div class="tnf-breaking-marquee">
					<div class="tnf-breaking-marquee__strip">
						<?php echo $breaking_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts ?>
					</div>
					<div class="tnf-breaking-marquee__strip" aria-hidden="true">
						<?php echo $breaking_inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php
	endif;
	?>

	<div class="tnf-shell">
		<div class="tnf-layout">
			<main class="tnf-main-col">
				<section class="tnf-card tnf-hero-section">
					<div class="tnf-hero-grid">
						<div class="tnf-hero-main">
							<?php
							$hero = new WP_Query(
								array(
									'post_type'      => 'tnf_news',
									'post_status'    => 'publish',
									'posts_per_page' => 1,
								)
							);
							if ( $hero->have_posts() ) :
								$hero->the_post();
								?>
								<a class="tnf-hero-image" href="<?php the_permalink(); ?>"><img src="<?php echo esc_url( twentytwentyfive_tnf_news_thumbnail_url( (int) get_the_ID() ) ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" /></a>
								<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<?php
								wp_reset_postdata();
							endif;
							?>
						</div>
						<div class="tnf-hero-side">
							<h3><?php esc_html_e( 'Latest Headlines', 'twentytwentyfive' ); ?></h3>
							<?php
							$latest = new WP_Query(
								array(
									'post_type'      => 'tnf_news',
									'post_status'    => 'publish',
									'posts_per_page' => 8,
									'offset'         => 1,
								)
							);
							if ( $latest->have_posts() ) :
								echo '<ul>';
								while ( $latest->have_posts() ) :
									$latest->the_post();
									echo '<li><a class="tnf-list-thumb" href="' . esc_url( get_permalink() ) . '"><img src="' . esc_url( twentytwentyfive_tnf_news_thumbnail_url( (int) get_the_ID() ) ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy" /><span>' . esc_html( get_the_title() ) . '</span></a></li>';
								endwhile;
								echo '</ul>';
								wp_reset_postdata();
							endif;
							?>
						</div>
					</div>
				</section>

				<section class="tnf-card tnf-featured-videos">
					<div class="tnf-cat-head">
						<h3><?php esc_html_e( 'Featured Videos', 'twentytwentyfive' ); ?></h3>
						<?php
						$more_videos = get_post_type_archive_link( 'tnf_video' );
						$more_videos = is_string( $more_videos ) && $more_videos !== '' ? $more_videos : home_url( '/videos/' );
						?>
						<a href="<?php echo esc_url( $more_videos ); ?>"><?php esc_html_e( 'See all videos', 'twentytwentyfive' ); ?></a>
					</div>
					<div class="tnf-video-layout">
						<div class="tnf-video-hero">
							<?php
							$video_hero = new WP_Query(
								array(
									'post_type'      => 'tnf_video',
									'post_status'    => 'publish',
									'posts_per_page' => 1,
								)
							);
							if ( $video_hero->have_posts() ) :
								$video_hero->the_post();
								$hero_thumb = twentytwentyfive_tnf_video_thumbnail_url( (int) get_the_ID() );
								?>
								<a class="tnf-video-hero__thumb" href="<?php the_permalink(); ?>">
									<img src="<?php echo esc_url( $hero_thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
									<span class="tnf-video-play"></span>
								</a>
								<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
								<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								<?php
								wp_reset_postdata();
							endif;
							?>
						</div>
						<div class="tnf-video-grid">
						<?php
						$videos = new WP_Query(
							array(
								'post_type'      => 'tnf_video',
								'post_status'    => 'publish',
								'posts_per_page' => 4,
								'offset'         => 1,
							)
						);
						if ( $videos->have_posts() ) :
							while ( $videos->have_posts() ) :
								$videos->the_post();
								$video_thumb = twentytwentyfive_tnf_video_thumbnail_url( (int) get_the_ID() );
								?>
								<article class="tnf-video-card">
									<a class="tnf-video-card__thumb" href="<?php the_permalink(); ?>">
										<img src="<?php echo esc_url( $video_thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy" />
										<span class="tnf-video-play"></span>
									</a>
									<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
								</article>
								<?php
							endwhile;
							wp_reset_postdata();
						endif;
						?>
						</div>
					</div>
				</section>

				<?php
				$epaper_url = get_post_type_archive_link( 'tnf_pdf_report' );
				if ( is_string( $epaper_url ) && $epaper_url !== '' ) :
					?>
				<section class="tnf-card tnf-epaper-teaser" aria-labelledby="tnf-epaper-teaser-title">
					<div class="tnf-epaper-teaser__grid">
						<div class="tnf-epaper-teaser__copy">
							<p class="tnf-epaper-teaser__kicker"><?php esc_html_e( 'Digital edition', 'twentytwentyfive' ); ?></p>
							<h3 id="tnf-epaper-teaser-title" class="tnf-epaper-teaser__title"><?php esc_html_e( 'ePaper', 'twentytwentyfive' ); ?></h3>
							<p class="tnf-epaper-teaser__desc"><?php esc_html_e( 'Read full newspaper-style PDFs in the browser or download — same idea as a classic e-paper hub.', 'twentytwentyfive' ); ?></p>
							<a class="tnf-epaper-teaser__btn" href="<?php echo esc_url( $epaper_url ); ?>"><?php esc_html_e( 'Open ePaper library', 'twentytwentyfive' ); ?></a>
						</div>
						<div class="tnf-epaper-teaser__visual" aria-hidden="true">
							<span class="tnf-epaper-teaser__pages"></span>
						</div>
					</div>
				</section>
					<?php
				endif;
				?>

				<?php
				twentytwentyfive_tnf_render_news_cards( 'health', 4, __( 'Health', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'religion', 4, __( 'Religion', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'politics', 4, __( 'Politics', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'sports', 4, __( 'Sports', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'business', 4, __( 'Business', 'twentytwentyfive' ), 'tnf-cat-block--business' );
				twentytwentyfive_tnf_render_news_cards( 'entertainment', 4, __( 'Entertainment', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'tech', 4, __( 'Tech', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'exclusive', 4, __( 'Exclusive', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'lifestyle', 4, __( 'Lifestyle', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'cultural', 4, __( 'Cultural', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_news_cards( 'crime', 4, __( 'Crime News', 'twentytwentyfive' ) );
				twentytwentyfive_tnf_render_recent_news_grid( 9 );
				?>
			</main>

			<aside class="tnf-side-col">
				<section class="tnf-card tnf-side-widget">
					<div class="tnf-cat-head"><h3><?php esc_html_e( 'Trending News', 'twentytwentyfive' ); ?></h3></div>
					<?php
					$trend = new WP_Query(
						array(
							'post_type'      => 'tnf_news',
							'post_status'    => 'publish',
							'posts_per_page' => 8,
							'orderby'        => 'comment_count',
							'order'          => 'DESC',
						)
					);
					if ( $trend->have_posts() ) :
						echo '<ul>';
						while ( $trend->have_posts() ) :
							$trend->the_post();
							echo '<li><a class="tnf-list-thumb" href="' . esc_url( get_permalink() ) . '"><img src="' . esc_url( twentytwentyfive_tnf_news_thumbnail_url( (int) get_the_ID() ) ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy" /><span>' . esc_html( get_the_title() ) . '</span></a></li>';
						endwhile;
						echo '</ul>';
						wp_reset_postdata();
					endif;
					?>
				</section>

				<section class="tnf-card tnf-side-widget">
					<div class="tnf-cat-head"><h3><?php esc_html_e( 'Top News', 'twentytwentyfive' ); ?></h3></div>
					<?php
					$top = new WP_Query(
						array(
							'post_type'      => 'tnf_news',
							'post_status'    => 'publish',
							'posts_per_page' => 6,
							'orderby'        => 'date',
							'order'          => 'DESC',
						)
					);
					if ( $top->have_posts() ) :
						echo '<ul>';
						while ( $top->have_posts() ) :
							$top->the_post();
							echo '<li><a class="tnf-list-thumb" href="' . esc_url( get_permalink() ) . '"><img src="' . esc_url( twentytwentyfive_tnf_news_thumbnail_url( (int) get_the_ID() ) ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy" /><span>' . esc_html( get_the_title() ) . '</span></a></li>';
						endwhile;
						echo '</ul>';
						wp_reset_postdata();
					endif;
					?>
				</section>

				<section class="tnf-card tnf-side-widget">
					<div class="tnf-cat-head"><h3><?php esc_html_e( 'Weather Forecast', 'twentytwentyfive' ); ?></h3></div>
					<div class="tnf-weather-box">
						<p><?php esc_html_e( 'Agra weather overview', 'twentytwentyfive' ); ?></p>
						<a href="https://world-weather.info/forecast/india/agra/14days/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View 14-day forecast', 'twentytwentyfive' ); ?></a>
					</div>
				</section>

				<section class="tnf-card tnf-side-widget">
					<div class="tnf-cat-head"><h3><?php esc_html_e( 'User Poll', 'twentytwentyfive' ); ?></h3></div>
					<div class="tnf-poll-box">
						<p><?php esc_html_e( 'क्या आप भारत को एक हिन्दू राष्ट्र बनाना चाहते हैं?', 'twentytwentyfive' ); ?></p>
						<label><input type="radio" name="tnf_poll_vote" disabled /> <?php esc_html_e( 'हाँ', 'twentytwentyfive' ); ?></label>
						<label><input type="radio" name="tnf_poll_vote" disabled /> <?php esc_html_e( 'नहीं', 'twentytwentyfive' ); ?></label>
						<label><input type="radio" name="tnf_poll_vote" disabled /> <?php esc_html_e( 'पता नहीं', 'twentytwentyfive' ); ?></label>
					</div>
				</section>
			</aside>
		</div>
	</div>
</div>
