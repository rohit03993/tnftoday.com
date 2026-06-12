<?php
/**
 * About Us page — official TNF Today Media Network profile (from company PDF).
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

define('TNF_ABOUT_US_PAGE_VERSION', 4);

/**
 * Register About Us hooks.
 */
function tnf_register_about_us_page(): void {
	add_filter('body_class', 'tnf_about_us_body_class');
	add_filter('the_content', 'tnf_about_us_replace_content', 1);
	add_action('wp_enqueue_scripts', 'tnf_enqueue_about_us_styles', 25);
	add_action('init', 'tnf_sync_about_us_page_marker', 101);
}

/**
 * @param array<int,string> $classes Body classes.
 * @return array<int,string>
 */
function tnf_about_us_body_class(array $classes): array {
	if (tnf_about_us_is_page()) {
		$classes[] = 'tnf-about-us-page';
	}
	return $classes;
}

/**
 * Whether the current request is the About Us page.
 */
function tnf_about_us_is_page(): bool {
	return is_page('about-us');
}

/**
 * Replace default legal stub with designed profile markup.
 *
 * @param string $content Post content.
 */
function tnf_about_us_replace_content(string $content): string {
	if (! tnf_about_us_is_page() || ! in_the_loop() || ! is_main_query()) {
		return $content;
	}
	return tnf_about_us_markup();
}

/**
 * Enqueue About Us styles on the profile page only.
 */
function tnf_enqueue_about_us_styles(): void {
	if (! tnf_about_us_is_page()) {
		return;
	}

	$path = TNF_NEWS_PLATFORM_PATH . 'assets/css/frontend-about-us.css';
	if (! is_readable($path)) {
		return;
	}

	wp_enqueue_style(
		'tnf-frontend-about-us',
		TNF_NEWS_PLATFORM_URL . 'assets/css/frontend-about-us.css',
		array('tnf-frontend-chrome'),
		(string) filemtime($path)
	);
}

/**
 * Store a minimal editor marker so WP admin shows the page is plugin-managed.
 */
function tnf_sync_about_us_page_marker(): void {
	if (wp_installing()) {
		return;
	}

	$stored = (int) get_option('tnf_about_us_page_v', 0);
	if ($stored >= TNF_ABOUT_US_PAGE_VERSION) {
		return;
	}

	$page = get_page_by_path('about-us');
	if (! $page instanceof WP_Post) {
		return;
	}

	wp_update_post(
		wp_slash(
			array(
				'ID'           => (int) $page->ID,
				'post_title'   => __('About Us', 'tnf-news-platform'),
				'post_content' => '<!-- wp:paragraph -->
<p>' . esc_html(__('This page is rendered by the TNF News Platform plugin. Edit the profile in includes/about-us-page.php.', 'tnf-news-platform')) . '</p>
<!-- /wp:paragraph -->',
			)
		)
	);

	update_option('tnf_about_us_page_v', TNF_ABOUT_US_PAGE_VERSION, false);
}

/**
 * Full About Us page markup (Hindi profile from official PDF).
 */
