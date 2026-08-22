/**
 * Client-side URL completeness checks aligned with provider `getVideoIdFromUrl`
 * patterns — used so the field doesn’t hit get-video on every keystroke (D05).
 */

const YOUTUBE_ID =
    /(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/|.+\?v=)|youtu\.be\/)([\w-]{11})/i;

const VIMEO_ID =
    /(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(?:channels\/[\w]+\/|groups\/[\w]+\/videos\/|album\/\d+\/video\/|video\/|)(\d+)/i;

/** True when the string looks like a full YouTube or Vimeo video URL we can resolve. */
export const isResolvableVideoUrl = (url: string): boolean => {
    const trimmed = url.trim();

    if (!trimmed) {
        return false;
    }

    return YOUTUBE_ID.test(trimmed) || VIMEO_ID.test(trimmed);
};
