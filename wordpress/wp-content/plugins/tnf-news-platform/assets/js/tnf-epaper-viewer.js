(function () {
	'use strict';

	function parsePages() {
		var el = document.getElementById('tnf-epaper-pages');
		if (!el || !el.textContent) {
			return null;
		}
		try {
			var pages = JSON.parse(el.textContent);
			return Array.isArray(pages) && pages.length ? pages : null;
		} catch (e) {
			return null;
		}
	}

	function buildPageOrder(pages) {
		return pages
			.map(function (r) {
				return r.page;
			})
			.sort(function (a, b) {
				return a - b;
			});
	}

	function urlForPage(pages, pnum) {
		for (var i = 0; i < pages.length; i++) {
			if (pages[i].page === pnum) {
				return pages[i].url;
			}
		}
		return '';
	}

	function addQueryArg(base, key, val) {
		var u;
		try {
			u = new URL(base, window.location.origin);
		} catch (e) {
			return base;
		}
		u.searchParams.set(key, String(val));
		return u.toString();
	}

	function updateShareBar(bar, shareUrl, title) {
		if (!bar) {
			return;
		}
		var encUrl = encodeURIComponent(shareUrl);
		var encTitle = encodeURIComponent(title);
		bar.setAttribute('data-share-base', shareUrl);
		bar.setAttribute('data-share-title', title);

		var wa = bar.querySelector('.is-wa');
		if (wa) {
			wa.href = 'https://wa.me/?text=' + encTitle + '%20' + encUrl;
		}
		var fb = bar.querySelector('.is-fb');
		if (fb) {
			fb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encUrl;
		}
		var x = bar.querySelector('.is-x');
		if (x) {
			x.href = 'https://twitter.com/intent/tweet?url=' + encUrl + '&text=' + encTitle;
		}
		var li = bar.querySelector('.is-li');
		if (li) {
			li.href = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encUrl;
		}
		var copy = bar.querySelector('.is-copy');
		if (copy) {
			copy.setAttribute('data-epaper-copy', shareUrl);
		}
	}

	function bindCopy(bar) {
		var copy = bar && bar.querySelector('.tnf-epaper-share__btn.is-copy');
		if (!copy || copy.dataset.tnfCopyBound) {
			return;
		}
		copy.dataset.tnfCopyBound = '1';
		copy.addEventListener('click', function () {
			var u = copy.getAttribute('data-epaper-copy') || '';
			if (!u) {
				return;
			}
			var orig = copy.textContent;
			function done() {
				copy.textContent = orig;
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(u).then(function () {
					copy.textContent =
						typeof window.tnfEpaperL10n !== 'undefined' && window.tnfEpaperL10n.copied
							? window.tnfEpaperL10n.copied
							: 'Copied!';
					setTimeout(done, 1800);
				});
			}
		});
	}

	function parseClipFromQuery() {
		var params = new URLSearchParams(window.location.search);
		if (params.get('tnf_clip') !== '1') {
			return null;
		}
		var page = parseInt(params.get('tnf_pg'), 10);
		var x = parseFloat(params.get('tnf_cx'));
		var y = parseFloat(params.get('tnf_cy'));
		var w = parseFloat(params.get('tnf_cw'));
		var h = parseFloat(params.get('tnf_ch'));
		if (isNaN(page) || isNaN(x) || isNaN(y) || isNaN(w) || isNaN(h)) {
			return null;
		}
		if (w <= 0 || h <= 0) {
			return null;
		}
		return {
			page: page,
			x: Math.max(0, Math.min(1, x)),
			y: Math.max(0, Math.min(1, y)),
			w: Math.max(0.02, Math.min(1, w)),
			h: Math.max(0.02, Math.min(1, h))
		};
	}

	function clearClipBox(figure) {
		if (!figure) {
			return;
		}
		var existing = figure.querySelector('.tnf-epaper__clip-box');
		if (existing) {
			existing.remove();
		}
	}

	function drawClipBox(figure, media, clip) {
		if (!figure || !media || !clip) {
			return;
		}
		var figRect = figure.getBoundingClientRect();
		var mediaRect = media.getBoundingClientRect();
		if (!figRect.width || !figRect.height || !mediaRect.width || !mediaRect.height) {
			return;
		}
		clearClipBox(figure);
		var box = document.createElement('div');
		box.className = 'tnf-epaper__clip-box';
		box.style.left = String(mediaRect.left - figRect.left + mediaRect.width * clip.x) + 'px';
		box.style.top = String(mediaRect.top - figRect.top + mediaRect.height * clip.y) + 'px';
		box.style.width = String(mediaRect.width * clip.w) + 'px';
		box.style.height = String(mediaRect.height * clip.h) + 'px';
		figure.appendChild(box);
	}

	function buildClipUrl(permalink, pageNum, clip) {
		var out;
		try {
			out = new URL(permalink, window.location.origin);
		} catch (e) {
			return addQueryArg(permalink, 'tnf_pg', pageNum);
		}
		out.searchParams.set('tnf_pg', String(pageNum));
		out.searchParams.set('tnf_clip', '1');
		out.searchParams.set('tnf_cx', clip.x.toFixed(4));
		out.searchParams.set('tnf_cy', clip.y.toFixed(4));
		out.searchParams.set('tnf_cw', clip.w.toFixed(4));
		out.searchParams.set('tnf_ch', clip.h.toFixed(4));
		return out.toString();
	}

	function clipCanvasFromImage(img, clip) {
		if (!img || !img.naturalWidth || !img.naturalHeight) {
			return null;
		}
		var sx = Math.round(img.naturalWidth * clip.x);
		var sy = Math.round(img.naturalHeight * clip.y);
		var sw = Math.max(1, Math.round(img.naturalWidth * clip.w));
		var sh = Math.max(1, Math.round(img.naturalHeight * clip.h));
		var out = document.createElement('canvas');
		out.width = sw;
		out.height = sh;
		var ctx = out.getContext('2d');
		if (!ctx) {
			return null;
		}
		ctx.drawImage(img, sx, sy, sw, sh, 0, 0, sw, sh);
		return out;
	}

	function clipCanvasFromCanvas(canvas, clip) {
		if (!canvas || !canvas.width || !canvas.height) {
			return null;
		}
		var sx = Math.round(canvas.width * clip.x);
		var sy = Math.round(canvas.height * clip.y);
		var sw = Math.max(1, Math.round(canvas.width * clip.w));
		var sh = Math.max(1, Math.round(canvas.height * clip.h));
		var out = document.createElement('canvas');
		out.width = sw;
		out.height = sh;
		var ctx = out.getContext('2d');
		if (!ctx) {
			return null;
		}
		ctx.drawImage(canvas, sx, sy, sw, sh, 0, 0, sw, sh);
		return out;
	}

	function mountClipOnlyView(root, clipCanvas, titleText, shareUrl) {
		if (!root || !clipCanvas) {
			return;
		}
		var imgUrl = clipCanvas.toDataURL('image/png');
		var html = '';
		html += '<div class="tnf-epaper-clip-only">';
		html += '<header class="tnf-epaper-clip-only__head">';
		html += '<h1 class="tnf-epaper-clip-only__title">' + String(titleText || document.title) + '</h1>';
		html += '</header>';
		html += '<figure class="tnf-epaper-clip-only__figure"><img class="tnf-epaper-clip-only__img" alt="" src="' + imgUrl + '" /></figure>';
		html += '<div class="tnf-epaper-clip-only__actions">';
		html += '<button type="button" class="tnf-epaper-share__btn is-copy" data-epaper-copy="' + String(shareUrl || window.location.href) + '">Copy link</button>';
		html += '</div>';
		html += '</div>';
		root.innerHTML = html;
		var copy = root.querySelector('.tnf-epaper-share__btn.is-copy');
		if (copy) {
			copy.addEventListener('click', function () {
				var u = copy.getAttribute('data-epaper-copy') || '';
				if (!u) {
					return;
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(u).then(function () {
						copy.textContent = 'Copied!';
						setTimeout(function () {
							copy.textContent = 'Copy link';
						}, 1400);
					});
				}
			});
		}
	}

	function openClipShareDialog(clipUrl, titleText) {
		var existing = document.querySelector('.tnf-clip-share-modal');
		if (existing) {
			existing.remove();
		}
		var encUrl = encodeURIComponent(clipUrl);
		var encTitle = encodeURIComponent(titleText || document.title);
		var waIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19.1 4.9A9.94 9.94 0 0 0 12.05 2C6.56 2 2.1 6.46 2.1 11.95c0 1.76.46 3.49 1.33 5.01L2 22l5.19-1.36a9.89 9.89 0 0 0 4.84 1.24h.01c5.49 0 9.95-4.46 9.95-9.95a9.9 9.9 0 0 0-2.9-7.03Zm-7.06 15.3a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.08.8.83-3-.2-.31a8.2 8.2 0 0 1-1.26-4.42c0-4.55 3.7-8.25 8.26-8.25a8.2 8.2 0 0 1 5.84 2.42 8.2 8.2 0 0 1 2.41 5.84c0 4.55-3.7 8.25-8.25 8.25Zm4.52-6.16c-.25-.13-1.47-.73-1.7-.81-.23-.09-.4-.13-.56.12-.16.24-.64.81-.78.98-.14.16-.28.18-.53.06-.25-.13-1.05-.39-2-1.25-.74-.66-1.24-1.47-1.39-1.72-.14-.24-.02-.37.11-.5.11-.11.25-.28.37-.42.12-.14.16-.24.24-.41.08-.16.04-.3-.02-.42-.07-.12-.56-1.36-.77-1.86-.2-.47-.4-.41-.56-.42h-.48c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2 0 1.18.86 2.32.98 2.48.12.16 1.68 2.56 4.06 3.59.57.24 1.01.38 1.36.49.57.18 1.08.15 1.49.09.45-.07 1.47-.6 1.68-1.17.2-.58.2-1.07.14-1.17-.06-.11-.22-.17-.47-.29Z"/></svg>';
		var fbIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.5-3.88 3.78-3.88 1.09 0 2.23.2 2.23.2v2.45H15.2c-1.21 0-1.59.75-1.59 1.52V12h2.7l-.43 2.89h-2.27v6.99A10 10 0 0 0 22 12Z"/></svg>';
		var xIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M18.9 2H22l-6.77 7.74L23.2 22h-6.24l-4.9-6.44L6.4 22H3.3l7.24-8.28L.8 2h6.4l4.43 5.85L18.9 2Zm-1.1 18h1.73L6.26 3.9H4.4L17.8 20Z"/></svg>';
		var liIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.94 8.5H3.56V20h3.38V8.5ZM5.25 3a1.96 1.96 0 1 0 0 3.91A1.96 1.96 0 0 0 5.25 3ZM20.44 13.4c0-3.05-1.63-4.9-4.25-4.9-1.96 0-2.84 1.08-3.33 1.84V8.5H9.5V20h3.36v-5.69c0-1.5.29-2.95 2.14-2.95 1.82 0 1.85 1.7 1.85 3.05V20h3.37v-6.6Z"/></svg>';
		var html = '';
		html += '<div class="tnf-clip-share-modal" role="dialog" aria-modal="true" aria-label="Share clip">';
		html += '<div class="tnf-clip-share-modal__card">';
		html += '<div class="tnf-clip-share-modal__head"><strong>Share clip</strong><button type="button" class="tnf-clip-share-modal__close" aria-label="Close">x</button></div>';
		html += '<div class="tnf-clip-share-modal__actions">';
		html += '<a class="tnf-epaper-share__btn is-wa tnf-epaper-share__btn--icon" aria-label="WhatsApp" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=' + encTitle + '%20' + encUrl + '">' + waIcon + '</a>';
		html += '<a class="tnf-epaper-share__btn is-fb tnf-epaper-share__btn--icon" aria-label="Facebook" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u=' + encUrl + '">' + fbIcon + '</a>';
		html += '<a class="tnf-epaper-share__btn is-x tnf-epaper-share__btn--icon" aria-label="X" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?url=' + encUrl + '&text=' + encTitle + '">' + xIcon + '</a>';
		html += '<a class="tnf-epaper-share__btn is-li tnf-epaper-share__btn--icon" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer" href="https://www.linkedin.com/sharing/share-offsite/?url=' + encUrl + '">' + liIcon + '</a>';
		html += '<a class="tnf-epaper-share__btn is-open" target="_blank" rel="noopener noreferrer" href="' + clipUrl + '">Open</a>';
		html += '<button type="button" class="tnf-epaper-share__btn is-copy" data-clip-copy="' + clipUrl + '">Copy link</button>';
		html += '</div></div></div>';
		document.body.insertAdjacentHTML('beforeend', html);

		var modal = document.querySelector('.tnf-clip-share-modal');
		if (!modal) {
			return;
		}
		var closeBtn = modal.querySelector('.tnf-clip-share-modal__close');
		var copyBtn = modal.querySelector('[data-clip-copy]');
		function closeModal() {
			modal.remove();
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}
		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				closeModal();
			}
		});
		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var u = copyBtn.getAttribute('data-clip-copy') || '';
				if (!u) {
					return;
				}
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(u).then(function () {
						copyBtn.textContent = 'Copied!';
						setTimeout(function () {
							copyBtn.textContent = 'Copy link';
						}, 1500);
					});
				}
			});
		}
	}

	function initEpaper() {
		var pages = parsePages();
		var root = document.querySelector('[data-tnf-epaper]');
		if (!pages || !root) {
			return;
		}

		var permalink = root.getAttribute('data-tnf-permalink') || window.location.href.split('?')[0];
		var order = buildPageOrder(pages);
		var title = document.querySelector('.tnf-epaper__title');
		var titleText = title ? title.textContent.trim() : document.title;

		var img = root.querySelector('[data-tnf-epaper-main]');
		var figure = root.querySelector('.tnf-epaper__figure');
		var select = root.querySelector('.tnf-epaper__select');
		var thumbs = root.querySelectorAll('.tnf-epaper__thumb');
		var numberPager = root.querySelector('[data-tnf-number-pager]');
		var curEl = root.querySelector('[data-tnf-epaper-current]');
		var totalEl = root.querySelector('[data-tnf-epaper-total]');
		var curMobileEl = root.querySelector('[data-tnf-epaper-current-mobile]');
		var totalMobileEl = root.querySelector('[data-tnf-epaper-total-mobile]');
		var statusEl = root.querySelector('[data-tnf-page-status]');
		var shareBar = root.querySelector('.tnf-epaper-share');
		var zoomValueEl = root.querySelector('[data-tnf-zoom-value]');
		var zoom = 1;
		var clipFromQuery = parseClipFromQuery();
		var clipMode = false;
		var clipOnlyMode = clipFromQuery !== null;
		var clipButton = shareBar ? shareBar.querySelector('.tnf-epaper-share__btn.is-clip') : null;

		var params = new URLSearchParams(window.location.search);
		var qp = parseInt(params.get('tnf_pg'), 10);
		var current = order.indexOf(qp) >= 0 ? qp : order[0];

		function renderNumberPager() {
			if (!numberPager) {
				return;
			}
			var currentIndex = order.indexOf(current);
			var currentPos = currentIndex + 1;
			var total = order.length;
			var windowSize = 5;
			var start = Math.max(1, currentPos - Math.floor(windowSize / 2));
			var end = Math.min(total, start + windowSize - 1);
			if (end - start + 1 < windowSize) {
				start = Math.max(1, end - windowSize + 1);
			}

			var html = '';
			if (currentPos > 1) {
				html += '<button type="button" class="tnf-epaper__num-btn is-arrow" data-tnf-jump="' + String(order[currentPos - 2]) + '" aria-label="Previous page">&#8249;</button>';
			}
			for (var i = start; i <= end; i++) {
				var p = order[i - 1];
				var active = p === current ? ' is-active' : '';
				var aria = p === current ? ' aria-current="page"' : '';
				html += '<button type="button" class="tnf-epaper__num-btn' + active + '" data-tnf-jump="' + String(p) + '"' + aria + '>' + String(i) + '</button>';
			}
			if (currentPos < total) {
				html += '<button type="button" class="tnf-epaper__num-btn is-arrow" data-tnf-jump="' + String(order[currentPos]) + '" aria-label="Next page">&#8250;</button>';
			}
			numberPager.innerHTML = html;
			numberPager.querySelectorAll('[data-tnf-jump]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					setPage(parseInt(btn.getAttribute('data-tnf-jump'), 10));
				});
			});
		}

		function syncThumbState() {
			thumbs.forEach(function (btn) {
				var p = parseInt(btn.getAttribute('data-tnf-page'), 10);
				var on = p === current;
				btn.classList.toggle('is-active', on);
				if (on) {
					btn.setAttribute('aria-current', 'page');
				} else {
					btn.removeAttribute('aria-current');
				}
			});
			renderNumberPager();
		}

		function applyZoom() {
			if (!img) {
				return;
			}
			img.style.transform = 'scale(' + zoom + ')';
			img.style.transformOrigin = 'center top';
			if (zoomValueEl) {
				zoomValueEl.textContent = String(Math.round(zoom * 100)) + '%';
			}
		}

		function setPage(pnum) {
			if (order.indexOf(pnum) < 0) {
				return;
			}
			current = pnum;
			var src = urlForPage(pages, pnum);
			if (img && src) {
				img.src = src;
			}
			if (select) {
				select.value = String(pnum);
			}
			if (curEl) {
				curEl.textContent = String(order.indexOf(pnum) + 1);
			}
			if (curMobileEl) {
				curMobileEl.textContent = String(order.indexOf(pnum) + 1);
			}
			if (statusEl) {
				statusEl.textContent = 'Page ' + String(pnum) + ' of ' + String(order.length);
			}
			syncThumbState();

			var shareUrl = addQueryArg(permalink, 'tnf_pg', pnum);
			updateShareBar(shareBar, shareUrl, titleText);
			if (clipFromQuery && clipFromQuery.page === pnum) {
				drawClipBox(figure, img, clipFromQuery);
				if (clipOnlyMode) {
					var doRender = function () {
						var outCanvas = clipCanvasFromImage(img, clipFromQuery);
						if (outCanvas) {
							mountClipOnlyView(root, outCanvas, titleText, window.location.href);
						}
					};
					if (img.complete && img.naturalWidth > 0) {
						doRender();
					} else {
						img.addEventListener('load', doRender, { once: true });
					}
				}
			} else {
				clearClipBox(figure);
			}

			try {
				var clean = new URL(permalink, window.location.href);
				clean.searchParams.set('tnf_pg', String(pnum));
				var path = clean.pathname + clean.search + clean.hash;
				window.history.replaceState({}, '', path);
			} catch (e2) {
				/* keep address bar unchanged if URL parsing fails */
			}
		}

		function setupClipSelection() {
			if (!clipButton || !figure || !img) {
				return;
			}
			var startX = 0;
			var startY = 0;
			var dragging = false;
			var draft = null;

			function pointFromEvent(event) {
				var source = event.touches && event.touches[0] ? event.touches[0] : event;
				return { x: source.clientX, y: source.clientY };
			}

			function renderDraft(x1, y1, x2, y2) {
				var figRect = figure.getBoundingClientRect();
				var left = Math.min(x1, x2) - figRect.left;
				var top = Math.min(y1, y2) - figRect.top;
				var width = Math.abs(x2 - x1);
				var height = Math.abs(y2 - y1);
				if (!draft) {
					draft = document.createElement('div');
					draft.className = 'tnf-epaper__clip-box is-draft';
					figure.appendChild(draft);
				}
				draft.style.left = String(left) + 'px';
				draft.style.top = String(top) + 'px';
				draft.style.width = String(width) + 'px';
				draft.style.height = String(height) + 'px';
			}

			function finishSelection(endX, endY) {
				if (!dragging) {
					return;
				}
				dragging = false;
				if (draft) {
					draft.remove();
					draft = null;
				}
				var mediaRect = img.getBoundingClientRect();
				var x1 = Math.max(mediaRect.left, Math.min(mediaRect.right, Math.min(startX, endX)));
				var y1 = Math.max(mediaRect.top, Math.min(mediaRect.bottom, Math.min(startY, endY)));
				var x2 = Math.max(mediaRect.left, Math.min(mediaRect.right, Math.max(startX, endX)));
				var y2 = Math.max(mediaRect.top, Math.min(mediaRect.bottom, Math.max(startY, endY)));
				var width = x2 - x1;
				var height = y2 - y1;
				if (width < 16 || height < 16) {
					return;
				}
				var clip = {
					x: (x1 - mediaRect.left) / mediaRect.width,
					y: (y1 - mediaRect.top) / mediaRect.height,
					w: width / mediaRect.width,
					h: height / mediaRect.height
				};
				drawClipBox(figure, img, clip);
				var clipUrl = buildClipUrl(permalink, current, clip);
				clipFromQuery = { page: current, x: clip.x, y: clip.y, w: clip.w, h: clip.h };
				updateShareBar(shareBar, clipUrl, titleText);
				bindCopy(shareBar);
				var copy = shareBar ? shareBar.querySelector('.tnf-epaper-share__btn.is-copy') : null;
				if (copy) {
					copy.setAttribute('data-epaper-copy', clipUrl);
					copy.textContent = 'Copy link';
				}
				openClipShareDialog(clipUrl, titleText);
			}

			clipButton.addEventListener('click', function () {
				clipMode = !clipMode;
				clipButton.classList.toggle('is-active', clipMode);
				clipButton.textContent = clipMode ? 'Cancel clip' : 'Clip';
				figure.classList.toggle('is-clip-mode', clipMode);
			});

			figure.addEventListener('mousedown', function (event) {
				if (!clipMode) {
					return;
				}
				var pt = pointFromEvent(event);
				dragging = true;
				startX = pt.x;
				startY = pt.y;
				event.preventDefault();
			});
			figure.addEventListener('mousemove', function (event) {
				if (!clipMode || !dragging) {
					return;
				}
				var pt = pointFromEvent(event);
				renderDraft(startX, startY, pt.x, pt.y);
			});
			figure.addEventListener('mouseup', function (event) {
				if (!clipMode) {
					return;
				}
				var pt = pointFromEvent(event);
				finishSelection(pt.x, pt.y);
			});
			figure.addEventListener('touchstart', function (event) {
				if (!clipMode) {
					return;
				}
				var pt = pointFromEvent(event);
				dragging = true;
				startX = pt.x;
				startY = pt.y;
				event.preventDefault();
			}, { passive: false });
			figure.addEventListener('touchmove', function (event) {
				if (!clipMode || !dragging) {
					return;
				}
				var pt = pointFromEvent(event);
				renderDraft(startX, startY, pt.x, pt.y);
				event.preventDefault();
			}, { passive: false });
			figure.addEventListener('touchend', function (event) {
				if (!clipMode) {
					return;
				}
				var touch = event.changedTouches && event.changedTouches[0] ? event.changedTouches[0] : null;
				if (touch) {
					finishSelection(touch.clientX, touch.clientY);
				}
				event.preventDefault();
			}, { passive: false });
		}

		if (totalEl) {
			totalEl.textContent = String(order.length);
		}
		if (totalMobileEl) {
			totalMobileEl.textContent = String(order.length);
		}
		if (select) {
			select.addEventListener('change', function () {
				setPage(parseInt(select.value, 10));
			});
		}

		thumbs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				setPage(parseInt(btn.getAttribute('data-tnf-page'), 10));
			});
		});

		function step(delta) {
			var i = order.indexOf(current) + delta;
			if (i < 0) {
				i = 0;
			}
			if (i >= order.length) {
				i = order.length - 1;
			}
			setPage(order[i]);
		}

		root.querySelectorAll('[data-tnf-epaper-prev],[data-tnf-epaper-prev-mobile]').forEach(function (b) {
			b.addEventListener('click', function () {
				step(-1);
			});
		});
		root.querySelectorAll('[data-tnf-epaper-next],[data-tnf-epaper-next-mobile]').forEach(function (b) {
			b.addEventListener('click', function () {
				step(1);
			});
		});

		root.querySelectorAll('[data-tnf-zoom-in]').forEach(function (b) {
			b.addEventListener('click', function () {
				zoom = Math.min(2.4, +(zoom + 0.1).toFixed(2));
				applyZoom();
			});
		});
		root.querySelectorAll('[data-tnf-zoom-out]').forEach(function (b) {
			b.addEventListener('click', function () {
				zoom = Math.max(0.6, +(zoom - 0.1).toFixed(2));
				applyZoom();
			});
		});
		root.querySelectorAll('[data-tnf-zoom-reset]').forEach(function (b) {
			b.addEventListener('click', function () {
				zoom = 1;
				applyZoom();
			});
		});

		document.addEventListener('keydown', function (e) {
			if (!root.contains(document.activeElement) && document.activeElement !== document.body) {
				return;
			}
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				step(-1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				step(1);
			} else if (e.key === '+' || e.key === '=') {
				e.preventDefault();
				zoom = Math.min(2.4, +(zoom + 0.1).toFixed(2));
				applyZoom();
			} else if (e.key === '-') {
				e.preventDefault();
				zoom = Math.max(0.6, +(zoom - 0.1).toFixed(2));
				applyZoom();
			} else if (e.key === '0') {
				e.preventDefault();
				zoom = 1;
				applyZoom();
			}
		});

		bindCopy(shareBar);
		setPage(current);
		applyZoom();
		setupClipSelection();
	}

	function initEmbedShareOnly() {
		var panel = document.querySelector('.tnf-pdf-report-panel--embed-only');
		if (!panel) {
			return;
		}
		var bar = panel.querySelector('.tnf-epaper-share');
		if (!bar) {
			return;
		}
		var shareUrl = window.location.href.split('#')[0];
		var title = document.querySelector('h1') ? document.querySelector('h1').textContent.trim() : document.title;
		updateShareBar(bar, shareUrl, title);
		bindCopy(bar);
	}

	function initPdfjsFallback() {
		var root = document.querySelector('[data-tnf-pdfjs]');
		if (!root) {
			return;
		}
		var pdfUrl = root.getAttribute('data-tnf-pdf-url') || '';
		if (!pdfUrl || !window.pdfjsLib) {
			return;
		}
		if (window.tnfEpaperL10n && window.tnfEpaperL10n.pdfjsWorkerSrc) {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = window.tnfEpaperL10n.pdfjsWorkerSrc;
		}

		var canvas = root.querySelector('[data-tnf-pdfjs-canvas]');
		var figure = root.querySelector('.tnf-epaper__figure--pdfjs');
		var thumbsWrap = root.querySelector('[data-tnf-pdfjs-thumbs]');
		var statusEl = root.querySelector('[data-tnf-pdfjs-status]');
		var curEl = root.querySelector('[data-tnf-pdfjs-current]');
		var totalEl = root.querySelector('[data-tnf-pdfjs-total]');
		var curMobileEl = root.querySelector('[data-tnf-pdfjs-current-mobile]');
		var totalMobileEl = root.querySelector('[data-tnf-pdfjs-total-mobile]');
		var numberPager = root.querySelector('[data-tnf-pdfjs-number-pager]');
		var zoomSel = root.querySelector('[data-tnf-pdfjs-zoom]');
		var permalink = root.getAttribute('data-tnf-permalink') || window.location.href.split('?')[0];
		var shareBar = root.querySelector('.tnf-epaper-share');
		var clipButton = shareBar ? shareBar.querySelector('.tnf-epaper-share__btn.is-clip') : null;
		var title = root.querySelector('.tnf-epaper__title');
		var titleText = title ? title.textContent.trim() : document.title;
		if (!canvas || !thumbsWrap) {
			return;
		}

		var ctx = canvas.getContext('2d');
		var pdfDoc = null;
		var currentPage = 1;
		var totalPages = 1;
		var zoom = 1;
		var thumbScale = 0.22;
		var rendering = false;
		var pendingPage = null;
		var clipFromQuery = parseClipFromQuery();
		var clipMode = false;
		var clipOnlyMode = clipFromQuery !== null;

		function setShare(pageNum) {
			var shareUrl = addQueryArg(permalink, 'tnf_pg', pageNum);
			updateShareBar(shareBar, shareUrl, titleText);
			try {
				var clean = new URL(permalink, window.location.href);
				clean.searchParams.set('tnf_pg', String(pageNum));
				window.history.replaceState({}, '', clean.pathname + clean.search + clean.hash);
			} catch (e) {
				/* noop */
			}
		}

		function syncStatus() {
			if (curEl) {
				curEl.textContent = String(currentPage);
			}
			if (totalEl) {
				totalEl.textContent = String(totalPages);
			}
			if (curMobileEl) {
				curMobileEl.textContent = String(currentPage);
			}
			if (totalMobileEl) {
				totalMobileEl.textContent = String(totalPages);
			}
			if (statusEl) {
				statusEl.textContent = 'Page ' + String(currentPage) + ' of ' + String(totalPages);
			}
		}

		function renderNumberPager() {
			if (!numberPager) {
				return;
			}
			var windowSize = 5;
			var start = Math.max(1, currentPage - Math.floor(windowSize / 2));
			var end = Math.min(totalPages, start + windowSize - 1);
			if (end - start + 1 < windowSize) {
				start = Math.max(1, end - windowSize + 1);
			}
			var html = '';
			if (currentPage > 1) {
				html += '<button type="button" class="tnf-epaper__num-btn is-arrow" data-pg-jump="' + String(currentPage - 1) + '">&#8249;</button>';
			}
			for (var p = start; p <= end; p++) {
				var active = p === currentPage ? ' is-active' : '';
				var aria = p === currentPage ? ' aria-current="page"' : '';
				html += '<button type="button" class="tnf-epaper__num-btn' + active + '" data-pg-jump="' + String(p) + '"' + aria + '>' + String(p) + '</button>';
			}
			if (currentPage < totalPages) {
				html += '<button type="button" class="tnf-epaper__num-btn is-arrow" data-pg-jump="' + String(currentPage + 1) + '">&#8250;</button>';
			}
			numberPager.innerHTML = html;
			numberPager.querySelectorAll('[data-pg-jump]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					queueRenderPage(parseInt(btn.getAttribute('data-pg-jump'), 10));
				});
			});
		}

		function setActiveThumb() {
			var activeEl = null;
			thumbsWrap.querySelectorAll('.tnf-epaper__thumb').forEach(function (el) {
				var p = parseInt(el.getAttribute('data-tnf-page'), 10);
				var on = p === currentPage;
				el.classList.toggle('is-active', on);
				if (on) {
					el.setAttribute('aria-current', 'page');
					activeEl = el;
				} else {
					el.removeAttribute('aria-current');
				}
			});
			if (activeEl && typeof activeEl.scrollIntoView === 'function') {
				activeEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
			}
		}

		function renderPage(pageNum) {
			rendering = true;
			pdfDoc.getPage(pageNum).then(function (page) {
				var viewport = page.getViewport({ scale: zoom });
				canvas.height = viewport.height;
				canvas.width = viewport.width;
				var renderTask = page.render({ canvasContext: ctx, viewport: viewport });
				return renderTask.promise;
			}).then(function () {
				currentPage = pageNum;
				syncStatus();
				setActiveThumb();
				renderNumberPager();
				setShare(pageNum);
				if (clipFromQuery && clipFromQuery.page === pageNum) {
					drawClipBox(figure, canvas, clipFromQuery);
					if (clipOnlyMode) {
						var outCanvas = clipCanvasFromCanvas(canvas, clipFromQuery);
						if (outCanvas) {
							mountClipOnlyView(root, outCanvas, titleText, window.location.href);
						}
					}
				} else {
					clearClipBox(figure);
				}
				var stage = root.querySelector('.tnf-epaper__figure--pdfjs');
				if (stage) {
					stage.scrollTop = 0;
					stage.scrollLeft = 0;
				}
				rendering = false;
				if (pendingPage !== null) {
					var next = pendingPage;
					pendingPage = null;
					renderPage(next);
				}
			});
		}

		function queueRenderPage(pageNum) {
			if (!pdfDoc) {
				return;
			}
			if (pageNum < 1) {
				pageNum = 1;
			}
			if (pageNum > totalPages) {
				pageNum = totalPages;
			}
			if (rendering) {
				pendingPage = pageNum;
			} else {
				renderPage(pageNum);
			}
		}

		function renderThumb(pageNum, canvas) {
			return pdfDoc.getPage(pageNum).then(function (page) {
				var viewport = page.getViewport({ scale: thumbScale });
				var tctx = canvas.getContext('2d');
				canvas.width = viewport.width;
				canvas.height = viewport.height;
				return page.render({ canvasContext: tctx, viewport: viewport }).promise;
			});
		}

		function setupClipSelection() {
			if (!clipButton || !figure || !canvas) {
				return;
			}
			var startX = 0;
			var startY = 0;
			var dragging = false;
			var draft = null;

			function pointFromEvent(event) {
				var source = event.touches && event.touches[0] ? event.touches[0] : event;
				return { x: source.clientX, y: source.clientY };
			}

			function renderDraft(x1, y1, x2, y2) {
				var figRect = figure.getBoundingClientRect();
				var left = Math.min(x1, x2) - figRect.left;
				var top = Math.min(y1, y2) - figRect.top;
				var width = Math.abs(x2 - x1);
				var height = Math.abs(y2 - y1);
				if (!draft) {
					draft = document.createElement('div');
					draft.className = 'tnf-epaper__clip-box is-draft';
					figure.appendChild(draft);
				}
				draft.style.left = String(left) + 'px';
				draft.style.top = String(top) + 'px';
				draft.style.width = String(width) + 'px';
				draft.style.height = String(height) + 'px';
			}

			function finishSelection(endX, endY) {
				if (!dragging) {
					return;
				}
				dragging = false;
				if (draft) {
					draft.remove();
					draft = null;
				}
				var mediaRect = canvas.getBoundingClientRect();
				var x1 = Math.max(mediaRect.left, Math.min(mediaRect.right, Math.min(startX, endX)));
				var y1 = Math.max(mediaRect.top, Math.min(mediaRect.bottom, Math.min(startY, endY)));
				var x2 = Math.max(mediaRect.left, Math.min(mediaRect.right, Math.max(startX, endX)));
				var y2 = Math.max(mediaRect.top, Math.min(mediaRect.bottom, Math.max(startY, endY)));
				var width = x2 - x1;
				var height = y2 - y1;
				if (width < 16 || height < 16) {
					return;
				}
				var clip = {
					x: (x1 - mediaRect.left) / mediaRect.width,
					y: (y1 - mediaRect.top) / mediaRect.height,
					w: width / mediaRect.width,
					h: height / mediaRect.height
				};
				drawClipBox(figure, canvas, clip);
				var clipUrl = buildClipUrl(permalink, currentPage, clip);
				clipFromQuery = { page: currentPage, x: clip.x, y: clip.y, w: clip.w, h: clip.h };
				updateShareBar(shareBar, clipUrl, titleText);
				bindCopy(shareBar);
				var copy = shareBar ? shareBar.querySelector('.tnf-epaper-share__btn.is-copy') : null;
				if (copy) {
					copy.setAttribute('data-epaper-copy', clipUrl);
					copy.textContent = 'Copy link';
				}
				openClipShareDialog(clipUrl, titleText);
			}

			clipButton.addEventListener('click', function () {
				clipMode = !clipMode;
				clipButton.classList.toggle('is-active', clipMode);
				clipButton.textContent = clipMode ? 'Cancel clip' : 'Clip';
				figure.classList.toggle('is-clip-mode', clipMode);
			});

			figure.addEventListener('mousedown', function (event) {
				if (!clipMode) {
					return;
				}
				var pt = pointFromEvent(event);
				dragging = true;
				startX = pt.x;
				startY = pt.y;
				event.preventDefault();
			});
			figure.addEventListener('mousemove', function (event) {
				if (!clipMode || !dragging) {
					return;
				}
				var pt = pointFromEvent(event);
				renderDraft(startX, startY, pt.x, pt.y);
			});
			figure.addEventListener('mouseup', function (event) {
				if (!clipMode) {
					return;
				}
				var pt = pointFromEvent(event);
				finishSelection(pt.x, pt.y);
			});
			figure.addEventListener('touchstart', function (event) {
				if (!clipMode) {
					return;
				}
				var pt = pointFromEvent(event);
				dragging = true;
				startX = pt.x;
				startY = pt.y;
				event.preventDefault();
			}, { passive: false });
			figure.addEventListener('touchmove', function (event) {
				if (!clipMode || !dragging) {
					return;
				}
				var pt = pointFromEvent(event);
				renderDraft(startX, startY, pt.x, pt.y);
				event.preventDefault();
			}, { passive: false });
			figure.addEventListener('touchend', function (event) {
				if (!clipMode) {
					return;
				}
				var touch = event.changedTouches && event.changedTouches[0] ? event.changedTouches[0] : null;
				if (touch) {
					finishSelection(touch.clientX, touch.clientY);
				}
				event.preventDefault();
			}, { passive: false });
		}

		root.querySelectorAll('[data-tnf-pdfjs-prev],[data-tnf-pdfjs-prev-mobile]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				queueRenderPage(currentPage - 1);
			});
		});
		root.querySelectorAll('[data-tnf-pdfjs-next],[data-tnf-pdfjs-next-mobile]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				queueRenderPage(currentPage + 1);
			});
		});
		if (zoomSel) {
			zoomSel.addEventListener('change', function () {
				var z = parseFloat(zoomSel.value || '1');
				if (!isNaN(z) && z > 0) {
					zoom = z;
					queueRenderPage(currentPage);
				}
			});
		}
		document.addEventListener('keydown', function (e) {
			if (!root.contains(document.activeElement) && document.activeElement !== document.body) {
				return;
			}
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				queueRenderPage(currentPage - 1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				queueRenderPage(currentPage + 1);
			}
		});

		window.pdfjsLib.getDocument(pdfUrl).promise.then(function (pdf) {
			pdfDoc = pdf;
			totalPages = pdf.numPages || 1;
			var qp = parseInt(new URLSearchParams(window.location.search).get('tnf_pg'), 10);
			if (!isNaN(qp) && qp >= 1 && qp <= totalPages) {
				currentPage = qp;
			}
			syncStatus();
			thumbsWrap.innerHTML = '';
			var thumbCanvasMap = {};
			for (var i = 1; i <= totalPages; i++) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'tnf-epaper__thumb';
				btn.setAttribute('data-tnf-page', String(i));
				btn.innerHTML =
					'<span class="tnf-epaper__thumb-img-wrap"><canvas></canvas></span>' +
					'<span class="tnf-epaper__thumb-label">Page ' + String(i) + '</span>';
				(function (pg, node) {
					node.addEventListener('click', function () {
						queueRenderPage(pg);
					});
				})(i, btn);
				thumbsWrap.appendChild(btn);
				thumbCanvasMap[i] = btn.querySelector('canvas');
			}
			var chain = Promise.resolve();
			for (var p = 1; p <= totalPages; p++) {
				(function (pageNo) {
					chain = chain.then(function () {
						return renderThumb(pageNo, thumbCanvasMap[pageNo]);
					});
				})(p);
			}
			renderPage(currentPage);
			bindCopy(shareBar);
			setupClipSelection();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initEpaper();
			initEmbedShareOnly();
			initPdfjsFallback();
		});
	} else {
		initEpaper();
		initEmbedShareOnly();
		initPdfjsFallback();
	}
})();
