(function () {
	'use strict';

	function initMobileMenu(root) {
		var toggle = root.querySelector('.tnf-nav-toggle');
		var menu = root.querySelector('.tnf-main-menu');
		if (!toggle || !menu) {
			return;
		}

		var closeMenu = function () {
			root.classList.remove('is-menu-open');
			toggle.setAttribute('aria-expanded', 'false');
		};

		var openMenu = function () {
			root.classList.add('is-menu-open');
			toggle.setAttribute('aria-expanded', 'true');
		};

		toggle.addEventListener('click', function () {
			if (root.classList.contains('is-menu-open')) {
				closeMenu();
				return;
			}
			openMenu();
		});

		menu.addEventListener('click', function (event) {
			var target = event.target;
			if (target instanceof HTMLElement && target.closest('a')) {
				closeMenu();
			}
		});

		document.addEventListener('click', function (event) {
			var target = event.target;
			if (!(target instanceof Node)) {
				return;
			}
			if (!root.contains(target)) {
				closeMenu();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeMenu();
			}
		});

		window.addEventListener('resize', function () {
			if (window.innerWidth > 900) {
				closeMenu();
			}
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
	});
})();
