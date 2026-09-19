import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedVideoPickerFixture } from '../../support/fixtures';
import { createSourcesFrameStep, createVideoPickerChromeCleanupStep } from '../../support/presets';

let sourcesRoute = '/admin/video-picker/sources';

export default defineScreenshotScenario({
    id: 'video-picker-feature-tour-sources',
    output: 'feature-tour/video-picker-sources.png',
    route: () => sourcesRoute,
    viewport: { width: 1200, height: 600, deviceScaleFactor: 2 },
    async setup(context) {
        const fixture = await seedVideoPickerFixture(context);
        sourcesRoute = fixture.sourcesRoute;
    },
    waitFor: [
        { type: 'selector', selector: '#sources-vue-admin-table table tbody tr', state: 'visible', timeout: 30000 },
        { type: 'text', text: 'YouTube Tutorials', timeout: 30000 },
    ],
    preSteps: [createVideoPickerChromeCleanupStep(), createSourcesFrameStep()],
    steps: [{ type: 'wait', waitFor: { type: 'timeout', ms: 200 } }],
    target: { type: 'selector', selector: '#video-picker-sources-frame', padding: 0 },
    caption: 'Connected Vimeo and multiple YouTube sources in Video Picker’s Craft 5 source index.',
    intent: 'Capture the real Video Picker source table with deterministic connected fixtures.',
});
