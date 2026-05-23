/**
 * TNF homepage: horizontal rails — one-time scroll “peek” when a row is on screen
 * (scrollbars stay hidden via CSS). Skipped when prefers-reduced-motion is set.
 */
(function () {
	'use strict';

	var RAIL_SELECTOR =
		'.tnf-home-news .tnf-hero-side ul, ' +
		'.tnf-home-news .tnf-cat-grid, ' +
		'.tnf-home-news .tnf-recent-grid, ' +
		'.tnf-home-news .tnf-video-rail, ' +
		'.tnf-home-news .tnf-video-grid, ' +
		'.tnf-home-news .tnf-side-widget ul';

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function overflowX(el) {
		return el.scrollWidth - el.clientWidth;
	}

	function isRoughlyVisible(el) {
		var r = el.getBoundingClientRect();
		var vh = window.innerHeight || document.documentElement.clientHeight;
		return r.bottom > 8 && r.top < vh - 8;
	}

	function animateScrollLeft(el, from, to, durationMs, done) {
		var t0 = window.performance.now();
		function easeOut(u) {
			return 1 - (1 - u) * (1 - u);
		}
		function frame(now) {
			var u = Math.min(1, (now - t0) / durationMs);
			var eased = easeOut(u);
			el.scrollLeft = from + (to - from) * eased;
			if (u < 1) {
				window.requestAnimationFrame(frame);
			} else {
				el.scrollLeft = to;
				if (done) {
					done();
				}
			}
		}
		window.requestAnimationFrame(frame);
	}

	function init() {
		if (prefersReducedMotion()) {
			return;
		}

		var rails = document.querySelectorAll(RAIL_SELECTOR);
		if (!rails.length) {
			return;
		}

		var io = null;
		var ro = null;

		function stopWatching(el) {
			if (io) {
				io.unobserve(el);
			}
			if (ro) {
				ro.unobserve(el);
			}
		}

		function runPeek(el) {
			var maxScroll = overflowX(el);
			if (maxScroll < 10) {
				return false;
			}
			var peek = Math.min(80, Math.max(32, Math.floor(maxScroll * 0.32)));
			peek = Math.min(peek, maxScroll);
			el.scrollLeft = 0;
			animateScrollLeft(el, 0, peek, 240, function () {
				window.setTimeout(function () {
					animateScrollLeft(el, peek, 0, 300, null);
				}, 120);
			});
			return true;
		}

		function tryPeek(el) {
			if (el.getAttribute('data-tnf-scroll-hint') === '1') {
				return true;
			}
			if (!isRoughlyVisible(el)) {
				return false;
			}
			if (runPeek(el)) {
				el.setAttribute('data-tnf-scroll-hint', '1');
				stopWatching(el);
				return true;
			}
			return false;
		}

		function schedulePeek(el) {
			if (el.getAttribute('data-tnf-scroll-hint') === '1') {
				return;
			}
			if (el.getAttribute('data-tnf-scroll-scheduled') === '1') {
				return;
			}
			el.setAttribute('data-tnf-scroll-scheduled', '1');

			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					tryPeek(el);
					window.setTimeout(function () {
						tryPeek(el);
					}, 500);
				});
			});
		}

		if ('IntersectionObserver' in window) {
			io = new IntersectionObserver(
				function (entries) {
					for (var i = 0; i < entries.length; i++) {
						if (!entries[i].isIntersecting) {
							continue;
						}
						schedulePeek(entries[i].target);
					}
				},
				{ root: null, rootMargin: '0px 0px -6% 0px', threshold: 0 }
			);
		}

		if ('ResizeObserver' in window) {
			ro = new ResizeObserver(function (entries) {
				for (var k = 0; k < entries.length; k++) {
					var t = entries[k].target;
					if (t.getAttribute('data-tnf-scroll-hint') === '1') {
						stopWatching(t);
						continue;
					}
					if (t.getAttribute('data-tnf-scroll-scheduled') !== '1') {
						continue;
					}
					if (overflowX(t) >= 10 && isRoughlyVisible(t)) {
						tryPeek(t);
					}
				}
			});
		}

		for (var j = 0; j < rails.length; j++) {
			if (io) {
				io.observe(rails[j]);
			}
			if (ro) {
				ro.observe(rails[j]);
			}
		}

		if (!io) {
			window.addEventListener(
				'load',
				function () {
					for (var m = 0; m < rails.length; m++) {
						schedulePeek(rails[m]);
					}
				},
				{ once: true }
			);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
