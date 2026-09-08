import type {
    ScreenshotStep,
    ScreenshotTarget,
    ScreenshotViewport,
} from '@verbb/docs-screenshots/types';
import {
    createCpDetailViewPreset as createBaseCpDetailViewPreset,
    createCpFocusedRegionPreset as createBaseCpFocusedRegionPreset,
    createCpFullScreenPreset as createBaseCpFullScreenPreset,
    createCpModalPreset as createBaseCpModalPreset,
} from '@verbb/docs-screenshots/presets';

// Plugin-local preset layer. Generic capture math lives in @verbb/docs-screenshots;
// this file only adds Video-Picker-specific CP chrome cleanup + framing steps. As the
// Phase 1 explorer/preview UI lands, add promo-crop steps here (model on Hyper's presets.ts).

type CpPresetOptions = {
    selector?: string;
    viewport?: ScreenshotViewport;
    padding?: NonNullable<Extract<ScreenshotTarget, { type: 'selector' }>['padding']>;
    hidePlaceholder?: boolean;
};

/** Craft CP page wash — use when the shot should read as in-CP, not a cutout. */
export const VIDEO_PICKER_CP_GRAY = '#f3f7fc';

const scrollResetSelectors = [
    'html',
    'body',
    '#content-container',
    '#main-content',
    '#content',
    '.content-pane',
];

function buildCleanupCss({ hidePlaceholder = true }: { hidePlaceholder?: boolean }): string {
    const rules = [
        'craft-global-sidebar, footer#global-footer { display: none !important; }',
        'craft-global-sidebar { width: 0 !important; min-width: 0 !important; flex: 0 0 0 !important; }',
        '#global-header * { display: none !important; }',
        '#details-container { position: static !important; }',
        'body.fixed-header #header { position: static !important; top: auto !important; }',
        'body.fixed-header #content-container { padding-top: 0 !important; }',
        '#content-container, #main-content, #content { max-width: none !important; }',
        '#content-container { padding: 24px !important; }',
        '#main-content { padding-top: 0 !important; }',
        '#page-container, #content-container, #main-content, #content, .content-pane { left: 0 !important; margin-left: 0 !important; }',
        'html, body, * { scrollbar-width: none !important; -ms-overflow-style: none !important; }',
        'html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; }',
    ];

    if (hidePlaceholder) {
        rules.push('.cp-placeholder, .placeholder { display: none !important; }');
    }

    return rules.join('\n');
}

/** Strip Craft chrome (global sidebar/header/footer, scrollbars) for focused field crops. */
export function createVideoPickerCleanupStep({ hidePlaceholder = true }: { hidePlaceholder?: boolean } = {}): ScreenshotStep {
    const css = buildCleanupCss({ hidePlaceholder });

    return {
        type: 'evaluate',
        expression: `
            (() => {
                const styleId = 'video-picker-docs-screenshot-cleanup';
                let style = document.getElementById(styleId);

                if (!(style instanceof HTMLStyleElement)) {
                    style = document.createElement('style');
                    style.id = styleId;
                    document.head.appendChild(style);
                }

                style.textContent = ${JSON.stringify(css)} + '\\n#details, #details-container, #sidebar, .meta { display: none !important; }';

                ${JSON.stringify(scrollResetSelectors)}.forEach((selector) => {
                    document.querySelectorAll(selector).forEach((element) => {
                        if (element instanceof HTMLElement) {
                            element.scrollTop = 0;
                            element.scrollLeft = 0;
                        }
                    });
                });

                window.scrollTo(0, 0);
            })();
        `,
    };
}

/**
 * Frame the open explorer on the real pk-dialog panel (header + body + footer),
 * not a faux card around `.vp-explorer` alone. Modelled on Table Maker columns-modal:
 * measure the shadow panel, exit the modal top layer, and clip to panel + CP-gray inset.
 */
