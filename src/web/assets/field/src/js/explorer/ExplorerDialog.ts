import { formatErrorHtml } from '../utils/ajaxErrors.js';
import { createCollectionIcon } from '../utils/collectionIcons.js';
import { debounce } from '../utils/formatVideo.js';
import { createVideoGrid, syncVideoCardSelection, type VideoData } from '../video/VideoCard.js';

type PkDialogElement = HTMLElement & { open: boolean };
type PkSelectElement = HTMLElement & { value: string };

type Collection = {
    name: string;
    method?: string | null;
    options?: Record<string, unknown> | unknown[];
    icon?: string | null;
};

type Section = {
    name: string;
    collections: Collection[];
};

type Source = {
    handle: string;
    name: string;
    supportsSearch?: boolean;
    sections: Section[];
};

export type ExplorerDialogOptions = {
    mount: HTMLElement;
    fieldId?: number | null;
    video?: VideoData | null;
    onSelect: (video: VideoData) => void;
    onPlay: (video: VideoData) => void;
    onClosed: () => void;
};

/**
 * Explorer browse dialog — ports Explorer.vue onto `pk-dialog` + `pk-select`.
 * AJAX contracts unchanged: get-sources / get-videos (+ nextPage pagination).
 */
export class ExplorerDialog {
    private readonly options: ExplorerDialogOptions;
    private readonly dialog: PkDialogElement;

    private loadingSources = false;
    private loadingVideos = false;
    private loadingMore = false;
    private nextPage: unknown = null;
    private sourcesError: string | null = null;
    private videosError: string | null = null;
    private query = '';
    private sources: Source[] = [];
    private videos: VideoData[] = [];
    private currentSource: Source | null = null;
    private currentCollection: Collection | null = null;
    private currentVideo: VideoData | null = null;
    private closed = false;

    private bodyEl!: HTMLElement;
    private footerRefresh!: HTMLElement;
    private footerCancel!: HTMLElement;
    private footerSelect!: HTMLElement;

    private mainEl: HTMLElement | null = null;
    private searchInput: (HTMLElement & { value?: string }) | null = null;
    /** Persistent polite region — survives body re-renders (Load More / search). */
    private liveRegion!: HTMLElement;

    private readonly debouncedSearch: (() => void) & { cancel: () => void };
    private readonly debouncedFetchVideos: (() => void) & { cancel: () => void };

