<?php
/**
 * HTTP client for FastAPI PDF microservice.
 *
 * @package TNF_News_Platform
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Service base URL.
 */
function tnf_pdf_service_base_url(): string {
	if (defined('TNF_PDF_SERVICE_URL') && TNF_PDF_SERVICE_URL) {
		return rtrim(TNF_PDF_SERVICE_URL, '/');
	}
	return 'http://localhost:8000';
}

/**
 * Shared secret header value.
 */
function tnf_pdf_service_secret(): string {
	return defined('TNF_PDF_SERVICE_SECRET') ? (string) TNF_PDF_SERVICE_SECRET : '';
}

/**
 * Rewrite public attachment URL for in-cluster fetch (Docker).
 *
 * @param string $url Public URL.
 */
function tnf_pdf_internal_file_url(string $url): string {
	$internal = defined('TNF_INTERNAL_WP_URL') ? TNF_INTERNAL_WP_URL : '';
	if ($internal === '') {
		return $url;
	}
	$public = home_url();
	return str_replace(untrailingslashit($public), untrailingslashit($internal), $url);
}

/**
 * Enqueue PDF processing job.
 *
 * @param string $source_url HTTP URL to PDF (reachable from PDF service).
 * @param string $external_id Stable ID (e.g. post-{id}).
 */
function tnf_pdf_enqueue_process(string $source_url, string $external_id): array|string {
	$url = tnf_pdf_service_base_url() . '/pdf/process';
	$res = wp_remote_post(
		$url,
		array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'     => 'application/json',
				'X-Service-Secret' => tnf_pdf_service_secret(),
			),
			'body'    => wp_json_encode(
				array(
					'source_url'   => $source_url,
					'external_id'  => $external_id,
					'idempotency_key' => $external_id,
				)
			),
		)
	);

	if (is_wp_error($res)) {
		return $res->get_error_message();
	}

	$code = wp_remote_retrieve_response_code($res);
	$body = json_decode(wp_remote_retrieve_body($res), true);
	if ($code >= 400) {
		return isset($body['detail']) ? (string) $body['detail'] : 'HTTP ' . $code;
	}
	return is_array($body) ? $body : array();
}
