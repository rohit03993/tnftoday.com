(function () {
	'use strict';

	function initMobileMenu(root) {
		var chrome = root.closest('.tnf-chrome-aaj');
		var toggle = root.querySelector('.tnf-nav-toggle, .tnf-chrome-head__menu-btn');
		var moreBtn = root.querySelector('[data-tnf-nav-more]');
		var drawer = chrome ? chrome.querySelector('#tnf-chrome-drawer') : null;
		var backdrop = chrome ? chrome.querySelector('[data-tnf-drawer-backdrop]') : null;
		var closeBtn = drawer ? drawer.querySelector('[data-tnf-drawer-close]') : null;

		if (!chrome || !drawer) {
			return;
		}

		var bodyScrollLock = '';

		function lockBodyScroll() {
			bodyScrollLock = document.body.style.overflow;
			document.body.style.overflow = 'hidden';
			document.body.classList.add('tnf-nav-drawer-open');
		}

		function unlockBodyScroll() {
			document.body.style.overflow = bodyScrollLock;
			document.body.classList.remove('tnf-nav-drawer-open');
		}

		function setDrawerOpen(open) {
			if (open) {
				chrome.classList.add('is-drawer-open');
				root.classList.add('is-menu-open');
				root.classList.remove('is-more-open');
				drawer.setAttribute('aria-hidden', 'false');
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'true');
				}
				if (moreBtn) {
					moreBtn.setAttribute('aria-expanded', 'true');
				}
				if (backdrop) {
					backdrop.hidden = false;
				}
				lockBodyScroll();
				return;
			}

			chrome.classList.remove('is-drawer-open');
			root.classList.remove('is-menu-open', 'is-more-open');
			drawer.setAttribute('aria-hidden', 'true');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
			if (moreBtn) {
				moreBtn.setAttribute('aria-expanded', 'false');
			}
			if (backdrop) {
				backdrop.hidden = true;
			}
			unlockBodyScroll();
		}

		var closeMenu = function () {
			setDrawerOpen(false);
		};

		var openMenu = function () {
			setDrawerOpen(true);
		};

		if (toggle) {
			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				if (chrome.classList.contains('is-drawer-open')) {
					closeMenu();
					return;
				}
				openMenu();
			});
		}

		if (moreBtn) {
			moreBtn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				if (chrome.classList.contains('is-drawer-open')) {
					closeMenu();
					return;
				}
				openMenu();
			});
		}

		if (backdrop) {
			backdrop.addEventListener('click', function () {
				closeMenu();
			});
		}

		if (closeBtn) {
			closeBtn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				closeMenu();
			});
		}

		drawer.addEventListener('click', function (event) {
			var target = event.target;
			if (target instanceof HTMLElement && target.closest('a')) {
				closeMenu();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && chrome.classList.contains('is-drawer-open')) {
				closeMenu();
			}
		});

		window.addEventListener('resize', function () {
			if (window.innerWidth > 900 && chrome.classList.contains('is-drawer-open')) {
				/* keep open on desktop resize */
			}
		});
	}

	function initTopicsScroll() {
		document.querySelectorAll('.tnf-chrome-topics').forEach(function (wrap) {
			var track = wrap.querySelector('[data-tnf-topics-track]');
			if (!track) {
				return;
			}
			wrap.querySelectorAll('[data-tnf-topics-scroll]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var dir = btn.getAttribute('data-tnf-topics-scroll');
					var delta = dir === 'prev' ? -180 : 180;
					track.scrollBy({ left: delta, behavior: 'smooth' });
				});
			});
		});
	}

	function initBottomNav() {
		document.querySelectorAll('[data-tnf-bottom-menu]').forEach(function (btn) {
			btn.addEventListener('click', function (event) {
				event.preventDefault();
				var chrome = document.querySelector('.tnf-chrome-aaj');
				if (!chrome) {
					return;
				}
				if (chrome.classList.contains('is-drawer-open')) {
					var closeBtn = chrome.querySelector('[data-tnf-drawer-close]');
					if (closeBtn) {
						closeBtn.click();
					}
					return;
				}
				var head = document.querySelector('.tnf-chrome-head.tnf-top-nav');
				var toggle = head ? head.querySelector('.tnf-nav-toggle, .tnf-chrome-head__menu-btn') : null;
				if (toggle) {
					toggle.click();
				}
			});
		});
	}

	function hideDuplicateHeaderChrome() {
		var nodes = document.querySelectorAll('.tnf-site-chrome.tnf-home-news');
		if (nodes.length < 2) {
			return;
		}
		for (var i = 1; i < nodes.length; i++) {
			var wrap = nodes[i].closest('.wp-block-template-part');
			var hide = wrap || nodes[i];
			hide.style.setProperty('display', 'none', 'important');
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		hideDuplicateHeaderChrome();
		document.querySelectorAll('.tnf-top-nav').forEach(initMobileMenu);
		initTopicsScroll();
		initBottomNav();
	});
})();
