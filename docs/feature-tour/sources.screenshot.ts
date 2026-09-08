import { defineScreenshotScenario } from '@verbb/docs-screenshots/api';
import { seedVideoPickerDocsFixture } from '../.screenshots/video-picker/fixtures';
import {
    createPrepareVideoPickerSourcesIndexStep,
    createVideoPickerCleanupStep,
} from '../.screenshots/video-picker/presets';

let sourcesRoute = '/admin/video-picker/sources';

export default defineScreenshotScenario({
    id: 'feature-tour-sources',
    output: '_screenshots/feature-tour/sources.png',
    route: () => sourcesRoute,
    // ~200px narrower than the full table while keeping Provider visible.
    viewport: {
        width: 1200,
        height: 520,
        deviceScaleFactor: 2,
    },
    async setup(context) {
        const fixture = await seedVideoPickerDocsFixture(context);
        sourcesRoute = fixture.sourcesRoute || '/admin/video-picker/sources';
    },
    waitFor: [
        { type: 'selector', selector: '#sources-vue-admin-table', state: 'visible', timeout: 30000 },
        { type: 'selector', selector: '#sources-vue-admin-table table tbody tr', state: 'visible', timeout: 30000 },
    ],
    preSteps: [
        createVideoPickerCleanupStep(),
        createPrepareVideoPickerSourcesIndexStep(),
        {
            type: 'evaluate',
            expression: `
                (() => {
                    const rows = document.querySelectorAll('#sources-vue-admin-table table tbody tr');
                    if (rows.length < 8) {
                        throw new Error('Expected eight provider rows on the Sources index, found ' + rows.length + '.');
                    }
                })();
            `,
        },
        { type: 'wait', waitFor: { type: 'timeout', ms: 200 } },
    ],
    steps: [],
    target: {
        type: 'selector',
        selector: '#sources-vue-admin-table table',
        padding: 0,
    },
    caption: 'Video Picker Sources index with every supported provider connected.',
    intent: 'Show the Sources index listing all built-in providers, each marked connected.',
});
