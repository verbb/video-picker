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

/** Keep one tab stop in the grid; selected (or first) card is the roving target. */
export const syncVideoCardSelection = (
    cards: NodeListOf<Element> | HTMLElement[],
    selectedId: string | number | null | undefined,
): void => {
    const list = [...cards] as HTMLElement[];
    let focusIndex = list.findIndex(
        (card) => card.getAttribute('aria-selected') === 'true',
    );

    list.forEach((card) => {
        const id = card.dataset.videoId ?? '';
        const selected = selectedId != null && id !== '' && String(selectedId) === String(id);
        card.setAttribute('aria-selected', selected ? 'true' : 'false');
        card.querySelector('.vp-video-thumb')?.classList.toggle('is-selected', selected);

        if (selected) {
            focusIndex = list.indexOf(card);
        }
    });

    if (focusIndex < 0) {
        focusIndex = 0;
    }

    list.forEach((card, index) => {
        card.tabIndex = index === focusIndex ? 0 : -1;
    });
};

const columnCount = (grid: HTMLElement): number => {
    const raw = getComputedStyle(grid).gridTemplateColumns;
    const count = raw.split(/\s+/).filter(Boolean).length;

    return count > 0 ? count : 1;
};

export const createVideoCard = (
    video: VideoData,
    selected: boolean,
    handlers: VideoCardHandlers,
): HTMLElement => {
    const card = document.createElement('div');
    card.className = 'vp-video-card';
    card.setAttribute('role', 'option');
    card.setAttribute('aria-selected', selected ? 'true' : 'false');
    // Roving tabindex is finalized by the grid after all cards mount.
    card.tabIndex = -1;

    if (video.id != null) {
        card.dataset.videoId = String(video.id);
    }

    const label = (video.title ?? '').trim() || Craft.t('video-picker', 'Video');
    card.setAttribute('aria-label', label);

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

    card.addEventListener('keydown', (event: KeyboardEvent) => {
        if (event.key === ' ' || event.key === 'Spacebar') {
            event.preventDefault();
            handlers.onSelect(video);
            return;
        }

        // Enter selects; when already selected, commit (keyboard stand-in for double-click).
        if (event.key === 'Enter') {
            event.preventDefault();

            if (card.getAttribute('aria-selected') === 'true') {
                handlers.onUse(video);
            } else {
                handlers.onSelect(video);
            }
        }
    });

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
    grid.setAttribute('role', 'listbox');
    grid.setAttribute('aria-label', Craft.t('video-picker', 'Videos'));
    // Single-select listbox — Space/Enter select; footer Select (or Enter again) commits.
    grid.setAttribute('aria-multiselectable', 'false');

    for (const video of videos) {
        const selected = selectedId != null && video.id != null && String(selectedId) === String(video.id);
        grid.appendChild(createVideoCard(video, selected, handlers));
    }

    const cards = () => [...grid.querySelectorAll<HTMLElement>('.vp-video-card')];

    syncVideoCardSelection(grid.querySelectorAll('.vp-video-card'), selectedId);

    // Arrow keys move focus (and selection) across the visual grid columns.
    grid.addEventListener('keydown', (event: KeyboardEvent) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.classList.contains('vp-video-card')) {
            return;
        }

        const list = cards();
        const index = list.indexOf(target);
        if (index < 0) {
            return;
        }

        const cols = columnCount(grid);
        let next = -1;

        switch (event.key) {
            case 'ArrowRight':
                next = Math.min(list.length - 1, index + 1);
                break;
            case 'ArrowLeft':
                next = Math.max(0, index - 1);
                break;
            case 'ArrowDown':
                next = Math.min(list.length - 1, index + cols);
                break;
            case 'ArrowUp':
                next = Math.max(0, index - cols);
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = list.length - 1;
                break;
            default:
                return;
        }

        if (next === index) {
            return;
        }

        event.preventDefault();
        const videoId = list[next].dataset.videoId;
        const video = videos.find((item) => videoId != null && String(item.id) === String(videoId));

        if (video) {
            // Move selection with focus so Tab → Select commits the focused card.
            handlers.onSelect(video);
            list[next].focus();
        } else {
            list[next].tabIndex = 0;
            target.tabIndex = -1;
            list[next].focus();
        }
    });

    return grid;
};
