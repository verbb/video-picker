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
        WISTIA_ID.test(trimmed)
    );
};
