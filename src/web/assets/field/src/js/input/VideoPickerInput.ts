// Video Picker field input — Plugin Kit v2 web components.
//
// Translates `video-picker-before/.../VideoPickerInput.vue` (+ Explorer / Preview /
// VideoCard / VideoThumb) onto imperative DOM + `pk-*`. Plugin Kit wins on chrome;
// AJAX endpoints and the posted URL value contract stay the same.

import { ExplorerDialog } from '../explorer/ExplorerDialog.js';
import { PreviewDialog } from '../preview/PreviewDialog.js';
import { formatErrorHtml } from '../utils/ajaxErrors.js';
import { formatPlays, formatTimeAgo } from '../utils/formatVideo.js';
import { createVideoThumb, type VideoData } from '../video/VideoCard.js';

export type VideoValue = VideoData;

export interface VideoPickerSettings {
    inputId: string;
    inputName: string;
    fieldId?: number | null;
    value?: VideoValue | null;
    showExplorer?: boolean;
    showPreview?: boolean;
    /** Empty URL control hint; falls back to “Enter a video URL” when unset. */
    placeholder?: string | null;
    sourceCount?: number;
    sourceWarning?: string;
}

type PkInputElement = HTMLElement & { value: string; focus?: () => void };

const parseJson = <T>(raw: string | null | undefined, fallback: T): T => {
    if (!raw) {
        return fallback;
    }

    try {
        return JSON.parse(raw) as T;
    } catch {
        return fallback;
    }
};

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
    Boolean(value) && typeof value === 'object' && !Array.isArray(value);

export class VideoPickerInput {
    private readonly root: HTMLElement;
    private readonly settings: VideoPickerSettings;

    private loadingVideo = false;
    private videoError: string | null = null;
    private videoUrl: string | null = null;
    private enableExplorer = true;
    private enablePreview = true;
    private currentVideo: VideoData | null = null;

    private wrap!: HTMLElement;
    /** Visible chrome only — nameless so it never participates in FormObserver serialize. */
    private urlInput!: PkInputElement;
    /** SSR native control Craft serializes / posts (name = inputName). */
    private valueInput!: HTMLInputElement;
    private previewHost!: HTMLElement;
    private explorerOpen = false;
    private previewOpen = false;

    constructor(root: HTMLElement) {
        this.root = root;
        this.settings = parseJson<VideoPickerSettings>(root.getAttribute('data-settings'), {
            inputId: '',
            inputName: '',
        });
    }

    init(): void {
        this.enableExplorer = Boolean(this.settings.showExplorer);
        this.enablePreview = Boolean(this.settings.showPreview);
        this.currentVideo = this.settings.value ?? null;
        this.videoUrl = this.currentVideo?.url ?? null;

        this.buildDom();
        this.bindEvents();
        this.syncPreview();
    }

    // -------------------------------------------------------------------------
    // DOM
    // -------------------------------------------------------------------------

    private buildDom(): void {
        // Keep the Twig-rendered named hidden — recreating it after FormObserver’s
        // first serialize looks like a form change (false provisional draft).
        this.adoptValueInput();

        [...this.root.children].forEach((child) => {
            if (child === this.valueInput) {
                return;
            }

            child.remove();
        });

        this.wrap = document.createElement('div');
        this.wrap.className = 'vp-input';

        const urlRow = document.createElement('div');
        urlRow.className = 'vp-input-row';

        // Nameless on purpose — Craft FormObserver uses jQuery serialize, which
        // ignores ElementInternals / form-associated custom elements.
        this.urlInput = document.createElement('pk-input') as PkInputElement;
        this.urlInput.setAttribute('placeholder', this.placeholder);
        this.associateCraftFieldLabel();

        // Display only — posted value stays on the SSR hidden unless it actually differs.
        const displayUrl = this.valueInput.value || this.videoUrl || '';
        this.videoUrl = displayUrl || null;

        if (displayUrl) {
            this.urlInput.value = displayUrl;
        }

        urlRow.appendChild(this.urlInput);

        if (this.hasSources() && this.enableExplorer) {
            // Link chrome + absolute placement matches BEFORE’s in-field “Browse videos…” control.
            // Text won’t fit pk-input’s ≤1.25rem end adornment, so overlay instead of slot=end.
            const browse = document.createElement('pk-button');
            browse.className = 'vp-browse';
            browse.setAttribute('variant', 'link');
            browse.setAttribute('size', 'sm');
            browse.textContent = Craft.t('video-picker', 'Browse videos…');
            browse.addEventListener('click', (event) => {
                event.preventDefault();
                this.openExplorer();
            });
            urlRow.appendChild(browse);
            urlRow.classList.add('has-browse');
        }

        this.wrap.appendChild(urlRow);

        this.previewHost = document.createElement('div');
        this.previewHost.className = 'vp-preview-host';
        this.wrap.appendChild(this.previewHost);

        // Value input first so serialize order stays stable if other named nodes appear later.
        this.root.append(this.valueInput, this.wrap);
        this.syncBusyState();
    }

