import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedVideoPickerFixture } from '../../support/fixtures';
import { createFieldFrameStep, createVideoPickerChromeCleanupStep } from '../../support/presets';

let entryEditRoute = '/admin/entries';

export default defineScreenshotScenario({
    id: 'video-picker-feature-tour-field',
    output: 'feature-tour/video-picker-field.png',
    route: () => entryEditRoute,
    viewport: { width: 1180, height: 760, deviceScaleFactor: 2 },
    async setup(context) {
        const fixture = await seedVideoPickerFixture(context);
        entryEditRoute = fixture.entryEditRoute;
    },
    waitFor: [
        { type: 'selector', selector: '.vp-input-component', state: 'visible', timeout: 30000 },
        { type: 'selector', selector: '.vp-single-video-container', state: 'visible', timeout: 30000 },
    ],
    preSteps: [createVideoPickerChromeCleanupStep(), createFieldFrameStep()],
    steps: [{ type: 'wait', waitFor: { type: 'timeout', ms: 300 } }],
    target: {
        type: 'selector',
        selector: '.field:has(.vp-input-component)',
        padding: { top: 12, right: -3, bottom: 12, left: 0 },
    },
    caption: 'A real Video Picker field with a resolved Vimeo video in Craft 5.',
    intent: 'Show URL input, browse control and the selected video card rendered by the plugin.',
});
