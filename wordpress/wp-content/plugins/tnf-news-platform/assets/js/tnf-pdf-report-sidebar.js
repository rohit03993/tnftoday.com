(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var MediaUpload = wp.blockEditor.MediaUpload;
	var Button = wp.components.Button;
	var CheckboxControl = wp.components.CheckboxControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var __ = wp.i18n.__;

	function TnfPdfDocumentPanel() {
		var meta = useSelect(function (select) {
			return select('core/editor').getEditedPostAttribute('meta') || {};
		}, []);

		var editPost = useDispatch('core/editor').editPost;

		var id = parseInt(meta.tnf_pdf_attachment_id, 10) || 0;
		var restricted =
			meta.tnf_restricted === true ||
			meta.tnf_restricted === 1 ||
			meta.tnf_restricted === '1';
		var status = meta.tnf_pdf_status ? String(meta.tnf_pdf_status) : 'idle';
		var jobId = meta.tnf_pdf_job_id ? String(meta.tnf_pdf_job_id) : '';
		var err = meta.tnf_pdf_error ? String(meta.tnf_pdf_error) : '';

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'tnf-pdf-file',
				title: __('PDF file & access', 'tnf-news-platform'),
				className: 'tnf-pdf-document-panel',
			},
			el(
				'p',
				{ className: 'description', style: { marginTop: 0 } },
				__(
					'Visitors get an in-browser PDF viewer and download links on the public page. The ePaper list at /epaper/ only shows published reports.',
					'tnf-news-platform'
				)
			),
			el(
				MediaUploadCheck,
				null,
				el(MediaUpload, {
					onSelect: function (media) {
						if (!media || !media.id) {
							return;
						}
						editPost({
							meta: { tnf_pdf_attachment_id: media.id },
						});
					},
					allowedTypes: ['application/pdf'],
					value: id || undefined,
					render: function (obj) {
						return el(
							Fragment,
							null,
							el(
								Button,
								{
									variant: 'primary',
									onClick: obj.open,
								},
								id
									? __('Replace PDF', 'tnf-news-platform')
									: __('Upload or choose PDF', 'tnf-news-platform')
							),
							id
								? el(
										Button,
										{
											variant: 'link',
											onClick: function () {
												editPost({
													meta: { tnf_pdf_attachment_id: 0 },
												});
											},
											style: { marginLeft: '8px' },
										},
										__('Remove PDF', 'tnf-news-platform')
									)
								: null
						);
					},
				})
			),
			id
				? el(
						'p',
						{ className: 'description', style: { marginBottom: 0 } },
						__('Attachment ID:', 'tnf-news-platform') + ' ' + String(id) + ' ',
						el(
							'a',
							{
								href:
									window.tnfPdfSidebarAdmin && window.tnfPdfSidebarAdmin.postEditUrlTpl
										? String(window.tnfPdfSidebarAdmin.postEditUrlTpl).replace(
												'__ID__',
												String(id)
											)
										: '#',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__('Open in Media Library', 'tnf-news-platform')
						)
					)
				: null,
			el(CheckboxControl, {
				label: __('Subscriber-only PDF', 'tnf-news-platform'),
				checked: restricted,
				onChange: function (v) {
					editPost({
						meta: { tnf_restricted: !!v },
					});
				},
			}),
			el('hr', { style: { margin: '12px 0' } }),
			el(
				'p',
				{ style: { fontSize: '12px', margin: '0 0 4px' } },
				__('Processing status:', 'tnf-news-platform') + ' ' + status
			),
			jobId
				? el(
						'p',
						{ style: { fontSize: '12px', margin: '0 0 4px' } },
						__('Job ID:', 'tnf-news-platform') + ' ' + jobId
					)
				: null,
			err
				? el(
						'p',
						{
							className: 'tnf-pdf-sidebar-error',
							style: {
								fontSize: '12px',
								padding: '8px',
								background: '#fcf0f1',
								borderLeft: '4px solid #d63638',
							},
						},
						err
					)
				: null,
			el(
				'p',
				{ className: 'description', style: { marginTop: '12px', marginBottom: 0 } },
				__(
					'Save the post, then Publish — drafts do not appear on /epaper/.',
					'tnf-news-platform'
				)
			)
		);
	}

	function TnfPdfPlugin() {
		var postType = useSelect(function (select) {
			return select('core/editor').getCurrentPostType();
		}, []);

		if (postType !== 'tnf_pdf_report') {
			return null;
		}

		return el(TnfPdfDocumentPanel, null);
	}

	registerPlugin('tnf-pdf-report-sidebar', {
		icon: 'media-document',
		render: TnfPdfPlugin,
	});
})(window.wp);
