import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Live production server — app content updates from tnftoday.com without APK rebuilds.
 * Override with CAPACITOR_SERVER_URL env for staging (e.g. https://staging.tnftoday.com).
 */
const serverUrl =
	typeof process !== 'undefined' && process.env.CAPACITOR_SERVER_URL
		? process.env.CAPACITOR_SERVER_URL
		: 'https://tnftoday.com';

const config: CapacitorConfig = {
	appId: 'com.tnftoday.app',
	appName: 'TNF Today',
	webDir: 'www',
	server: {
		url: serverUrl,
		cleartext: false,
		androidScheme: 'https',
	},
	android: {
		appendUserAgent: ' TNFTodayCapacitor/1.0',
		allowMixedContent: false,
		backgroundColor: '#0f1320',
	},
	plugins: {
		SplashScreen: {
			launchShowDuration: 1200,
			launchAutoHide: true,
			backgroundColor: '#bc1e38',
			androidSplashResourceName: 'splash',
			androidScaleType: 'CENTER_CROP',
			showSpinner: false,
		},
		StatusBar: {
			style: 'DARK',
			backgroundColor: '#0f1320',
		},
		PushNotifications: {
			presentationOptions: ['badge', 'sound', 'alert'],
		},
	},
};

export default config;