    /** Reflect in-flight URL fetch without polite live spam (fetch runs on every keystroke). */
    private syncBusyState(): void {
        this.wrap.toggleAttribute('aria-busy', this.loadingVideo);
        this.previewHost.toggleAttribute('aria-busy', this.loadingVideo);
    }

    /** Field setting when set; otherwise the historical default URL hint. */
    private get placeholder(): string {
        const raw = this.settings.placeholder;
        if (typeof raw === 'string' && raw.trim()) {
            return raw.trim();
        }

        return Craft.t('video-picker', 'Enter a video URL');
    }

    /**
     * Wire Craft’s field <label> to pk-input.
     * `label[for]` must match the host id (click-to-focus). The shadow <input> also
     * needs aria-label — pk-input does not forward host labelling into its textbox,
     * so without this the accessible name falls back to the placeholder.
     */
    private associateCraftFieldLabel(): void {
        const field = this.root.closest('.field');
        const label = field?.querySelector<HTMLLabelElement>('.heading label');
        const forId = label?.getAttribute('for') || this.settings.inputId;

        if (forId) {
            this.urlInput.id = forId;
        }

        const name = label?.textContent?.replace(/\s+/g, ' ').trim();
        if (!name) {
            return;
        }

        const applyName = (): void => {
            this.urlInput.setAttribute('aria-label', name);
            this.urlInput.shadowRoot
                ?.querySelector('input')
                ?.setAttribute('aria-label', name);
        };

        applyName();
        void customElements.whenDefined('pk-input').then(() => {
            requestAnimationFrame(applyName);
        });
    }

    /**
     * Prefer Twig `data-video-picker-value`. Fallback create is only for remounts
     * without SSR — that path can still false-dirty ElementEditor, so keep it rare.
     */
    private adoptValueInput(): void {
        let input = this.root.querySelector<HTMLInputElement>('input[data-video-picker-value]');

        if (!input) {
            // Remount without SSR — name must already be fully namespaced
            // (namespaceInputs already ran). Prefer keeping SSR so this stays rare.
            input = document.createElement('input');
            input.type = 'hidden';
            input.dataset.videoPickerValue = '';
            input.name = this.settings.inputName;
            input.value = this.videoUrl ?? '';
            this.root.appendChild(input);
        }

        // Never rewrite `name` on an adopted SSR input — Craft already namespaced it.

        this.valueInput = input;
    }

