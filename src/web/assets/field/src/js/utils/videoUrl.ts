/**
 * Client-side URL completeness checks aligned with provider `getVideoIdFromUrl`
 * patterns — used so the field doesn’t hit get-video on every keystroke (D05).
 */

const YOUTUBE_ID =
    /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/|.+\?v=)|youtu\.be\/)([\w-]{11})/i;

const VIMEO_ID =
    /(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(?:channels\/[\w]+\/|groups\/[\w]+\/videos\/|album\/\d+\/video\/|video\/|)(\d+)/i;

const DAILYMOTION_ID =
    /(?:https?:\/\/)?(?:www\.)?(?:dailymotion\.com\/video\/|dai\.ly\/)([a-zA-Z0-9]+)/i;

const MUX_ID =
    /(?:https?:\/\/)?(?:www\.)?(?:player\.mux\.com|stream\.mux\.com)\/([a-zA-Z0-9]+)/i;

const WISTIA_ID =
    /(?:https?:\/\/)?(?:[\w.-]+\.)?wistia\.com\/medias\/([a-zA-Z0-9]+)|(?:https?:\/\/)?fast\.wistia\.net\/embed\/iframe\/([a-zA-Z0-9]+)/i;

const CLOUDFLARE_STREAM_ID =
    /(?:https?:\/\/)?(?:[\w.-]+\.)?cloudflarestream\.com\/([a-f0-9]{32})|(?:https?:\/\/)?(?:www\.)?(?:iframe\.)?videodelivery\.net\/([a-f0-9]{32})/i;

const BUNNY_STREAM_ID =
    /(?:https?:\/\/)?(?:player|iframe)\.mediadelivery\.net\/embed\/\d+\/([a-f0-9-]{36})|(?:https?:\/\/)?video\.bunnycdn\.com\/play\/\d+\/([a-f0-9-]{36})/i;

const SPROUT_VIDEO_ID =
    /(?:https?:\/\/)?videos\.sproutvideo\.com\/embed\/([a-z0-9]+)(?:\/[a-z0-9]+)?|(?:https?:\/\/)?(?:www\.)?sproutvideo\.com\/videos\/([a-z0-9]+)/i;

/** Custom providers own URL matching on the server; allow them on explicit commit. */
export const isHttpVideoUrl = (url: string): boolean => {
    try {
        const parsed = new URL(url);
        return parsed.protocol === 'https:' || parsed.protocol === 'http:';
    } catch {
        return false;
    }
};

/** True when the string looks like a full video URL we can resolve. */
export const isResolvableVideoUrl = (url: string): boolean => {
    const trimmed = url.trim();

    if (!trimmed) {
        return false;
    }

    return (
        YOUTUBE_ID.test(trimmed) ||
        VIMEO_ID.test(trimmed) ||
        DAILYMOTION_ID.test(trimmed) ||
        MUX_ID.test(trimmed) ||
        WISTIA_ID.test(trimmed) ||
        CLOUDFLARE_STREAM_ID.test(trimmed) ||
        BUNNY_STREAM_ID.test(trimmed) ||
        SPROUT_VIDEO_ID.test(trimmed)
    );
};
