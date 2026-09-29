/**
 * Bespoke explorer sidebar icons — copied as-is from BEFORE Vue SVGs
 * (`VideoCamera.vue`, `ThumbUp.vue`, `Folder.vue`, `Layout.vue`, `List.vue`).
 *
 * These are Heroicons-style 24×24 outline strokes, not Font Awesome. Registered
 * with Plugin Kit for name lookup; rendered via {@link strokeIconToSvg} because
 * kit `iconToSvg` / `<pk-icon>` always paint `fill="currentColor"`.
 */
import type { PkIcon } from '@verbb/plugin-kit-icons';

export const videoCamera: PkIcon = {
    width: 24,
    height: 24,
    path: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
};

export const thumbUp: PkIcon = {
    width: 24,
    height: 24,
    path: 'M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5',
};

export const folder: PkIcon = {
    width: 24,
    height: 24,
    path: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
};

export const layout: PkIcon = {
    width: 24,
    height: 24,
    path: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z',
};

export const list: PkIcon = {
    width: 24,
    height: 24,
    path: 'M4 6h16M4 10h16M4 14h16M4 18h16',
};

/** PHP / Vue collection `icon` keys that use the stroke set above. */
export const SIDEBAR_STROKE_ICON_NAMES = new Set([
    'video-camera',
    'thumb-up',
    'folder',
    'layout',
    'list',
]);

/** BEFORE outline SVG — stroke, not fill (kit `iconToSvg` cannot express this). */
export const strokeIconToSvg = (icon: PkIcon): string => {
    const { width, height, path } = icon;

    return (
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" fill="none" aria-hidden="true">` +
        `<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path}"/>` +
        `</svg>`
    );
};
