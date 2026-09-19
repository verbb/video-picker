import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedVideoPickerFixture } from '../../support/fixtures';
import { createExplorerFrameStep, createVideoPickerChromeCleanupStep } from '../../support/presets';

let entryEditRoute = '/admin/entries';

export default defineScreenshotScenario({
    id: 'video-picker-feature-tour-explorer',
    output: 'feature-tour/video-picker-explorer.png',
    route: () => entryEditRoute,
    viewport: { width: 1320, height: 900, deviceScaleFactor: 2 },
    async setup(context) {
        const fixture = await seedVideoPickerFixture(context);
        entryEditRoute = fixture.entryEditRoute;
    },
    waitFor: [
        { type: 'selector', selector: '.vp-input-component', state: 'visible', timeout: 30000 },
    ],
    steps: [
        createVideoPickerChromeCleanupStep(),
        { type: 'click', selector: '.vp-explorer-btn' },
        { type: 'wait', waitFor: { type: 'selector', selector: '.vp-explorer-modal .vp-video-card', state: 'visible', timeout: 30000 } },
        createExplorerFrameStep(),
        { type: 'wait', waitFor: { type: 'timeout', ms: 300 } },
    ],
    target: { type: 'selector', selector: '.vp-explorer-modal .vp-modal-wrap', padding: 0 },
    caption: 'Video Picker’s explorer browsing a connected Vimeo library in Craft 5.',
    intent: 'Capture the real Video Picker explorer with representative local provider data.',
});