    constructor(options: ExplorerDialogOptions) {
        this.options = options;
        this.currentVideo = options.video ?? null;

        this.debouncedSearch = debounce(() => this.search(), 1000);
        // Collection clicks wait 400ms like BEFORE (rapid nav shouldn't spam get-videos).
        this.debouncedFetchVideos = debounce(() => this.fetchVideos(), 400);

        this.dialog = document.createElement('pk-dialog') as PkDialogElement;
        this.dialog.classList.add('vp-explorer-dialog');
        this.dialog.setAttribute('label', Craft.t('video-picker', 'Browse videos…'));

        this.liveRegion = document.createElement('div');
        this.liveRegion.className = 'vp-sr-only';
        this.liveRegion.setAttribute('role', 'status');
        this.liveRegion.setAttribute('aria-live', 'polite');
        this.liveRegion.setAttribute('aria-atomic', 'true');
        this.dialog.appendChild(this.liveRegion);
        this.dialog.setAttribute('without-body-padding', '');
        // Sizing lives in CSS (`.vp-explorer-dialog`) so mins can cap to the viewport.

        this.bodyEl = document.createElement('div');
        this.bodyEl.className = 'vp-explorer-body';
        this.dialog.appendChild(this.bodyEl);

        this.footerRefresh = document.createElement('pk-button');
        this.footerRefresh.className = 'vp-explorer-refresh';
        this.footerRefresh.setAttribute('slot', 'footer');
        // Default variant + default size so icon-only square matches Cancel height (BEFORE `btn`).
        this.footerRefresh.setAttribute('variant', 'default');
        this.footerRefresh.setAttribute('title', Craft.t('video-picker', 'Refresh'));
        this.footerRefresh.setAttribute('aria-label', Craft.t('video-picker', 'Refresh'));
        const refreshIcon = document.createElement('pk-icon');
        refreshIcon.setAttribute('slot', 'start');
        refreshIcon.setAttribute('icon', 'arrows-rotate');
        this.footerRefresh.appendChild(refreshIcon);

        this.footerCancel = document.createElement('pk-button');
        this.footerCancel.setAttribute('slot', 'footer');
        this.footerCancel.setAttribute('variant', 'default');
        this.footerCancel.textContent = Craft.t('app', 'Cancel');

        this.footerSelect = document.createElement('pk-button');
        this.footerSelect.setAttribute('slot', 'footer');
        this.footerSelect.setAttribute('variant', 'primary');
        this.footerSelect.textContent = Craft.t('video-picker', 'Select');
        // Keyboard path for mouse double-click: Select, or Enter again on a selected card.
        this.footerSelect.setAttribute(
            'title',
            Craft.t('video-picker', 'Use the selected video (or press Enter on a selected card)'),
        );

        this.dialog.append(this.footerRefresh, this.footerCancel, this.footerSelect);
        options.mount.appendChild(this.dialog);

        this.footerRefresh.addEventListener('click', (event) => {
            event.preventDefault();
            this.fetchSources(true);
        });
        this.footerCancel.addEventListener('click', (event) => {
            event.preventDefault();
            this.close();
        });
        this.footerSelect.addEventListener('click', (event) => {
            event.preventDefault();
            this.commitSelect();
        });

        // Nested `pk-select` / overlays also emit composed `pk-open-change` + `pk-after-hide`.
        // Only tear down when *this* dialog closed — otherwise light-dismissing the source
        // select tears down the whole explorer (event.target is the select, not the dialog).
        this.dialog.addEventListener('pk-open-change', (event) => {
            if (event.target !== this.dialog) {
                return;
            }

            if (event instanceof CustomEvent && event.detail?.open === false) {
                this.finishClose();
            }
        });
        this.dialog.addEventListener('pk-after-hide', (event) => {
            if (event.target !== this.dialog) {
                return;
            }

            this.finishClose();
        });
    }

    open(): void {
        this.render();
        requestAnimationFrame(() => {
            this.dialog.open = true;
            this.fetchSources();
        });
    }

    close(): void {
        this.dialog.open = false;
    }

    private finishClose(): void {
        if (this.closed) {
            return;
        }

        this.closed = true;
        this.debouncedSearch.cancel();
        this.debouncedFetchVideos.cancel();
        window.setTimeout(() => this.dialog.remove(), 0);
        this.options.onClosed();
    }

    private commitSelect(): void {
        if (!this.currentVideo) {
            return;
        }

        this.options.onSelect(this.currentVideo);
        this.close();
    }

    private supportsSearch(): boolean {
        return this.currentSource?.supportsSearch ?? true;
    }

    private canSelect(): boolean {
        return Boolean(this.currentVideo);
    }

    private reset(): void {
        this.query = '';
        this.nextPage = null;
        this.videos = [];

        if (this.searchInput) {
            this.searchInput.value = '';
        }
    }

    private setCollection(collection: Collection | null): void {
        this.currentCollection = collection;

        // PHP sometimes sends options as [] — coerce so we can assign nextPage / q.
        if (this.currentCollection && Array.isArray(this.currentCollection.options)) {
            this.currentCollection.options = {};
        }
    }

    private isCollectionSelected(collection: Collection): boolean {
        return JSON.stringify(collection) === JSON.stringify(this.currentCollection);
    }

