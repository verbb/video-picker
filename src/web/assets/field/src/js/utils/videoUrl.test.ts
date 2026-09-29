import { describe, expect, it } from 'vitest';

import { isResolvableVideoUrl } from './videoUrl.js';

describe('isResolvableVideoUrl', () => {
    it.each([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://vimeo.com/123456789',
        'https://dai.ly/x8abc12',
        'https://player.mux.com/abc123',
        'https://fast.wistia.net/embed/iframe/abc123',
        'https://videodelivery.net/0123456789abcdef0123456789abcdef',
        'https://player.mediadelivery.net/embed/1/123e4567-e89b-12d3-a456-426614174000',
        'https://sproutvideo.com/videos/abc123',
    ])('accepts a complete supported provider URL: %s', (url) => {
        expect(isResolvableVideoUrl(url)).toBe(true);
    });

    it.each([
        '',
        'https://www.youtube.com/watch?v=short',
        'https://example.com/video/123',
        'javascript:alert(1)',
    ])('does not resolve incomplete or unsupported input: %s', (url) => {
        expect(isResolvableVideoUrl(url)).toBe(false);
    });
});
