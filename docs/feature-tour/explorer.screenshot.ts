import { defineScreenshotScenario } from '@verbb/docs-screenshots/api';
import { seedVideoPickerDocsFixture } from '../.screenshots/video-picker/fixtures';
import {
    createSelectVimeoExplorerSourceStep,
    createVideoPickerCleanupStep,
    createVideoPickerExplorerPromoCropStep,
} from '../.screenshots/video-picker/presets';

let entryEditRoute = '/admin/entries';

export default defineScreenshotScenario({
    id: 'feature-tour-explorer',
    output: '_screenshots/feature-tour/explorer.png',
    route: () => entryEditRoute,
    viewport: {
        width: 1320,
        height: 900,
        deviceScaleFactor: 2,
    },
    async setup(context) {
        const fixture = await seedVideoPickerDocsFixture(context);
        entryEditRoute = fixture.entryEditRoute;
    },
    waitFor: [
        { type: 'selector', selector: 'pk-dialog.vp-explorer-dialog', state: 'attached', timeout: 60000 },
        { type: 'selector', selector: '.vp-explorer .vp-video-card', state: 'visible', timeout: 60000 },
        { type: 'selector', selector: 'pk-dialog.vp-explorer-dialog > .vp-explorer-refresh', state: 'attached', timeout: 30000 },
        { type: 'selector', selector: '#video-picker-docs-screenshot-stage', state: 'visible', timeout: 60000 },
    ],
    preSteps: [
        createVideoPickerCleanupStep(),
        { type: 'click', selector: 'pk-button.vp-browse, .vp-browse' },
        {
            type: 'wait',
            waitFor: {
                type: 'selector',
                selector: '.vp-explorer',
                state: 'attached',
                timeout: 60000,
            },
        },
        createSelectVimeoExplorerSourceStep(),
        {
            type: 'wait',
            waitFor: {
                type: 'selector',
                selector: '.vp-explorer .vp-video-card',
                state: 'visible',
                timeout: 60000,
            },
        },
        {
            type: 'evaluate',
            expression: `
                (() => new Promise((resolve, reject) => {
                    const deadline = Date.now() + 30000;
                    const tick = () => {
                        const cards = document.querySelectorAll('.vp-explorer .vp-video-card');
                        const imgs = Array.from(document.querySelectorAll('.vp-explorer .vp-video-thumb-image'))
                            .filter((el) => el instanceof HTMLImageElement);
                        const ready = cards.length >= 6
                            && imgs.length >= 6
                            && imgs.every((img) => img.complete && img.naturalWidth > 0);
                        if (ready) {
                            resolve(true);
                            return;
                        }
                        if (Date.now() > deadline) {
                            reject(new Error('Timed out waiting for Vimeo explorer demo videos.'));
                            return;
                        }
                        requestAnimationFrame(tick);
                    };
                    tick();
                }))();
            `,
        },
        createVideoPickerExplorerPromoCropStep({ width: 980, padding: 0 }),
        { type: 'wait', waitFor: { type: 'timeout', ms: 400 } },
    ],
    steps: [],
    target: {
        type: 'selector',
        selector: '#video-picker-docs-screenshot-stage',
        padding: 0,
    },
    caption: 'Video explorer browsing Vimeo uploads with folders and a video grid.',
    intent: 'Show the explorer modal with Vimeo selected, sidebar collections, and demo videos.',
});
