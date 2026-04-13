(function ($) {
	'use strict';

	function setSummary(text) {
		$('#tnf_pdf_file_summary').text(text || tnfPdfMetaboxL10n.none);
	}

	function refreshButtons(hasId) {
		$('#tnf_pdf_clear_btn').toggle(!!hasId);
		$('#tnf_pdf_select_btn').text(
			hasId ? tnfPdfMetaboxL10n.replace : tnfPdfMetaboxL10n.select
		);
	}

	$(function () {
		var $input = $('#tnf_pdf_attachment_id');
		if (!$input.length) {
			return;
		}

		var frame;

		$('#tnf_pdf_select_btn').on('click', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: tnfPdfMetaboxL10n.title,
				button: { text: tnfPdfMetaboxL10n.button },
				library: { type: 'application/pdf' },
				multiple: false,
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				if (!att || !att.id) {
					return;
				}
				if (att.mime && att.mime !== 'application/pdf') {
					return;
				}
				$input.val(String(att.id));
				setSummary(att.filename || att.title || '#' + att.id);
				refreshButtons(true);
			});
			frame.open();
		});

		$('#tnf_pdf_clear_btn').on('click', function (e) {
			e.preventDefault();
			$input.val('0');
			setSummary('');
			refreshButtons(false);
		});

		refreshButtons(!!parseInt($input.val(), 10));
	});
})(jQuery);