function tnf_about_us_markup(): string {
	$site_url = esc_url(home_url('/'));

	ob_start();
	?>
<div class="tnf-about-us">
	<header class="tnf-about-us__hero">
		<div class="tnf-about-us__hero-inner">
			<div class="tnf-about-us__brand">
				<span class="tnf-about-us__brand-mark" aria-hidden="true"></span>
				<div class="tnf-about-us__brand-text">
					<span class="tnf-about-us__brand-name">TNF TODAY</span>
					<span class="tnf-about-us__brand-type">Media Network Pvt. Ltd.</span>
				</div>
			</div>
			<p class="tnf-about-us__hero-kicker"><?php esc_html_e('Official Profile', 'tnf-news-platform'); ?></p>
			<h1>TNF Today Media Network Private Limited</h1>
			<p class="tnf-about-us__hero-sub">आधिकारिक परिचय पत्र व संगठनात्मक ढांचा</p>
			<p class="tnf-about-us__hero-tagline"><em>Thoughts · News · Facts Today</em> — पत्रकारिता की विश्वसनीयता को पुनर्जीवित करने का संकल्प</p>
		</div>
	</header>

	<div class="tnf-about-us__stats-wrap">
		<div class="tnf-about-us__stats" aria-label="<?php esc_attr_e('Key facts', 'tnf-news-platform'); ?>">
			<div class="tnf-about-us__stat">
				<strong>8 मई 2023</strong>
				<span><?php esc_html_e('Established', 'tnf-news-platform'); ?></span>
			</div>
			<div class="tnf-about-us__stat">
				<strong>आगरा</strong>
				<span><?php esc_html_e('Head office', 'tnf-news-platform'); ?></span>
			</div>
			<div class="tnf-about-us__stat">
				<strong>Digital + Print</strong>
				<span><?php esc_html_e('Multi-platform', 'tnf-news-platform'); ?></span>
			</div>
			<div class="tnf-about-us__stat">
				<strong>8</strong>
				<span><?php esc_html_e('Committees', 'tnf-news-platform'); ?></span>
			</div>
		</div>
	</div>

	<section class="tnf-about-us__section">
		<div class="tnf-about-us__shell">
			<div class="tnf-about-us__section-grid">
				<div class="tnf-about-us__section-head">
					<span class="tnf-about-us__section-label">01 — Introduction</span>
					<h2>संस्था का विजन और परिचय</h2>
				</div>
				<div class="tnf-about-us__prose">
					<p class="tnf-about-us__lead">TNF Today (Thoughts News Facts Today) महज़ एक मीडिया संस्थान नहीं, बल्कि पत्रकारिता की विश्वसनीयता को पुनर्जीवित करने का एक संकल्प है।</p>
					<p>8 मई 2023 को स्थापित यह कंपनी, डिजिटल युग में पत्रकारिता के उन मूल्यों को वापिस लाने के लिए समर्पित है, जहाँ <strong>'सत्य'</strong> और <strong>'तथ्य'</strong> ही सर्वोपरि हैं। हमारी नींव <strong>'विचार, समाचार और तथ्यों'</strong> के सामंजस्य पर टिकी है।</p>
					<p>हम मानते हैं कि पत्रकारिता का अर्थ केवल सूचना देना नहीं, बल्कि समाज के अंतिम व्यक्ति तक सही जानकारी पहुँचाना और राष्ट्र निर्माण में सक्रिय भूमिका निभाना है।</p>
				</div>
			</div>
		</div>
	</section>

	<section class="tnf-about-us__section tnf-about-us__section--alt">
		<div class="tnf-about-us__shell">
			<div class="tnf-about-us__section-grid tnf-about-us__section-grid--stack">
				<div class="tnf-about-us__section-head">
					<span class="tnf-about-us__section-label">02 — Leadership</span>
					<h2>नेतृत्व: एक वैचारिक यात्रा</h2>
				</div>
				<div>
					<div class="tnf-about-us__prose">
						<p>संस्थान का नेतृत्व आगरा के वरिष्ठ पत्रकार <strong>श्री धीरज शर्मा</strong> कर रहे हैं, जो अपनी निष्पक्षता, निडरता और कलम की ईमानदारी के लिए जाने जाते हैं। वे देश के प्रतिष्ठित मीडिया संस्थानों में प्रमुख जिम्मेदारियों का निर्वाह कर चुके हैं। कंपनी की निदेशक <strong>श्रीमती रूबी शर्मा</strong> हैं।</p>
					</div>
					<aside class="tnf-about-us__heritage">
						यह गौरवशाली यात्रा श्री धीरज शर्मा के पूज्य दादाजी स्वर्गीय पंडित श्री रामेश्वर प्रसाद शास्त्री जी, उनके देवतुल्य पिता श्री राशंकर शर्मा (रघु पंडित जी) और माताजी श्रीमती नीरज देवी की असीम अनुकम्पा, कृपा और तपोबल से पोषित है।
					</aside>
				</div>
			</div>
		</div>
	</section>

	<section class="tnf-about-us__section">
		<div class="tnf-about-us__shell">
			<div class="tnf-about-us__section-head">
				<span class="tnf-about-us__section-label">03 — Journey</span>
				<h2>TNF Today: डिजिटल से प्रिंट तक का सफर</h2>
			</div>
			<div class="tnf-about-us__split">
				<div class="tnf-about-us__split-item">
					<span class="tnf-about-us__split-tag">Digital</span>
					<p>TNF Today ने अपनी शुरुआत एक डिजिटल क्रांति के रूप में की, जहाँ हमारे सोशल मीडिया प्लेटफ़ॉर्म और पोर्टल पर करोड़ों दर्शकों का विश्वास हमें प्राप्त हुआ।</p>
				</div>
				<div class="tnf-about-us__split-item">
					<span class="tnf-about-us__split-tag">Print</span>
					<p>अपने इसी मिशन को विस्तार देते हुए <strong>'TNF Today दैनिक समाचार पत्र'</strong> का प्रकाशन एक ऐतिहासिक कदम है।</p>
				</div>
			</div>
		</div>
	</section>

	<section class="tnf-about-us__section tnf-about-us__section--alt">
		<div class="tnf-about-us__shell">
			<div class="tnf-about-us__section-head tnf-about-us__section-head--center">
				<span class="tnf-about-us__section-label">04 — Purpose</span>
				<h2>विजन और मिशन</h2>
			</div>
			<div class="tnf-about-us__cards">
				<article class="tnf-about-us__card">
					<span class="tnf-about-us__card-en">Vision</span>
					<h3>विजन</h3>
					<p>पत्रकारिता को बाज़ारी उत्पादों के दबाव से मुक्त कर, इसे <strong>'मिशनरी पत्रकारिता'</strong> के रूप में स्थापित करना।</p>
				</article>
				<article class="tnf-about-us__card">
					<span class="tnf-about-us__card-en">Mission</span>
					<h3>मिशन</h3>
					<p>सामाजिक सरोकार वाली खबरों को प्राथमिकता देना, आमजन की आवाज़ को नीति-निर्माताओं तक पहुँचाना और देश-भक्त व राष्ट्र निर्माण की व्यवस्थाओं को सुदृढ़ करना।</p>
				</article>
			</div>
		</div>
	</section>

	<section class="tnf-about-us__section">
		<div class="tnf-about-us__shell">
			<div class="tnf-about-us__section-head">
				<span class="tnf-about-us__section-label">05 — Milestone</span>
				<h2>ऐतिहासिक उद्घोषणा और वैचारिक महाकुंभ</h2>
			</div>
			<div class="tnf-about-us__prose">
				<p>13 जून 2026 को आयोजित <strong>'राष्ट्रीय उद्घोषणा समारोह एवं वैचारिक महाकुंभ'</strong> में हमने आधिकारिक रूप से 'TNF Today' के अखबारी मॉडल को राष्ट्र के समक्ष प्रस्तुत किया।</p>
				<p>स्वाधीनता पश्चात पत्रकारिता के द्विशताब्दी वर्ष (1826–2026) के इस पावन अवसर पर हमने संकल्प लिया है कि TNF Today <strong>मिशनरी पत्रकारिता</strong> का संवाहक बनेगा।</p>
			</div>
			<div class="tnf-about-us__callout">
				<strong>हमारा अखबार</strong> विज्ञापन की निर्भरता से मुक्त, जन-सहयोग आधारित और निर्भीक होगा।
			</div>
		</div>
	</section>

	<section class="tnf-about-us__section tnf-about-us__section--alt">
		<div class="tnf-about-us__shell tnf-about-us__shell--wide">
			<div class="tnf-about-us__section-head tnf-about-us__section-head--center">
				<span class="tnf-about-us__section-label">06 — Structure</span>
				<h2>संगठनात्मक ढांचा: 8 आधारभूत समितियाँ</h2>
				<p>संस्थान के सुचारू संचालन एवं वैचारिक स्पष्टता हेतु हमने 8 विशिष्ट समितियों का गठन किया है</p>
			</div>
			<div class="tnf-about-us__committees">
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">01</span>
					<div>
						<h3>TNF Today Vision and Mission Committee</h3>
						<p>संस्थान के दीर्घकालिक लक्ष्यों और मूल्यों का निर्धारण।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">02</span>
					<div>
						<h3>TNF Today Elders Committee</h3>
						<p>अनुभवी मार्गदर्शकों का समूह, जो संस्थान को दिशा प्रदान करता है।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">03</span>
					<div>
						<h3>TNF Today CORE Committee</h3>
						<p>दैनिक कार्यों और महत्वपूर्ण निर्णय लेने वाली कार्यकारी समिति।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">04</span>
					<div>
						<h3>TNF Today Editorial Board</h3>
						<p>समाचारों के चयन, भाषा की शुद्धता और वैचारिक स्पष्टता का उत्तरदायी बोर्ड।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">05</span>
					<div>
						<h3>TNF Today MARGDARSHAK Mandal</h3>
						<p>वरिष्ठ विचारकों का मंडल जो संस्थान के वैचारिक प्रवाह को आलोकित करता है।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">06</span>
					<div>
						<h3>TNF Today Board of Directors</h3>
						<p>नीतिगत निर्णयों और कंपनी के वैधानिक कार्यों का संचालन।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">07</span>
					<div>
						<h3>TNF Today Research Committee</h3>
						<p>प्रत्येक मुद्दे का गहन डेटा-ड्रिवेन विश्लेषण और तथ्यों की गहराई तक पड़ताल।</p>
					</div>
				</article>
				<article class="tnf-about-us__committee">
					<span class="tnf-about-us__committee-num">08</span>
					<div>
						<h3>TNF Today AUDIT Committee</h3>
						<p>संस्थान के कार्यों, नैतिकता और वित्तीय मानकों की पारदर्शिता की निगरानी।</p>
					</div>
				</article>
			</div>
		</div>
	</section>

	<section class="tnf-about-us__section">
		<div class="tnf-about-us__shell">
			<div class="tnf-about-us__section-head">
				<span class="tnf-about-us__section-label">07 — Achievements</span>
				<h2>हमारी उपलब्धियाँ और पहचान</h2>
			</div>

			<div class="tnf-about-us__achievements-grid">
				<div class="tnf-about-us__pledge">
					<h3 class="tnf-about-us__subhead">हमारा संकल्प</h3>
					<blockquote>
						TNF Today का उद्देश्य केवल सूचना साझा करना नहीं, बल्कि समाज में एक सकारात्मक वैचारिक परिवर्तन लाना है। हम आगरा से शुरू होकर पूरे राष्ट्र में 'सत्य के पथ पर, राष्ट्र के साथ' के अपने ध्येय को सार्थक करने के लिए प्रतिबद्ध हैं।
					</blockquote>
				</div>

				<div class="tnf-about-us__meta-row">
					<div class="tnf-about-us__meta-card">
						<span class="tnf-about-us__meta-label">संस्थापक / प्रधान संपादक</span>
						<p class="tnf-about-us__meta-value">धीरज शर्मा</p>
					</div>
					<div class="tnf-about-us__meta-card">
						<span class="tnf-about-us__meta-label">आधिकारिक वेबसाइट</span>
						<p class="tnf-about-us__meta-value"><a href="<?php echo esc_url($site_url); ?>">www.tnftoday.com</a></p>
					</div>
					<div class="tnf-about-us__meta-card">
						<span class="tnf-about-us__meta-label">मुख्य कार्यालय</span>
						<p class="tnf-about-us__meta-value">आगरा, उत्तर प्रदेश</p>
					</div>
				</div>
			</div>

			<div class="tnf-about-us__prose tnf-about-us__prose--wide">
				<p class="tnf-about-us__lead tnf-about-us__lead--spaced"><strong>वैश्विक पहुँच:</strong> TNF Today की खबरें न केवल आम जनमानस द्वारा सराही गई हैं, बल्कि देश के प्रमुख राजनेताओं, मुख्यमंत्रियों और राजनीतिक दलों के प्रमुखों द्वारा साझा की गई हैं।</p>
			</div>

			<div class="tnf-about-us__highlights">
				<article class="tnf-about-us__highlight">
					<span class="tnf-about-us__highlight-num">01</span>
					<h3>ऐतिहासिक साक्षात्कार</h3>
					<p>प्रधानमंत्री श्री नरेंद्र मोदी जी की पत्नी श्रीमती जशोदाबेन मोदी का विशेष साक्षात्कार लेकर हमने अपनी पत्रकारिता की विश्वसनीयता और पहुँच का प्रमाण दिया, जो वैश्विक स्तर पर वायरल हुआ।</p>
				</article>
				<article class="tnf-about-us__highlight">
					<span class="tnf-about-us__highlight-num">02</span>
					<h3>निष्पक्षता का प्रतिमान</h3>
					<p>हमने हमेशा उन मुद्दों को उठाया है जिन्हें अक्सर मुख्यधारा की मीडिया नज़रअंदाज़ कर देती है।</p>
				</article>
				<article class="tnf-about-us__highlight">
					<span class="tnf-about-us__highlight-num">03</span>
					<h3>मिशनरी निष्ठा</h3>
					<p>हमारा हर कर्मचारी एक 'मिशनरी' है, जो राष्ट्र-प्रेम के साथ अपने कर्तव्यों का पालन करता है।</p>
				</article>
			</div>
		</div>
	</section>
</div>
	<?php
	return (string) ob_get_clean();
}

add_action('plugins_loaded', 'tnf_register_about_us_page');
