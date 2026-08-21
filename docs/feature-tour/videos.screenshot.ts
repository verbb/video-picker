import { defineScreenshotScenario } from '@verbb/docs-screenshots/api';
import { seedVideoPickerDocsFixture } from '../.screenshots/video-picker/fixtures';
import { createVideoPickerCleanupStep } from '../.screenshots/video-picker/presets';

// Starter scenario — captures the seeded Video Picker field settings page. Retarget the
// route/selector at the explorer/preview UI once the Phase 1 work is built out.
let settingsRoute = '/admin/settings/fields';

export default defineScreenshotScenario({
    id: 'feature-tour-videos',
    output: '_screenshots/feature-tour/videos.png',
    route: () => settingsRoute,
    viewport: {
        width: 1320,
        height: 820,
        deviceScaleFactor: 2,
    },
    async setup(context) {
        const fixture = await seedVideoPickerDocsFixture(context);
        settingsRoute = fixture.settingsRoute;
    },
    waitFor: [
        { type: 'selector', selector: '#content', state: 'visible' },
    ],
    preSteps: [
        createVideoPickerCleanupStep(),
    ],
    steps: [],
    target: {
        type: 'selector',
        selector: '#content',
        padding: 20,
    },
    caption: 'Video Picker field settings.',
    intent: 'Show how a Video Picker field is configured in the field settings screen.',
});
