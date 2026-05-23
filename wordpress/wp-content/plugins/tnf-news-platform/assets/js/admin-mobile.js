(function () {
	'use strict';

	function qs(id) {
		return document.getElementById(id);
	}

	function isMobileAdmin() {
		return (
			(document.body.classList.contains('tnf-admin-branded') ||
				document.body.classList.contains('tnf-admin-mobile-ready')) &&
			window.matchMedia('(max-width: 782px)').matches
		);
	}

	function fixListTables() {
		if (!window.matchMedia('(max-width: 782px)').matches) {
			return;
		}
		document.querySelectorAll('.wp-list-table').forEach(function (table) {
			table.classList.add('tnf-mob-list-table');
			var tfoot = table.querySelector('tfoot');
			if (tfoot) {
				tfoot.remove();
			}
		});
	}

	function setMenuOpen(open) {
		if (typeof window.tnfMobAdminToggleMenu === 'function') {
			window.tnfMobAdminToggleMenu(open);
			return;
		}
		document.body.classList.toggle('tnf-mob-menu-open', open);
		var wpbody = document.getElementById('wpbody');
		if (wpbody) {
			wpbody.classList.toggle('wp-responsive-open', open);
		}
		document.documentElement.classList.toggle('tnf-mob-menu-open', open);
		var wrap = document.getElementById('adminmenuwrap');
		if (wrap) {
			wrap.style.display = 'block';
		}
		var btn = qs('tnf-mob-admin-menu-btn');
		var backdrop = qs('tnf-mob-admin-backdrop');
		if (btn) {
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
		if (backdrop) {
			if (open) {
				backdrop.removeAttribute('hidden');
			} else {
				backdrop.setAttribute('hidden', '');
			}
		}
	}

	function removeWpMobileClutter() {
		if (!window.matchMedia('(max-width: 782px)').matches) {
			return;
		}
		var ids = ['screen-meta-links', 'screen-meta', 'wp-responsive-toggle'];
		ids.forEach(function (id) {
			var el = document.getElementById(id);
			if (el && el.remove) {
				el.remove();
			}
		});
		document.querySelectorAll('.update-nag, .notice.update-nag').forEach(function (el) {
			if (el && el.remove) {
				el.remove();
			}
		});
	}

	function watchLateWpChrome() {
		if (!window.MutationObserver || !window.matchMedia('(max-width: 782px)').matches) {
			return;
		}
		var obs = new MutationObserver(function () {
			removeWpMobileClutter();
			fixListTables();
		});
		obs.observe(document.body, { childList: true, subtree: true });
		window.setTimeout(function () {
			obs.disconnect();
		}, 8000);
	}

	function initMobileAdmin() {
		if (
			!document.body.classList.contains('tnf-admin-branded') &&
			!document.body.classList.contains('tnf-admin-mobile-ready')
		) {
			return;
		}

		removeWpMobileClutter();
		fixListTables();
		watchLateWpChrome();

		var menuBtn = qs('tnf-mob-admin-menu-btn');
		var moreBtn = qs('tnf-mob-admin-tabs-menu');
		var backdrop = qs('tnf-mob-admin-backdrop');
		var wpToggle = document.getElementById('wp-responsive-toggle');

		if (wpToggle) {
			wpToggle.setAttribute('hidden', '');
			wpToggle.setAttribute('aria-hidden', 'true');
		}

		if (menuBtn) {
			menuBtn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				if (!isMobileAdmin()) {
					return;
				}
				setMenuOpen(!document.body.classList.contains('tnf-mob-menu-open'));
			});
		}

		if (moreBtn) {
			moreBtn.addEventListener('click', function (e) {
				e.preventDefault();
				if (!isMobileAdmin()) {
					return;
				}
				setMenuOpen(true);
			});
		}

		if (backdrop) {
			backdrop.addEventListener('click', function () {
				setMenuOpen(false);
			});
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				setMenuOpen(false);
			}
		});

		window.addEventListener('resize', function () {
			if (!window.matchMedia('(max-width: 782px)').matches) {
				setMenuOpen(false);
			} else {
				removeWpMobileClutter();
				fixListTables();
			}
		});

		/* Close menu after tapping a sidebar link */
		var menuwrap = document.getElementById('adminmenuwrap');
		if (menuwrap) {
			menuwrap.addEventListener('click', function (e) {
				var link = e.target.closest('a');
				if (link && link.getAttribute('href') && link.getAttribute('href') !== '#') {
					setMenuOpen(false);
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initMobileAdmin);
	} else {
		initMobileAdmin();
	}
})();
