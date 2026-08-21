import { createInlineIcon } from '../utils/collectionIcons.js';

export type VideoData = {
    id?: string | number | null;
    url?: string | null;
    title?: string | null;
    thumbnail?: string | null;
    duration?: string | null;
    authorName?: string | null;
    authorUrl?: string | null;
    plays?: number | null;
    date?: string | null;
    description?: string | null;
    embedHtml?: string | null;
    private?: boolean | null;
    errors?: Record<string, string[]> | null;
    [key: string]: unknown;
};

export type VideoCardHandlers = {
    onSelect: (video: VideoData) => void;
    onUse: (video: VideoData) => void;
    onPlay: (video: VideoData) => void;
};

export const createVideoThumb = (
    video: VideoData,
    options: { selected?: boolean; onPlay?: () => void } = {},
): HTMLElement => {
    const thumb = document.createElement('div');
    thumb.className = 'vp-video-thumb';

    if (options.selected) {
        thumb.classList.add('is-selected');
    }

    const imageContainer = document.createElement('div');
    imageContainer.className = 'vp-video-thumb-image-container';

    const image = document.createElement('div');
    image.className = 'vp-video-thumb-image';

    if (video.thumbnail) {
        image.style.backgroundImage = `url(${JSON.stringify(video.thumbnail).slice(1, -1)})`;
    }

    imageContainer.appendChild(image);

    const duration = document.createElement('div');
    duration.className = 'vp-video-thumb-duration';
    duration.textContent = video.duration ?? '';

    const play = document.createElement('div');
    play.className = 'vp-video-thumb-play';
    play.appendChild(createInlineIcon('play'));
    play.addEventListener('click', (event) => {
        // Match BEFORE: preventDefault only; bubble still selects the card.
        event.preventDefault();
        options.onPlay?.();
    });

    thumb.append(imageContainer, duration, play);

    return thumb;
};

export const createVideoCard = (
    video: VideoData,
    selected: boolean,
    handlers: VideoCardHandlers,
): HTMLElement => {
    const card = document.createElement('div');
    card.className = 'vp-video-card';

    const thumb = createVideoThumb(video, {
        selected,
        onPlay: () => handlers.onPlay(video),
    });

    const container = document.createElement('div');
    container.className = 'vp-video-card-container';

    if (video.private) {
        const privateIcon = document.createElement('div');
        privateIcon.className = 'vp-icon-private';
        privateIcon.appendChild(createInlineIcon('lock'));
        container.appendChild(privateIcon);
    }

    const text = document.createElement('div');
    text.className = 'vp-video-card-text';
    text.textContent = video.title ?? '';
    container.appendChild(text);

    card.append(thumb, container);

    card.addEventListener('click', () => handlers.onSelect(video));
    card.addEventListener('dblclick', () => handlers.onUse(video));

    return card;
};

export const createVideoGrid = (
    videos: VideoData[],
    selectedId: string | number | null | undefined,
    handlers: VideoCardHandlers,
): HTMLElement => {
    if (!videos.length) {
        const empty = document.createElement('div');
        const hint = document.createElement('span');
        hint.className = 'vp-hint-text';
        hint.textContent = Craft.t('video-picker', 'No videos available.');
        empty.appendChild(hint);

        return empty;
    }

    const grid = document.createElement('div');
    grid.className = 'vp-video-cards';

    for (const video of videos) {
        const selected = selectedId != null && video.id != null && String(selectedId) === String(video.id);
        grid.appendChild(createVideoCard(video, selected, handlers));
    }

    return grid;
};
