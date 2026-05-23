(function () {
	'use strict';

	function chromeHeight() {
		var top = 0;
		var bottom = 0;
		var stack = document.querySelector('.tnf-chrome-stack');
		if (stack) {
			top += stack.getBoundingClientRect().height;
		}
		var appNav = document.querySelector('.tnf-app-bottom-nav');
		var webNav = document.querySelector('.tnf-chrome-bottom-nav');
		if (appNav) {
			bottom += appNav.getBoundingClientRect().height;
		} else if (webNav) {
			bottom += webNav.getBoundingClientRect().height;
		}
		var title = document.querySelector('.single-tnf_video .wp-block-post-title, .single-tnf_video .entry-title');
		if (title) {
			top += title.getBoundingClientRect().height + 12;
		}
		return top + bottom + 28;
	}

	function fitShortsFrames() {
		var frames = document.querySelectorAll('.tnf-shorts-player__frame');
		if (!frames.length) {
			return;
		}

		var pad = 16;
		var maxW = Math.min(window.innerWidth - pad * 2, 400);
		var maxH = Math.max(280, window.innerHeight - chromeHeight());

		frames.forEach(function (frame) {
			var w = maxW;
			var h = (w * 16) / 9;
			if (h > maxH) {
				h = maxH;
				w = (h * 9) / 16;
			}

			frame.style.width = Math.round(w) + 'px';
			frame.style.height = Math.round(h) + 'px';
			frame.style.aspectRatio = 'auto';
			frame.style.maxHeight = 'none';
		});
	}

	var timer = null;
	function scheduleFit() {
		if (timer) {
			window.clearTimeout(timer);
		}
		timer = window.setTimeout(fitShortsFrames, 60);
	}

	function boot() {
		if (!document.querySelector('.tnf-shorts-player__frame')) {
			return;
		}
		fitShortsFrames();
		window.addEventListener('resize', scheduleFit);
		window.addEventListener('orientationchange', scheduleFit);
		window.addEventListener('pageshow', scheduleFit);
		window.addEventListener('load', scheduleFit);
		window.setTimeout(fitShortsFrames, 350);
		window.setTimeout(fitShortsFrames, 1200);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
