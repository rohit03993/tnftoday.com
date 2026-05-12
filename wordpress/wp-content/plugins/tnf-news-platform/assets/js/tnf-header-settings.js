(function ($) {
	'use strict';

	function setPreview(url) {
		if (!url) {
			$('#tnf_header_banner_preview').html(
				'<div style="max-width:520px;padding:18px;border:1px dashed rgba(0,0,0,.25);border-radius:6px;color:#555;">No banner image selected yet.</div>'
			);
			return;
		}
		$('#tnf_header_banner_preview').html(
			'<img src="' +
				url +
				'" alt="" style="max-width:520px;width:100%;height:auto;border:1px solid rgba(0,0,0,.12);border-radius:6px;" />'
		);
	}

	$(function () {
		var frame = null;

		$('#tnf_header_banner_select_btn').on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: (window.tnfHeaderSettingsL10n && window.tnfHeaderSettingsL10n.title) || 'Choose header banner image',
				button: { text: (window.tnfHeaderSettingsL10n && window.tnfHeaderSettingsL10n.button) || 'Use this image' },
				library: { type: 'image' },
				multiple: false,
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				if (!att || !att.id) return;

				$('#tnf_header_banner_attachment_id').val(att.id);
				var url = (att.sizes && att.sizes.large && att.sizes.large.url) || att.url;
				setPreview(url);
				$('#tnf_header_banner_clear_btn').show();
			});

			frame.open();
		});

		$('#tnf_header_banner_clear_btn').on('click', function (e) {
			e.preventDefault();
			$('#tnf_header_banner_attachment_id').val('0');
			setPreview('');
			$(this).hide();
		});
	});
})(jQuery);