    /**
     * Live options bag on the current collection.
     * BEFORE mutates this in place for search `q` and pagination `nextPage` —
     * a shallow copy would drop `q` on Load More and fall back to the sidebar collection.
     */
    private collectionOptions(): Record<string, unknown> {
        if (!this.currentCollection) {
            return {};
        }

        if (!this.currentCollection.options || Array.isArray(this.currentCollection.options)) {
            this.currentCollection.options = {};
        }

        return this.currentCollection.options as Record<string, unknown>;
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    private fetchSources(refresh = false): void {
        this.loadingSources = true;
        this.sourcesError = null;
        this.render();

        const data: Record<string, unknown> = {
            fieldId: this.options.fieldId,
        };

        if (refresh) {
            data.refresh = true;
            // Refresh only the active source’s sections (others stay lightweight).
            if (this.currentSource?.handle) {
                data.hydrate = this.currentSource.handle;
            }
        }

        Craft.sendActionRequest('POST', 'video-picker/videos/get-sources', { data })
            .then(async (response: { data: Source[] }) => {
                this.sources = response.data ?? [];
                this.loadingSources = false;

                if (!this.sources.length) {
                    this.render();
                    return;
                }

                // Prefer the previously selected source if it still exists.
                const previousHandle = this.currentSource?.handle;
                this.currentSource =
                    this.sources.find((s) => s.handle === previousHandle) ?? this.sources[0];

                await this.ensureSourceSections(this.currentSource);
                this.setCollection(this.currentSource?.sections?.[0]?.collections?.[0] ?? null);
                this.fetchVideos();
            })
            .catch((error: unknown) => {
                this.sourcesError = formatErrorHtml(error);
                this.loadingSources = false;
                this.render();
            });
    }

    /**
     * get-sources only hydrates the first (or requested) source. When switching,
     * fetch that source’s sections without re-fetching every provider.
     */
    private async ensureSourceSections(source: Source | null, refresh = false): Promise<void> {
        if (!source) {
            return;
        }

        if (!refresh && (source.sections?.length ?? 0) > 0) {
            return;
        }

        const data: Record<string, unknown> = {
            fieldId: this.options.fieldId,
            hydrate: source.handle,
        };

        if (refresh) {
            data.refresh = true;
        }

        const response = await Craft.sendActionRequest('POST', 'video-picker/videos/get-sources', {
            data,
        });
        const list = (response.data ?? []) as Source[];
        const hydrated = list.find((s) => s.handle === source.handle);

        if (!hydrated) {
            return;
        }

        // Merge into the in-memory source list so the select stays consistent.
        this.sources = this.sources.map((s) => (s.handle === hydrated.handle ? hydrated : s));
        this.currentSource = hydrated;
    }

    private fetchVideos(): void {
        if (!this.currentSource) {
            return;
        }

        this.loadingVideos = true;
        this.videosError = null;
        this.render();

        const data = {
            source: this.currentSource.handle,
            method: this.currentCollection?.method ?? null,
            options: this.collectionOptions(),
            fieldId: this.options.fieldId,
        };

        Craft.sendActionRequest('POST', 'video-picker/videos/get-videos', { data })
            .then((response: { data: { videos: VideoData[]; nextPage: unknown } }) => {
                this.videos = response.data.videos ?? [];
                this.nextPage = response.data.nextPage;
            })
            .catch((error: unknown) => {
                this.videosError = formatErrorHtml(error);
            })
            .finally(() => {
                this.loadingVideos = false;
                this.render();
                this.announceVideoCount();
            });
    }

    private fetchMoreVideos(): void {
        if (!this.currentSource) {
            return;
        }

        this.loadingMore = true;
        this.videosError = null;
        this.renderMoreState();

        const options = this.collectionOptions();
        options.nextPage = this.nextPage;

        const data: {
            source: string;
            method: string | null;
            options: Record<string, unknown>;
            fieldId?: number | null;
        } = {
            source: this.currentSource.handle,
            method: this.currentCollection?.method ?? null,
            options,
            fieldId: this.options.fieldId,
        };

        // Search pagination must keep method=search when q is present (BEFORE quirk).
        if (options.q) {
            data.method = 'search';
        }

        const previousCount = this.videos.length;

        Craft.sendActionRequest('POST', 'video-picker/videos/get-videos', { data })
            .then((response: { data: { videos: VideoData[]; nextPage: unknown } }) => {
                this.videos = this.videos.concat(response.data.videos ?? []);
                this.nextPage = response.data.nextPage;
            })
            .catch((error: unknown) => {
                this.videosError = formatErrorHtml(error);
            })
            .finally(() => {
                this.loadingMore = false;
                // Full re-render replaces the grid (and kills focus). Capture before
                // rebuild so keyboard nav can continue on the same / selected card.
                const restoreId = this.captureFocusedVideoId();
                const scrollTop = this.mainEl?.scrollTop ?? 0;
                this.render();
                if (this.mainEl) {
                    this.mainEl.scrollTop = scrollTop;
                }
                this.restoreVideoCardFocus(restoreId);
                this.announceVideoCount(previousCount);
            });
    }

    private searchVideos(): void {
        if (!this.currentSource) {
            return;
        }

        this.loadingVideos = true;
        this.videosError = null;
        this.render();

        const options = this.collectionOptions();
        // Persist on the collection so fetchMoreVideos still sees `q` (BEFORE).
        options.q = this.query;

        const data = {
            source: this.currentSource.handle,
            method: 'search',
            options,
            fieldId: this.options.fieldId,
        };

        Craft.sendActionRequest('POST', 'video-picker/videos/get-videos', { data })
            .then((response: { data: { videos: VideoData[]; nextPage: unknown } }) => {
                this.videos = response.data.videos ?? [];
                this.nextPage = response.data.nextPage;
            })
            .catch((error: unknown) => {
                this.videosError = formatErrorHtml(error);
            })
            .finally(() => {
                this.loadingVideos = false;
                this.render();
                this.announceVideoCount();
            });
    }

    private search(): void {
        this.debouncedSearch.cancel();
        this.searchVideos();
    }

    private maybeLoadMore(): void {
        if (!this.nextPage || this.loadingMore || !this.mainEl) {
            return;
        }

        const { scrollHeight, scrollTop, clientHeight } = this.mainEl;

        if (scrollHeight - scrollTop <= clientHeight + 15) {
            this.fetchMoreVideos();
        }
    }

    // -------------------------------------------------------------------------
    // DOM
    // -------------------------------------------------------------------------

    private render(): void {
        this.bodyEl.replaceChildren();
        this.footerSelect.toggleAttribute('disabled', !this.canSelect());

        if (this.loadingSources) {
            this.bodyEl.appendChild(this.centeredSpinner('md', Craft.t('video-picker', 'Loading sources…')));
            return;
        }

        if (this.sourcesError) {
            const err = document.createElement('div');
            err.className = 'vp-centered error';
            err.setAttribute('role', 'alert');
            err.style.wordBreak = 'break-word';
            err.innerHTML = this.sourcesError;
            this.bodyEl.appendChild(err);
            return;
        }

        const explorer = document.createElement('div');
        explorer.className = 'vp-explorer';

        explorer.append(this.buildSidebar(), this.buildMain());
        this.bodyEl.appendChild(explorer);
    }

    /** Avoid full re-render while appending — only swap the Load More / spinner row. */
    private renderMoreState(): void {
        const more = this.bodyEl.querySelector('.vp-videos-more');

        if (!more) {
            this.render();
            return;
        }

        more.replaceChildren();

        if (this.loadingMore) {
            const spinner = document.createElement('pk-spinner');
            spinner.setAttribute('size', 'sm');
            spinner.setAttribute('aria-hidden', 'true');
            more.setAttribute('role', 'status');
            more.setAttribute('aria-live', 'polite');
            more.setAttribute('aria-busy', 'true');
            const sr = document.createElement('span');
            sr.className = 'vp-sr-only';
            sr.textContent = Craft.t('video-picker', 'Loading more videos…');
            more.append(spinner, sr);
        } else {
            more.removeAttribute('role');
            more.removeAttribute('aria-live');
            more.removeAttribute('aria-busy');
            const btn = document.createElement('pk-button');
            btn.setAttribute('variant', 'secondary');
            btn.textContent = Craft.t('video-picker', 'Load More');
            btn.addEventListener('click', () => this.fetchMoreVideos());
            more.appendChild(btn);
        }
    }

    private centeredSpinner(
        size: 'sm' | 'md' | 'lg' = 'md',
        statusText = Craft.t('video-picker', 'Loading…'),
    ): HTMLElement {
        const wrap = document.createElement('div');
        // Absolute to `.vp-explorer-main` (BEFORE `.vp-no-videos`) — not the short videos wrap.
        wrap.className = 'vp-centered';
        wrap.setAttribute('role', 'status');
        wrap.setAttribute('aria-live', 'polite');
        wrap.setAttribute('aria-busy', 'true');
        const spinner = document.createElement('pk-spinner');
        // BEFORE `vp-loading-lg` is 2rem → kit `md` (kit `lg` is 3rem and reads huge).
        spinner.setAttribute('size', size);
        spinner.setAttribute('aria-hidden', 'true');
        const sr = document.createElement('span');
        sr.className = 'vp-sr-only';
        sr.textContent = statusText;
        wrap.append(spinner, sr);

        return wrap;
    }

    private buildSidebar(): HTMLElement {
        const sidebar = document.createElement('div');
        sidebar.className = 'vp-explorer-sidebar';

        const selectWrap = document.createElement('div');
        selectWrap.className = 'vp-sidebar-select';

        const select = document.createElement('pk-select') as PkSelectElement;
        select.setAttribute('width', 'full');
        select.setAttribute('aria-label', Craft.t('video-picker', 'Source'));

        for (const source of this.sources) {
            const option = document.createElement('pk-option');
            option.setAttribute('value', source.handle);
            option.setAttribute('label', source.name);
            option.textContent = source.name;

            if (this.currentSource?.handle === source.handle) {
                option.setAttribute('selected', '');
            }

            select.appendChild(option);
        }

        if (this.currentSource) {
            select.value = this.currentSource.handle;
        }

        select.addEventListener('pk-change', () => {
            const handle = select.value;
            const next = this.sources.find((s) => s.handle === handle);

            if (!next || next === this.currentSource) {
                return;
            }

            this.currentSource = next;
            void this.ensureSourceSections(next).then(() => {
                this.setCollection(this.currentSource?.sections?.[0]?.collections?.[0] ?? null);
                this.reset();
                this.fetchVideos();
            });
        });

        selectWrap.appendChild(select);
        sidebar.appendChild(selectWrap);

        const nav = document.createElement('nav');
        const list = document.createElement('ul');

        if (this.currentSource) {
            for (const section of this.currentSource.sections ?? []) {
                const heading = document.createElement('li');
                heading.className = 'heading';
                const span = document.createElement('span');
                span.textContent = section.name;
                heading.appendChild(span);
                list.appendChild(heading);

                for (const collection of section.collections ?? []) {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '#';

                    if (this.isCollectionSelected(collection)) {
                        a.classList.add('sel');
                        a.setAttribute('aria-current', 'true');
                    }

                    if (collection.icon) {
                        a.appendChild(createCollectionIcon(collection.icon));
                    }

                    a.append(document.createTextNode(collection.name));
                    a.addEventListener('click', (event) => {
                        event.preventDefault();
                        this.setCollection(collection);
                        this.render();
                        this.debouncedFetchVideos();
                    });
                    li.appendChild(a);
                    list.appendChild(li);
                }
            }
        }

        nav.appendChild(list);
        sidebar.appendChild(nav);

        return sidebar;
    }

    /** Update selection chrome in place so the scroll position survives a card click. */
    private selectCurrentVideo(video: VideoData): void {
        this.currentVideo = video;
        this.footerSelect.toggleAttribute('disabled', !this.canSelect());

        syncVideoCardSelection(
            this.bodyEl.querySelectorAll('.vp-video-card'),
            video.id ?? null,
        );
    }

    /** Prefer the focused card; fall back to the current selection (Load More / spinner). */
    private captureFocusedVideoId(): string | null {
        const active = document.activeElement;

        if (active instanceof HTMLElement) {
            const card = active.closest('.vp-video-card');

            if (card instanceof HTMLElement && card.dataset.videoId) {
                return card.dataset.videoId;
            }
        }

        return this.currentVideo?.id != null ? String(this.currentVideo.id) : null;
    }

    private restoreVideoCardFocus(videoId: string | null): void {
        if (videoId == null || videoId === '') {
            return;
        }

        const card = this.bodyEl.querySelector(
            `.vp-video-card[data-video-id="${CSS.escape(videoId)}"]`,
        ) as HTMLElement | null;

        card?.focus({ preventScroll: true });
    }

    private buildMain(): HTMLElement {
        const main = document.createElement('div');
        main.className = 'vp-explorer-main';
        main.addEventListener('scroll', () => this.maybeLoadMore());
        this.mainEl = main;

        if (this.currentSource && this.supportsSearch()) {
            const searchWrap = document.createElement('div');
            searchWrap.className = 'vp-videos-search-wrapper';

            const search = document.createElement('pk-input') as HTMLElement & { value?: string };
            search.setAttribute('type', 'search');
            const searchLabel = Craft.t('video-picker', 'Search {source} videos…', {
                source: this.currentSource.name,
            });
            search.setAttribute('placeholder', searchLabel);
            // Accessible name must not rely on placeholder alone (A6).
            search.setAttribute('aria-label', searchLabel);
            const searchIcon = document.createElement('pk-icon');
            searchIcon.setAttribute('slot', 'start');
            searchIcon.setAttribute('icon', 'magnifying-glass');
            search.appendChild(searchIcon);
            search.value = this.query;
            this.searchInput = search;

            const applySearchName = (): void => {
                search.setAttribute('aria-label', searchLabel);
                search.shadowRoot?.querySelector('input')?.setAttribute('aria-label', searchLabel);
            };
            applySearchName();
            void customElements.whenDefined('pk-input').then(() => {
                requestAnimationFrame(applySearchName);
            });

            search.addEventListener('input', () => {
                this.query = search.value ?? '';
                this.debouncedSearch();
            });
            search.addEventListener('keydown', (event: Event) => {
                if ((event as KeyboardEvent).key === 'Enter') {
                    event.preventDefault();
                    this.search();
                }
            });

            searchWrap.appendChild(search);
            main.appendChild(searchWrap);
        }

        const videosWrap = document.createElement('div');
        videosWrap.className = 'vp-videos-wrapper';

        if (this.loadingVideos) {
            // Mount on `main` so absolute centering uses the full panel (search + body), like BEFORE.
            main.appendChild(this.centeredSpinner('md', Craft.t('video-picker', 'Loading videos…')));
        } else if (this.videosError) {
            const err = document.createElement('div');
            err.className = 'vp-centered error';
            err.setAttribute('role', 'alert');
            err.style.wordBreak = 'break-word';
            err.innerHTML = this.videosError;
            main.appendChild(err);
        } else {
            videosWrap.appendChild(
                createVideoGrid(this.videos, this.currentVideo?.id ?? null, {
                    onSelect: (video) => this.selectCurrentVideo(video),
                    onUse: (video) => {
                        this.currentVideo = video;
                        this.commitSelect();
                    },
                    onPlay: (video) => this.options.onPlay(video),
                }),
            );

            if (this.nextPage) {
                const more = document.createElement('div');
                more.className = 'vp-videos-more';

                if (this.loadingMore) {
                    const spinner = document.createElement('pk-spinner');
                    spinner.setAttribute('size', 'sm');
                    spinner.setAttribute('aria-hidden', 'true');
                    more.setAttribute('role', 'status');
                    more.setAttribute('aria-live', 'polite');
                    more.setAttribute('aria-busy', 'true');
                    const sr = document.createElement('span');
                    sr.className = 'vp-sr-only';
                    sr.textContent = Craft.t('video-picker', 'Loading more videos…');
                    more.append(spinner, sr);
                } else {
                    const btn = document.createElement('pk-button');
                    btn.setAttribute('variant', 'secondary');
                    btn.textContent = Craft.t('video-picker', 'Load More');
                    btn.addEventListener('click', () => this.fetchMoreVideos());
                    more.appendChild(btn);
                }

                videosWrap.appendChild(more);
            }

            main.appendChild(videosWrap);
        }

        return main;
    }

    /**
     * Announce total (and optional newly appended) video counts after grid updates.
     * Clears first so identical strings still fire for successive Load More calls.
     */
    private announceVideoCount(previousCount?: number): void {
        if (this.videosError) {
            return;
        }

        const total = this.videos.length;
        const message =
            previousCount != null && total > previousCount
                ? Craft.t(
                      'video-picker',
                      '{added, number} more {added, plural, =1{video} other{videos}} loaded. {total, number} total.',
                      { added: total - previousCount, total },
                  )
                : Craft.t(
                      'video-picker',
                      '{count, number} {count, plural, =1{video} other{videos}} loaded.',
                      { count: total },
                  );

        this.liveRegion.textContent = '';
        requestAnimationFrame(() => {
            this.liveRegion.textContent = message;
        });
    }
}
