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
    private closed = false;

    constructor(options: PreviewDialogOptions) {
        this.onClosed = options.onClosed;

        this.dialog = document.createElement('pk-dialog') as PkDialogElement;
        this.dialog.classList.add('vp-preview-dialog');
        this.dialog.setAttribute('without-header', '');
        this.dialog.setAttribute('without-body-padding', '');
        this.dialog.setAttribute('size', 'wide');
        // Match BEFORE's ~66% viewport modal sizing via kit CSS vars.
        this.dialog.style.setProperty('--pk-dialog-width', '66vw');
        this.dialog.style.setProperty('--pk-dialog-max-width', '66vw');
        this.dialog.style.setProperty('--pk-dialog-height', '66vh');
        this.dialog.style.setProperty('--pk-dialog-min-width', '600px');
        this.dialog.style.setProperty('--pk-dialog-min-height', '400px');

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
