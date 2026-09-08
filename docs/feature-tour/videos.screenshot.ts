import { defineScreenshotScenario } from '@verbb/docs-screenshots/api';
import { seedVideoPickerDocsFixture } from '../.screenshots/video-picker/fixtures';
import {
    createVideoPickerCleanupStep,
    createVideoPickerFieldPromoCropStep,
} from '../.screenshots/video-picker/presets';

let entryEditRoute = '/admin/entries';

export default defineScreenshotScenario({
    id: 'feature-tour-videos',
    output: '_screenshots/feature-tour/videos.png',
    route: () => entryEditRoute,
    viewport: {
        width: 1100,
        height: 820,
        deviceScaleFactor: 2,
    },
    async setup(context) {
        const fixture = await seedVideoPickerDocsFixture(context);
        entryEditRoute = fixture.entryEditRoute;
    },
    waitFor: [
        { type: 'selector', selector: '.vp-input-component', state: 'visible', timeout: 30000 },
        { type: 'selector', selector: '.vp-single-video-container, .vp-preview-host', state: 'visible', timeout: 30000 },
    ],
    preSteps: [
        createVideoPickerCleanupStep(),
        {
            type: 'wait',
            waitFor: {
                type: 'selector',
                selector: '.vp-single-video-container .vp-video-thumb-image',
                state: 'attached',
                timeout: 30000,
            },
        },
        {
            type: 'evaluate',
            expression: `
                (() => new Promise((resolve) => {
                    const deadline = Date.now() + 20000;
                    const tick = () => {
                        const img = document.querySelector('.vp-single-video-container .vp-video-thumb-image');
                        const ready = img instanceof HTMLImageElement
                            && img.complete
                            && img.naturalWidth > 0;
                        if (ready || Date.now() > deadline) {
                            resolve(true);
                            return;
                        }
                        requestAnimationFrame(tick);
                    };
                    tick();
                }))();
            `,
        },
        createVideoPickerFieldPromoCropStep({ padding: 0, width: 720, background: '#ffffff' }),
        { type: 'wait', waitFor: { type: 'timeout', ms: 300 } },
    ],
    steps: [],
    target: {
        type: 'selector',
        selector: '#video-picker-docs-screenshot-stage',
        padding: 0,
    },
    caption: 'Video Picker field with URL input and selected video preview.',
    intent: 'Show a selected video preview on the field input.',
});