export function createVideoPickerExplorerPromoCropStep({
    width = 980,
    padding = 0,
    background = VIDEO_PICKER_CP_GRAY,
}: {
    width?: number;
    padding?: number;
    background?: string;
} = {}): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                document.getElementById('video-picker-docs-screenshot-stage')?.remove();

                const dialog = document.querySelector('pk-dialog.vp-explorer-dialog');
                if (!(dialog instanceof HTMLElement)) {
                    throw new Error('Video explorer dialog not found for promo crop.');
                }

                dialog.id = 'video-picker-docs-explorer-dialog';
                dialog.style.setProperty('opacity', '1', 'important');
                dialog.style.setProperty('visibility', 'visible', 'important');
                dialog.style.setProperty('--pk-dialog-width', ${width} + 'px', 'important');
                dialog.style.setProperty('--pk-dialog-max-width', ${width} + 'px', 'important');

                const stageBackground = ${JSON.stringify(background)};
                const styleId = 'video-picker-docs-explorer-modal-css';
                let style = document.getElementById(styleId);
                if (!(style instanceof HTMLStyleElement)) {
                    style = document.createElement('style');
                    style.id = styleId;
                    document.head.appendChild(style);
                }
                style.textContent = [
                    '#video-picker-docs-explorer-dialog::part(panel) {',
                    '  max-height: none !important;',
                    '  border-radius: 0 !important;',
                    '  box-shadow: none !important;',
                    '}',
                    '#video-picker-docs-explorer-dialog::part(body) {',
                    '  max-height: none !important;',
                    '  overflow: visible !important;',
                    '}',
                    '#video-picker-docs-explorer-dialog::part(backdrop) {',
                    '  background: ' + stageBackground + ' !important;',
                    '  opacity: 1 !important;',
                    '}',
                    'html, body, #page-container, #content { background: ' + stageBackground + ' !important; }',
                    // Keep card titles flush with the thumb edge for promo crops.
                    '.vp-explorer .vp-video-card { align-items: stretch !important; }',
                    '.vp-explorer .vp-video-card-container {',
                    '  display: block !important;',
                    '  width: 100% !important;',
                    '}',
                    '.vp-explorer .vp-video-card-text {',
                    '  display: -webkit-box !important;',
                    '  text-align: start !important;',
                    '  width: 100% !important;',
                    '}',
                ].join('\\n');

                const field = document.querySelector('.field:has(.vp-input-component)');
                if (field instanceof HTMLElement) {
                    field.style.setProperty('opacity', '0', 'important');
                }

                dialog.style.setProperty('z-index', '2147483600', 'important');

                const panel =
                    dialog.shadowRoot?.querySelector('[part="panel"], .panel, dialog')
                    || dialog;

                if (!(panel instanceof HTMLElement)) {
                    throw new Error('Video explorer dialog panel not found.');
                }

                panel.style.setProperty('border-radius', '0', 'important');
                panel.style.setProperty('box-shadow', 'none', 'important');

                // Do NOT call panel.close() here — ExplorerDialog listens for
                // pk-open-change and removes the dialog from the DOM on close.

                const restClose = (root) => {
                    if (!root?.querySelectorAll) {
                        return;
                    }
                    root.querySelectorAll('button.close, [data-dialog="close"]').forEach((el) => {
                        if (el instanceof HTMLElement) {
                            el.blur();
                            el.style.setProperty('box-shadow', 'none', 'important');
                            el.style.setProperty('outline', 'none', 'important');
                        }
                    });
                };

                const paintBackdrop = (root) => {
                    if (!root?.querySelectorAll) {
                        return;
                    }
                    root.querySelectorAll('[part="backdrop"], .backdrop').forEach((el) => {
                        if (el instanceof HTMLElement) {
                            el.style.setProperty('background', stageBackground, 'important');
                            el.style.setProperty('opacity', '1', 'important');
                        }
                    });

                    if (root.adoptedStyleSheets) {
                        const sheet = new CSSStyleSheet();
                        sheet.replaceSync(
                            'dialog::backdrop { background: ' + stageBackground + ' !important; opacity: 1 !important; }',
                        );
                        root.adoptedStyleSheets = [...root.adoptedStyleSheets, sheet];
                    }
                };

                paintBackdrop(dialog.shadowRoot);
                paintBackdrop(panel.shadowRoot);

                return new Promise((resolve) => {
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            const box = panel.getBoundingClientRect();
                            const inset = ${padding};
                            const left = Math.max(0, Math.floor(box.left) - inset);
                            const top = Math.max(0, Math.floor(box.top) - inset);
                            const right = Math.ceil(box.right) + inset;
                            const bottom = Math.ceil(box.bottom) + inset;

                            if (document.activeElement instanceof HTMLElement) {
                                document.activeElement.blur();
                            }
                            restClose(dialog.shadowRoot);
                            restClose(panel);
                            restClose(panel.shadowRoot);
                            paintBackdrop(dialog.shadowRoot);
                            paintBackdrop(panel.shadowRoot);
                            if (document.body instanceof HTMLElement) {
                                document.body.setAttribute('tabindex', '-1');
                                document.body.focus({ preventScroll: true });
                            }

                            const stage = document.createElement('div');
                            stage.id = 'video-picker-docs-screenshot-stage';
                            stage.style.cssText = [
                                'position:fixed',
                                'left:' + left + 'px',
                                'top:' + top + 'px',
                                'width:' + Math.max(1, right - left) + 'px',
                                'height:' + Math.max(1, bottom - top) + 'px',
                                'pointer-events:none',
                                'z-index:2147483640',
                                'background:transparent',
                                'box-sizing:border-box',
                            ].join(';');
                            document.body.appendChild(stage);
                            resolve(true);
                        });
                    });
                });
            })();
        `,
    };
}

/**
 * Switch the open explorer to the connected docs Vimeo source.
 */
export function createSelectVimeoExplorerSourceStep(): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => new Promise((resolve) => {
                const select = document.querySelector('.vp-explorer pk-select');
                if (!(select instanceof HTMLElement)) {
                    throw new Error('Explorer source select not found.');
                }

                const chooseVimeo = () => {
                    select.setAttribute('value', 'vimeo');
                    if ('value' in select) {
                        select.value = 'vimeo';
                    }
                    select.dispatchEvent(new CustomEvent('pk-change', {
                        bubbles: true,
                        composed: true,
                        detail: { value: 'vimeo' },
                    }));
                };

                if (customElements.get('pk-select')) {
                    requestAnimationFrame(() => {
                        chooseVimeo();
                        resolve(true);
                    });
                    return;
                }

                customElements.whenDefined('pk-select').then(() => {
                    requestAnimationFrame(() => {
                        chooseVimeo();
                        resolve(true);
                    });
                });
            }))();
        `,
    };
}
export function createPrepareVideoPickerSourcesIndexStep(): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                document.getElementById('video-picker-docs-sources-screenshot-stage')?.remove();

                const root = document.querySelector('#sources-vue-admin-table');
                if (!(root instanceof HTMLElement)) {
                    throw new Error('Sources VueAdminTable root not found.');
                }

                const table = root.querySelector('table');
                if (!(table instanceof HTMLTableElement)) {
                    throw new Error('Sources table not found.');
                }

                const styleId = 'video-picker-docs-sources-index-css';
                let style = document.getElementById(styleId);
                if (!(style instanceof HTMLStyleElement)) {
                    style = document.createElement('style');
                    style.id = styleId;
                    document.head.appendChild(style);
                }
                style.textContent = [
                    '#content-container { padding: 0 !important; }',
                    // Shrink-wrap every wrapper so the crop ends at the Provider column
                    // instead of stretching the table across the full content pane.
                    '#sources-vue-admin-table, #sources-vue-admin-table .vue-admin-table__table-wrapper, #sources-vue-admin-table .dataTables_wrapper { height: auto !important; min-height: 0 !important; overflow: visible !important; width: max-content !important; max-width: none !important; }',
                    '#content, .content-pane { min-height: 0 !important; height: auto !important; overflow: visible !important; }',
                    // Drop reorder/delete columns — saves width without clipping data columns.
                    '#sources-vue-admin-table table.data thead tr th:nth-last-child(-n+2),',
                    '#sources-vue-admin-table table.data tbody tr td:nth-last-child(-n+2) { display: none !important; }',
                    // Auto layout + max-content sizes each column to its own text, so
                    // nothing is squished and no dead space trails the last column.
                    '#sources-vue-admin-table table.data { table-layout: auto !important; width: max-content !important; min-width: 0 !important; max-width: none !important; }',
                    '#sources-vue-admin-table table.data th,',
                    '#sources-vue-admin-table table.data td { padding-left: 15px !important; padding-right: 15px !important; white-space: nowrap !important; }',
                    // Capture runs with a live cursor over the table; kill hover/selected
                    // tints so no single row reads as highlighted in the docs shot.
                    '#sources-vue-admin-table table.data tbody tr,',
                    '#sources-vue-admin-table table.data tbody tr:hover,',
                    '#sources-vue-admin-table table.data tbody tr.sel,',
                    '#sources-vue-admin-table table.data tbody tr:hover td,',
                    '#sources-vue-admin-table table.data tbody tr td { background-color: transparent !important; }',
                    '#sources-vue-admin-table .vp-provider { gap: 6px !important; }',
                    '#sources-vue-admin-table .vp-provider-label { white-space: nowrap !important; }',
                ].join('\\n');

                // Park the pointer off the table so Vue does not re-apply a hover class.
                for (const row of table.querySelectorAll('tbody tr')) {
                    row.classList.remove('sel', 'hover');
                }

                window.scrollTo(0, 0);
                [root, table, root.closest('#content')].forEach((node) => {
                    if (node instanceof HTMLElement) {
                        node.scrollLeft = 0;
                        node.scrollTop = 0;
                    }
                });
            })();
        `,
    };
}

/**
 * Stage the Video field (label + preview) without Craft entry chrome.
 */
export function createVideoPickerFieldPromoCropStep({
    width = 720,
    padding = 20,
    background = '#ffffff',
}: {
    width?: number;
    padding?: number;
    background?: string;
} = {}): ScreenshotStep {
    return {
        type: 'evaluate',
        expression: `
            (() => {
                document.getElementById('video-picker-docs-screenshot-stage')?.remove();

                const field = document.querySelector('.field:has(.vp-input-component)');
                if (!(field instanceof HTMLElement)) {
                    throw new Error('Video Picker field not found for promo crop.');
                }

                const inset = ${padding};
                const frameWidth = ${width};
                const stageBackground = ${JSON.stringify(background)};

                const stage = document.createElement('div');
                stage.id = 'video-picker-docs-screenshot-stage';
                stage.style.cssText = [
                    'position:fixed',
                    'left:0',
                    'top:0',
                    'width:' + frameWidth + 'px',
                    'z-index:2147483640',
                    'background:' + stageBackground,
                    'padding:0',
                    'box-sizing:border-box',
                    'overflow:hidden',
                ].join(';');

                const frame = document.createElement('div');
                frame.style.cssText = [
                    'background:#fff',
                    'border-radius:8px',
                    'box-sizing:border-box',
                    'padding:20px',
                    'overflow:hidden',
                ].join(';');

                frame.appendChild(field);
                field.style.margin = '0';
                field.style.maxWidth = '100%';
                stage.appendChild(frame);
                document.body.appendChild(stage);

                const box = frame.getBoundingClientRect();
                stage.style.width = Math.ceil(box.width) + 'px';
                stage.style.height = Math.ceil(box.height + inset * 2) + 'px';
                if (inset > 0) {
                    stage.style.padding = inset + 'px';
                }

                document.documentElement.style.background = stageBackground;
                document.body.style.background = stageBackground;
                Array.from(document.body.children).forEach((child) => {
                    if (child instanceof HTMLElement && child.id !== 'video-picker-docs-screenshot-stage') {
                        child.style.setProperty('display', 'none', 'important');
                    }
                });
            })();
        `,
    };
}

export function createCpFocusedRegionPreset(options: CpPresetOptions = {}) {
    const preset = createBaseCpFocusedRegionPreset(options);

    return {
        ...preset,
        steps: [
            createVideoPickerCleanupStep({ hidePlaceholder: options.hidePlaceholder }),
            ...preset.steps,
        ] satisfies ScreenshotStep[],
    };
}

export function createCpFullScreenPreset(options: CpPresetOptions = {}) {
    const preset = createBaseCpFullScreenPreset(options);

    return {
        ...preset,
        steps: [
            createVideoPickerCleanupStep({ hidePlaceholder: options.hidePlaceholder }),
            ...preset.steps,
        ] satisfies ScreenshotStep[],
    };
}

export function createCpModalPreset(options: CpPresetOptions = {}) {
    return createBaseCpModalPreset(options);
}

export function createCpDetailViewPreset(options: CpPresetOptions = {}) {
    const preset = createBaseCpDetailViewPreset(options);

    return {
        ...preset,
        steps: [
            createVideoPickerCleanupStep({ hidePlaceholder: options.hidePlaceholder }),
            ...preset.steps,
        ] satisfies ScreenshotStep[],
    };
}
