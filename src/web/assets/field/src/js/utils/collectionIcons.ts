/**
 * PHP sources emit kebab icon names (`video-camera`, `folder`, …).
 *
 * Sidebar collection icons are bespoke BEFORE outline strokes registered on the
 * Plugin Kit icon registry (not FA). Thumb `play` stays the filled circle-play
 * path; `lock` uses kit.
 */

import { getIcon, normalizeIconName } from '@verbb/plugin-kit-icons';

import {
    SIDEBAR_STROKE_ICON_NAMES,
    strokeIconToSvg,
} from '../icons/sidebarCollectionIcons.js';

/** Filled circle-play for thumb overlay (not sidebar). */
const PLAY_INLINE = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true"><path fill="currentColor" d="M0 256a256 256 0 1 1 512 0A256 256 0 1 1 0 256zM188.3 147.1c-7.6 4.2-12.3 12.3-12.3 20.9v176c0 8.7 4.7 16.7 12.3 20.9s16.8 4.1 24.3-.5l144-88c7.1-4.4 11.5-12.1 11.5-20.5s-4.4-16.1-11.5-20.5l-144-88c-7.4-4.5-16.7-4.7-24.3-.5z"/></svg>`;

/** Kit names for non-sidebar chrome when PHP emits them. */
const KIT_ICON: Record<string, string> = {
    eye: 'eye',
    download: 'download',
    lock: 'lock',
    search: 'magnifying-glass',
};

export const createCollectionIcon = (name: string | null | undefined): HTMLElement => {
    const key = normalizeIconName(name || '') || (name || '').trim();
    const wrap = document.createElement('span');
    wrap.className = 'vp-sidebar-icon';
    wrap.setAttribute('aria-hidden', 'true');

    if (SIDEBAR_STROKE_ICON_NAMES.has(key)) {
        const icon = getIcon(key);

        if (icon) {
            wrap.innerHTML = strokeIconToSvg(icon);

            return wrap;
        }
    }

    const kit = KIT_ICON[key];

    if (kit) {
        const el = document.createElement('pk-icon');
        el.setAttribute('icon', kit);
        wrap.appendChild(el);

        return wrap;
    }

    // Unknown name — fall back to registered folder outline.
    const folder = getIcon('folder');

    if (folder) {
        wrap.innerHTML = strokeIconToSvg(folder);
    }

    return wrap;
};

export const createInlineIcon = (name: 'play' | 'lock'): HTMLElement => {
    const wrap = document.createElement('span');
    wrap.className = `vp-inline-icon vp-inline-icon--${name}`;
    wrap.setAttribute('aria-hidden', 'true');

    if (name === 'lock') {
        const icon = document.createElement('pk-icon');
        icon.setAttribute('icon', 'lock');
        wrap.appendChild(icon);

        return wrap;
    }

    wrap.innerHTML = PLAY_INLINE;

    return wrap;
};
