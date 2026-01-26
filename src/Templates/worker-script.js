/**
 * CloudFlare R2 CDN Worker
 * Auto-deployed by WordPress plugin
 * Version: {{VERSION}}
 */
export default {
	async fetch(request, env) {
		const url = new URL(request.url);
		const pathname = url.pathname;

		// Skip non-image paths
		const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.avif'];
		const isImage = imageExtensions.some(ext => pathname.toLowerCase().endsWith(ext));

		if (!isImage) {
			// Pass through to R2 directly
			return fetch(`${env.R2_PUBLIC_URL}${pathname}`);
		}

		// Parse transformation params
		const options = { cf: { image: {} } };

		// Width
		const width = url.searchParams.get('w');
		if (width) {
			options.cf.image.width = parseInt(width, 10);
		}

		// Height
		const height = url.searchParams.get('h');
		if (height) {
			options.cf.image.height = parseInt(height, 10);
		}

		// Quality (1-100)
		const quality = url.searchParams.get('q');
		if (quality) {
			options.cf.image.quality = Math.min(100, Math.max(1, parseInt(quality, 10)));
		}

		// Fit mode
		const fit = url.searchParams.get('fit');
		if (fit && ['scale-down', 'contain', 'cover', 'crop', 'pad'].includes(fit)) {
			options.cf.image.fit = fit;
		}

		// Format negotiation
		const format = url.searchParams.get('f');
		if (format === 'auto') {
			const accept = request.headers.get('Accept') || '';
			if (env.ENABLE_AVIF === 'true' && /image\/avif/.test(accept)) {
				options.cf.image.format = 'avif';
			} else if (/image\/webp/.test(accept)) {
				options.cf.image.format = 'webp';
			}
		} else if (format && ['webp', 'avif', 'json'].includes(format)) {
			options.cf.image.format = format;
		}

		// Build origin URL (strip transform params)
		const originUrl = new URL(`${env.R2_PUBLIC_URL}${pathname}`);

		try {
			const response = await fetch(originUrl.toString(), options);

			// Add cache headers
			const headers = new Headers(response.headers);
			headers.set('Cache-Control', 'public, max-age=31536000, immutable');
			headers.set('X-CFR2-Transform', 'true');

			return new Response(response.body, {
				status: response.status,
				headers,
			});
		} catch (error) {
			// Fallback: serve original without transforms
			return fetch(originUrl.toString());
		}
	}
};
