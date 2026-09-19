import type { ScreenshotStep } from '@verbb/craft-screenshots/types';

export function createVideoPickerChromeCleanupStep(): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                const style = document.createElement('style');
                style.textContent = [
                    'craft-global-sidebar, footer#global-footer { display: none !important; }',
                    'craft-global-sidebar { width: 0 !important; min-width: 0 !important; flex: 0 0 0 !important; }',
                    '#global-header * { visibility: hidden !important; }',
                    '#details, #details-container, #sidebar, .meta { display: none !important; }',
                    '#page-container, #content-container, #main-content, #content { margin-left: 0 !important; max-width: none !important; }',
                    'html, body, * { scrollbar-width: none !important; }',
                    'html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar { display: none !important; }',
                ].join('\\n');
                document.head.appendChild(style);
                window.scrollTo(0, 0);
            })();
        `,
    };
}

export function createExplorerFrameStep(): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                const modal = document.querySelector('.vp-explorer-modal .vp-modal-wrap');
                if (!(modal instanceof HTMLElement)) {
                    throw new Error('The real Video Picker explorer modal was not found.');
                }

                modal.style.setProperty('width', '980px', 'important');
                modal.style.setProperty('height', '540px', 'important');
                modal.style.setProperty('max-height', '540px', 'important');
                modal.style.setProperty('margin', '0', 'important');
                modal.style.setProperty('box-shadow', 'none', 'important');
                modal.style.setProperty('border-radius', '0', 'important');
                document.querySelectorAll('.vp-modal-overlay').forEach((element) => {
                    if (element instanceof HTMLElement) {
                        element.style.setProperty('background', '#f3f7fc', 'important');
                    }
                });
            })();
        `,
    };
}

export function createSourcesFrameStep(): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                const root = document.querySelector('#sources-vue-admin-table');
                if (!(root instanceof HTMLElement)) {
                    throw new Error('The real Video Picker Sources table was not found.');
                }

                const table = root.querySelector('table.data');
                if (!(table instanceof HTMLTableElement)) {
                    throw new Error('The real Video Picker Sources data table was not found.');
                }

                const style = document.createElement('style');
                style.textContent = [
                    '#content-container { padding: 0 !important; }',
                    '#video-picker-sources-frame { position: fixed; inset: 0 auto auto 0; z-index: 100000; width: 1080px; overflow: visible; background: #fff; }',
                    '#video-picker-sources-frame table.data { width: 1080px !important; min-width: 1080px !important; margin: 0 !important; }',
                    '.craft-tooltip, [role="tooltip"], .tooltip, .tippy-box, .tippy-popper, [data-tippy-root] { display: none !important; }',
                    '#video-picker-sources-frame .copytextbtn { pointer-events: none !important; }',
                    '#video-picker-sources-frame craft-copy-attribute .visually-hidden { display: none !important; }',
                    '#video-picker-sources-frame craft-copy-attribute button span:not([data-icon]) { display: none !important; }',
                    '#video-picker-sources-frame .copytextbtn::before, #video-picker-sources-frame .copytextbtn::after { display: none !important; content: none !important; }',
                    '#sources-vue-admin-table table.data th:nth-child(5), #sources-vue-admin-table table.data td:nth-child(5) { display: table-cell !important; }',
                    '#sources-vue-admin-table table.data tbody tr, #sources-vue-admin-table table.data tbody tr:hover, #sources-vue-admin-table table.data tbody td { background: transparent !important; }',
                ].join('\\n');
                document.head.appendChild(style);

                if (document.activeElement instanceof HTMLElement) {
                    document.activeElement.blur();
                }

                document.querySelectorAll('.craft-tooltip, [role="tooltip"], .tooltip, .tippy-box, .tippy-popper, [data-tippy-root]').forEach((element) => element.remove());

                const clonedTable = table.cloneNode(true);
                if (!(clonedTable instanceof HTMLTableElement)) {
                    throw new Error('The Video Picker Sources table could not be cloned.');
                }

                clonedTable.querySelectorAll('craft-copy-attribute').forEach((element) => {
                    const input = element.querySelector('input');
                    const button = element.querySelector('button');
                    const value = element.getAttribute('value')
                        || (input instanceof HTMLInputElement ? input.value : '')
                        || button?.firstChild?.textContent?.trim()
                        || '';
                    element.replaceChildren(document.createTextNode(value));
                });

                clonedTable.querySelectorAll('.copytextbtn').forEach((element) => {
                    element.removeAttribute('title');
                    element.removeAttribute('aria-label');
                    element.removeAttribute('data-tooltip');
                });

                const frame = document.createElement('div');
                frame.id = 'video-picker-sources-frame';
                frame.appendChild(clonedTable);
                document.body.appendChild(frame);
                frame.querySelectorAll('craft-copy-attribute .visually-hidden').forEach((element) => element.remove());
                frame.querySelectorAll('.copytextbtn').forEach((element) => {
                    element.removeAttribute('title');
                    element.removeAttribute('aria-label');
                    element.removeAttribute('data-tooltip');
                });
                document.querySelectorAll('.craft-tooltip, [role="tooltip"], .tooltip, .tippy-box, .tippy-popper, [data-tippy-root]').forEach((element) => element.remove());
            })();
        `,
    };
}

export function createFieldFrameStep(): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                const field = document.querySelector('.field:has(.vp-input-component)');
                if (!(field instanceof HTMLElement)) {
                    throw new Error('The real Video Picker field was not found.');
                }

                field.style.setProperty('width', '900px', 'important');
                field.style.setProperty('max-width', '900px', 'important');
            })();
        `,
    };
}
