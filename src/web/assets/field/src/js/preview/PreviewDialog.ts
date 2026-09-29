import type { VideoData } from '../video/VideoCard.js';

type PkDialogElement = HTMLElement & { open: boolean };

export type PreviewDialogOptions = {
    mount: HTMLElement;
    video: VideoData;
    onClosed: () => void;
};

/**
 * Preview embed dialog — `pk-dialog` without header, black body, raw `embedHtml`.
 * Tear-down on `pk-open-change` mirrors Hyper's controlled dialog lifecycle.
 */
export class PreviewDialog {
    private readonly dialog: PkDialogElement;
    private readonly onClosed: () => void;
    private readonly accessibleName: string;
    private closed = false;

    constructor(options: PreviewDialogOptions) {
        this.onClosed = options.onClosed;
        // without-header skips the visible title — still name the panel for AT.
        this.accessibleName =
            options.video.title?.trim()
            || Craft.t('video-picker', 'Video preview');

        this.dialog = document.createElement('pk-dialog') as PkDialogElement;
        this.dialog.classList.add('vp-preview-dialog');
        this.dialog.setAttribute('without-header', '');
        this.dialog.setAttribute('without-body-padding', '');
        // Sizing lives in CSS (`.vp-preview-dialog`) so mins can cap to the viewport.
        this.dialog.setAttribute('label', this.accessibleName);
        this.dialog.setAttribute('aria-label', this.accessibleName);

        const body = document.createElement('div');
        body.className = 'vp-preview-body';

        const loading = document.createElement('div');
        loading.className = 'vp-centered';
        const spinner = document.createElement('pk-spinner');
        spinner.setAttribute('size', 'lg');
        loading.appendChild(spinner);
        body.appendChild(loading);

        if (options.video.embedHtml) {
            const embed = document.createElement('div');
            embed.className = 'vp-preview-embed';
            embed.innerHTML = options.video.embedHtml;
            body.appendChild(embed);
        }

        this.dialog.appendChild(body);
        options.mount.appendChild(this.dialog);

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
        // Defer so the element upgrades / first paint before show (Vue used $nextTick).
        requestAnimationFrame(() => {
            // Name the native <dialog> too — without-header does not render `label` as a heading.
            this.dialog.shadowRoot
                ?.querySelector('dialog')
                ?.setAttribute('aria-label', this.accessibleName);
            this.dialog.open = true;
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
        window.setTimeout(() => this.dialog.remove(), 0);
        this.onClosed();
    }
}
