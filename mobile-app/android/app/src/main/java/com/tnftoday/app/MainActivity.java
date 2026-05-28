package com.tnftoday.app;

import android.os.Bundle;
import android.webkit.WebSettings;
import android.webkit.WebView;

import androidx.activity.OnBackPressedCallback;

import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
	@Override
	public void onCreate(Bundle savedInstanceState) {
		super.onCreate(savedInstanceState);
		configureWebView();
		configureBackNavigation();
	}

	private void configureWebView() {
		if (getBridge() == null) {
			return;
		}
		WebView webView = getBridge().getWebView();
		if (webView == null) {
			return;
		}
		WebSettings settings = webView.getSettings();
		settings.setUseWideViewPort(true);
		settings.setLoadWithOverviewMode(true);
		settings.setTextZoom(100);
		settings.setBuiltInZoomControls(false);
		settings.setDisplayZoomControls(false);
		settings.setSupportZoom(false);
	}

	private void configureBackNavigation() {
		getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
			@Override
			public void handleOnBackPressed() {
				if (getBridge() != null && getBridge().getWebView() != null && getBridge().getWebView().canGoBack()) {
					getBridge().getWebView().goBack();
					return;
				}
				finish();
			}
		});
	}
}
