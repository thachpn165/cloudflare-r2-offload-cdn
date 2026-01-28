/**
 * CloudFlare R2 CDN Worker
 * Auto-deployed by WordPress plugin
 * Version: {{VERSION}}
 *
 * Uses R2 binding for direct bucket access (faster, no public access needed).
 */
export default {
	async fetch(request, env) {
		const url = new URL(request.url);
		// Remove leading slash for R2 key
		const key = url.pathname.slice(1);

		if (!key) {
			return new Response('Not Found', { status: 404 });
		}

		try {
			// Get object directly from R2 bucket
			const object = await env.BUCKET.get(key);

			if (!object) {
				return new Response('Not Found', { status: 404 });
			}

			// Determine content type
			const contentType = object.httpMetadata?.contentType || getMimeType(key);

			// Build response headers
			const headers = new Headers();
			headers.set('Content-Type', contentType);
			headers.set('Cache-Control', 'public, max-age=31536000, immutable');
			headers.set('ETag', object.httpEtag);
			headers.set('X-CFR2-Source', 'r2-binding');

			// Handle conditional requests
			const ifNoneMatch = request.headers.get('If-None-Match');
			if (ifNoneMatch === object.httpEtag) {
				return new Response(null, { status: 304, headers });
			}

			// Check if image transformation is requested
			const isImage = /\.(jpg|jpeg|png|gif|webp|avif)$/i.test(key);
			const hasTransformParams = url.searchParams.has('w') || url.searchParams.has('h') ||
				url.searchParams.has('q') || url.searchParams.has('f');

			if (isImage && hasTransformParams) {
				// For image transformation, use cf.image with origin fetch
				// This requires constructing a URL to the same object
				return handleImageTransform(request, env, key, url, headers);
			}

			// Return object directly
			return new Response(object.body, { headers });

		} catch (error) {
			return new Response(`Error: ${error.message}`, { status: 500 });
		}
	}
};

/**
 * Handle image transformation using cf.image
 */
async function handleImageTransform(request, env, key, url, baseHeaders) {
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

	// For cf.image to work, we need to fetch from a URL
	// Use the same request URL but strip transform params (origin fetch)
	const originUrl = new URL(request.url);
	originUrl.search = ''; // Remove query params

	try {
		const response = await fetch(originUrl.toString(), options);

		const headers = new Headers(response.headers);
		headers.set('Cache-Control', 'public, max-age=31536000, immutable');
		headers.set('X-CFR2-Transform', 'true');

		return new Response(response.body, {
			status: response.status,
			headers,
		});
	} catch (error) {
		// Fallback: serve original from R2
		const object = await env.BUCKET.get(key);
		if (object) {
			return new Response(object.body, { headers: baseHeaders });
		}
		return new Response('Transform failed', { status: 500 });
	}
}

/**
 * Get MIME type from file extension
 */
function getMimeType(filename) {
	const ext = filename.split('.').pop()?.toLowerCase();
	const mimeTypes = {
		'jpg': 'image/jpeg',
		'jpeg': 'image/jpeg',
		'png': 'image/png',
		'gif': 'image/gif',
		'webp': 'image/webp',
		'avif': 'image/avif',
		'svg': 'image/svg+xml',
		'ico': 'image/x-icon',
		'pdf': 'application/pdf',
		'css': 'text/css',
		'js': 'application/javascript',
		'json': 'application/json',
		'html': 'text/html',
		'txt': 'text/plain',
		'xml': 'application/xml',
		'mp4': 'video/mp4',
		'webm': 'video/webm',
		'mp3': 'audio/mpeg',
		'woff': 'font/woff',
		'woff2': 'font/woff2',
		'ttf': 'font/ttf',
		'eot': 'application/vnd.ms-fontobject',
	};
	return mimeTypes[ext] || 'application/octet-stream';
}
