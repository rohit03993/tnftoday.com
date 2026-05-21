(function () {
	'use strict';

	var cfg = typeof window.tnfMobileApp === 'object' ? window.tnfMobileApp : {};
	var Cap = window.Capacitor;
	var Plugins = Cap && Cap.Plugins ? Cap.Plugins : {};

	function plugin(name) {
		return Plugins[name] || null;
	}

	function runWhenIdle(fn, timeoutMs) {
		var t = timeoutMs || 2500;
		if (typeof window.requestIdleCallback === 'function') {
			window.requestIdleCallback(fn, { timeout: t });
			return;
		}
		window.setTimeout(fn, 80);
	}

	function hapticLight() {
		var Haptics = plugin('Haptics');
		if (Haptics && typeof Haptics.impact === 'function') {
			Haptics.impact({ style: 'LIGHT' }).catch(function () {});
		}
	}

	function initNativeChrome() {
		var StatusBar = plugin('StatusBar');
		if (StatusBar) {
			if (typeof StatusBar.setStyle === 'function') {
				StatusBar.setStyle({ style: 'DARK' }).catch(function () {});
			}
			if (typeof StatusBar.setBackgroundColor === 'function') {
				StatusBar.setBackgroundColor({ color: '#0f1320' }).catch(function () {});
			}
		}

		var SplashScreen = plugin('SplashScreen');
		if (SplashScreen && typeof SplashScreen.hide === 'function') {
			SplashScreen.hide().catch(function () {});
		}

		var App = plugin('App');
		if (App && typeof App.addListener === 'function') {
			App.addListener('appStateChange', function (state) {
				if (state && state.isActive) {
					checkNetwork(true);
				}
			});
			App.addListener('backButton', function () {
				if (window.history.length > 1) {
					window.history.back();
				} else if (cfg.homeUrl) {
					window.location.href = cfg.homeUrl;
				}
			});
		}
	}

	function initLazyImages() {
		if (!('IntersectionObserver' in window)) {
			return;
		}
		var seen = 0;
		var max = 48;
		var io = new IntersectionObserver(
			function (entries, observer) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					var img = entry.target;
					if (!(img instanceof HTMLImageElement)) {
						return;
					}
					if (!img.getAttribute('loading')) {
						img.loading = 'lazy';
					}
					if (!img.getAttribute('decoding')) {
						img.decoding = 'async';
					}
					observer.unobserve(img);
					seen += 1;
					if (seen >= max) {
						observer.disconnect();
					}
				});
			},
			{ rootMargin: '200px 0px', threshold: 0.01 }
		);

		document.querySelectorAll('img:not([loading])').forEach(function (img) {
			if (seen >= max) {
				return;
			}
			io.observe(img);
		});
	}

	function isExternalUrl(url) {
		try {
			var u = new URL(url, window.location.href);
			var host = window.location.hostname;
			return u.hostname !== host && u.protocol.indexOf('http') === 0;
		} catch (e) {
			return false;
		}
	}

	function handleExternalLinks() {
		document.addEventListener(
			'click',
			function (event) {
				var target = event.target;
				if (!(target instanceof Element)) {
					return;
				}
				var anchor = target.closest('a[href]');
				if (!anchor || anchor.getAttribute('target') === '_blank') {
					return;
				}
				var href = anchor.getAttribute('href');
				if (!href || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
					return;
				}
				if (!isExternalUrl(href)) {
					return;
				}
				event.preventDefault();
				hapticLight();
				var Browser = plugin('Browser');
				if (Browser && typeof Browser.open === 'function') {
					Browser.open({ url: href }).catch(function () {
						window.open(href, '_blank', 'noopener,noreferrer');
					});
				} else {
					window.open(href, '_blank', 'noopener,noreferrer');
				}
			},
			true
		);
	}

	function setOfflineVisible(show) {
		var el = document.getElementById('tnf-app-offline');
		if (!el) {
			return;
		}
		if (show) {
			el.removeAttribute('hidden');
		} else {
			el.setAttribute('hidden', '');
		}
	}

	function checkNetwork(silent) {
		var Network = plugin('Network');
		if (!Network || typeof Network.getStatus !== 'function') {
			if (!navigator.onLine) {
				setOfflineVisible(true);
			}
			return Promise.resolve();
		}
		return Network.getStatus()
			.then(function (status) {
				setOfflineVisible(!status.connected);
				if (!status.connected && !silent) {
					hapticLight();
				}
			})
			.catch(function () {
				if (!navigator.onLine) {
					setOfflineVisible(true);
				}
			});
	}

	function initNetwork() {
		window.addEventListener('online', function () {
			setOfflineVisible(false);
		});
		window.addEventListener('offline', function () {
			setOfflineVisible(true);
		});

		var Network = plugin('Network');
		if (Network && typeof Network.addListener === 'function') {
			Network.addListener('networkStatusChange', function (status) {
				setOfflineVisible(!status.connected);
			});
		}

		var retry = document.querySelector('[data-tnf-offline-retry]');
		if (retry) {
			retry.addEventListener('click', function () {
				hapticLight();
				checkNetwork(false).then(function () {
					if (navigator.onLine) {
						window.location.reload();
					}
				});
			});
		}

		checkNetwork(true);
	}

	function initBottomNav() {
		document.querySelectorAll('.tnf-app-bottom-nav__item[data-tnf-nav]').forEach(function (item) {
			item.addEventListener('click', function () {
				hapticLight();
			});
		});

		var menuBtn = document.querySelector('[data-tnf-app-menu]');
		if (menuBtn) {
			menuBtn.addEventListener('click', function () {
				hapticLight();
				var toggle = document.querySelector('.tnf-nav-toggle');
				if (toggle) {
					toggle.click();
				}
			});
		}
	}

	function initPullToRefresh() {
		if (!cfg.pullRefresh) {
			return;
		}
		var indicator = document.getElementById('tnf-app-refresh');
		var startY = 0;
		var pulling = false;
		var threshold = 72;

		document.addEventListener(
			'touchstart',
			function (e) {
				if (window.scrollY > 8) {
					return;
				}
				if (e.touches.length !== 1) {
					return;
				}
				startY = e.touches[0].clientY;
				pulling = true;
			},
			{ passive: true }
		);

		document.addEventListener(
			'touchmove',
			function (e) {
				if (!pulling || !indicator) {
					return;
				}
				var dy = e.touches[0].clientY - startY;
				if (dy > 20 && window.scrollY <= 8) {
					indicator.removeAttribute('hidden');
				}
			},
			{ passive: true }
		);

		document.addEventListener(
			'touchend',
			function (e) {
				if (!pulling) {
					return;
				}
				pulling = false;
				if (indicator) {
					indicator.setAttribute('hidden', '');
				}
				if (window.scrollY > 8) {
					return;
				}
				var dy = e.changedTouches[0].clientY - startY;
				if (dy >= threshold) {
					hapticLight();
					window.location.reload();
				}
			},
			{ passive: true }
		);
	}

	function initPushNotifications() {
		var Push = plugin('PushNotifications');
		if (!Push || typeof Push.requestPermissions !== 'function') {
			return;
		}
		Push.checkPermissions()
			.then(function (perm) {
				if (perm.receive === 'granted') {
					return Push.register();
				}
				return Push.requestPermissions().then(function (p) {
					if (p.receive === 'granted') {
						return Push.register();
					}
				});
			})
			.catch(function () {});

		if (typeof Push.addListener === 'function') {
			Push.addListener('pushNotificationActionPerformed', function (action) {
				var data = action && action.notification && action.notification.data;
				var url = data && (data.url || data.launchURL);
				if (url) {
					window.location.href = url;
				}
			});
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		if (!document.body.classList.contains('tnf-capacitor-app')) {
			return;
		}
		initNativeChrome();
		handleExternalLinks();
		initNetwork();
		initBottomNav();
		initPullToRefresh();
		initLazyImages();

		runWhenIdle(function () {
			initPushNotifications();
		}, 4000);
	});
})();