    /**
     * Write the posted URL only when it changes. Programmatic paths (explorer /
     * remove) also fire a bubbling `input` so FormObserver re-serializes — property
     * writes alone do not update the `value` attribute MutationObserver watches.
     * Typing already emits composed `input` from pk-input; skip a second dispatch.
     */
    private setPostedUrl(url: string | null, options: { notifyForm?: boolean } = {}): void {
        const next = url ?? '';
        this.videoUrl = next || null;

        if (this.urlInput.value !== next) {
            this.urlInput.value = next;
        }

        if (this.valueInput.value === next) {
            return;
        }

        this.valueInput.value = next;

        if (options.notifyForm) {
            this.valueInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    private bindEvents(): void {
        // No debounce — BEFORE fetches on every input event.
        this.urlInput.addEventListener('input', () => {
            // Sync hidden before FormObserver’s delayed checkForm (100–1000ms).
            // Do not notifyForm — pk-input’s composed input already woke the observer.
            this.setPostedUrl(this.urlInput.value || null);
            this.fetchVideo();
        });
    }

    private hasSources(): boolean {
        return (this.settings.sourceCount ?? 0) > 0;
    }

    // -------------------------------------------------------------------------
    // Preview card
    // -------------------------------------------------------------------------

    private collectErrors(): string[] {
        const all: string[] = [];

        if (this.videoError) {
            all.push(this.videoError);
        }

        if (this.currentVideo && isPlainObject(this.currentVideo.errors)) {
            Object.values(this.currentVideo.errors).forEach((errors) => {
                if (Array.isArray(errors)) {
                    errors.forEach((error) => all.push(String(error)));
                }
            });
        }

        return all;
    }

    private syncPreview(): void {
        this.previewHost.replaceChildren();
        this.syncBusyState();

        if (!this.hasSources()) {
            const warning = document.createElement('div');
            warning.className = 'vp-source-warning';
            warning.setAttribute('role', 'status');
            warning.innerHTML = `<span class="warning with-icon">${this.settings.sourceWarning ?? ''}</span>`;
            this.previewHost.appendChild(warning);
            return;
        }

        if (this.loadingVideo) {
            const loading = document.createElement('div');
            loading.className = 'vp-single-video-container';
            // aria-busy on the host announces progress; keep the spinner decorative.
            const spinner = document.createElement('pk-spinner');
            // BEFORE `.vp-loading` ::after is 1rem — kit `xs`. (`sm`/omit = 1.5rem, too large here.)
            spinner.setAttribute('size', 'xs');
            spinner.setAttribute('aria-hidden', 'true');
            loading.appendChild(spinner);
            this.previewHost.appendChild(loading);
            return;
        }

        const errors = this.collectErrors();

        if (errors.length) {
            const errWrap = document.createElement('div');
            errWrap.className = 'error vp-single-video-errors';
            errWrap.setAttribute('role', 'alert');

            for (const error of errors) {
                const row = document.createElement('div');
                row.innerHTML = error;
                errWrap.appendChild(row);
            }

            this.previewHost.appendChild(errWrap);
            return;
        }

        if (!this.currentVideo || !this.enablePreview) {
            return;
        }

        const container = document.createElement('div');
        container.className = 'vp-single-video-container';

        const thumbWrap = document.createElement('div');
        thumbWrap.className = 'vp-single-video-thumb';
        thumbWrap.appendChild(
            createVideoThumb(this.currentVideo, {
                onPlay: () => this.openPreview(this.currentVideo!),
                playInTabOrder: true,
            }),
        );

        const meta = document.createElement('div');
        meta.className = 'vp-single-video-meta';

        const title = document.createElement('div');
        title.className = 'vp-single-video-title';
        const titleLink = document.createElement('a');
        titleLink.href = this.currentVideo.url ?? '#';
        titleLink.target = '_blank';
        titleLink.rel = 'noopener noreferrer';
        const titleText = this.currentVideo.title ?? '';
        titleLink.textContent = titleText;
        titleLink.setAttribute('aria-label', this.newTabLabel(titleText || Craft.t('video-picker', 'Video')));
        title.appendChild(titleLink);

        const details = document.createElement('div');
        details.className = 'vp-single-video-meta-details';

        const author = document.createElement('a');
        author.className = 'vp-single-video-meta-author';
        author.href = this.currentVideo.authorUrl ?? '#';
        author.target = '_blank';
        author.rel = 'noopener noreferrer';
        const authorText = this.currentVideo.authorName ?? '';
        author.textContent = authorText;
        if (authorText) {
            author.setAttribute('aria-label', this.newTabLabel(authorText));
        }

        const plays = document.createElement('span');
        plays.className = 'vp-single-video-meta-plays';
        plays.textContent = formatPlays(this.currentVideo.plays);

        const date = document.createElement('span');
        date.className = 'vp-single-video-meta-date';
        date.textContent = formatTimeAgo(this.currentVideo.date);

        details.append(
            author,
            document.createTextNode(' • '),
            plays,
            document.createTextNode(' • '),
            date,
        );

        const description = document.createElement('div');
        description.className = 'vp-single-video-meta-description';
        description.textContent = this.currentVideo.description ?? '';

        const buttons = document.createElement('div');
        buttons.className = 'vp-single-video-buttons';

        // BEFORE Rotate/Remove paths (registered as vp-refresh / vp-remove) — heavier
        // filled art than kit arrows-rotate / xmark; explorer still uses kit glyphs.
        const refresh = this.iconButton('vp-refresh', Craft.t('video-picker', 'Refresh'));
        refresh.addEventListener('click', (event) => {
            event.preventDefault();
            this.fetchVideo(true);
        });

        const remove = this.iconButton('vp-remove', Craft.t('video-picker', 'Remove'));
        remove.addEventListener('click', (event) => {
            event.preventDefault();
            this.removeVideo();
        });

        buttons.append(refresh, remove);
        meta.append(title, details, description, buttons);
        container.append(thumbWrap, meta);
        this.previewHost.appendChild(container);
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    // Icon-only square control. `transparent` keeps rest state empty and uses the
    // kit’s regular slate hover fill (`variant="none"` opts out of hover entirely).
    private iconButton(iconName: string, label: string): HTMLElement {
        const button = document.createElement('pk-button');
        button.setAttribute('variant', 'transparent');
        button.setAttribute('size', 'sm');
        button.setAttribute('title', label);
        button.setAttribute('aria-label', label);

        const glyph = document.createElement('pk-icon');
        glyph.setAttribute('slot', 'start');
        glyph.setAttribute('icon', iconName);
        button.appendChild(glyph);

        return button;
    }

    /** Visible link text plus an AT cue that target=_blank opens a new tab (A14). */
    private newTabLabel(label: string): string {
        return Craft.t('video-picker', '{label} (opens in a new tab)', { label });
    }

    private removeVideo(): void {
        this.currentVideo = null;
        this.videoError = null;
        this.setPostedUrl(null, { notifyForm: true });
        this.syncPreview();
    }

    private openExplorer(): void {
        if (this.explorerOpen || !this.enableExplorer) {
            return;
        }

        this.explorerOpen = true;

        new ExplorerDialog({
            mount: this.root,
            fieldId: this.settings.fieldId,
            video: this.currentVideo,
            onSelect: (video) => {
                this.currentVideo = video;
                this.videoError = null;
                this.setPostedUrl(video.url ?? null, { notifyForm: true });
                this.syncPreview();
            },
            onPlay: (video) => this.openPreview(video),
            onClosed: () => {
                this.explorerOpen = false;
            },
        }).open();
    }

    private openPreview(video: VideoData): void {
        if (this.previewOpen || !this.enablePreview) {
            return;
        }

        this.previewOpen = true;

        new PreviewDialog({
            mount: this.root,
            video,
            onClosed: () => {
                this.previewOpen = false;
            },
        }).open();
    }

    private fetchVideo(refresh = false): void {
        if (!this.hasSources()) {
            return;
        }

        this.loadingVideo = true;
        this.currentVideo = null;
        this.videoError = null;
        this.syncPreview();

        if (!this.videoUrl) {
            this.loadingVideo = false;
            this.syncPreview();
            return;
        }

        const data: Record<string, unknown> = {
            url: this.videoUrl,
            fieldId: this.settings.fieldId,
        };

        if (refresh) {
            data.refresh = true;
        }

        Craft.sendActionRequest('POST', 'video-picker/videos/get-video', { data })
            .then((response: { data: VideoData & { error?: string } }) => {
                if (response.data.error) {
                    this.videoError = response.data.error;
                } else {
                    this.currentVideo = response.data;
                }
            })
            .catch((error: unknown) => {
                this.videoError = formatErrorHtml(error);
            })
            .finally(() => {
                this.loadingVideo = false;
                this.syncPreview();
            });
    }
}
