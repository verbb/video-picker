import { registerPluginBootstrap } from '@verbb/craft-screenshots/api';

import { ensureVideoPickerScreenshotModule } from './fixtures';

export default registerPluginBootstrap({
    id: 'video-picker',
    async setup(context) {
        await context.runCraft(['migrate/up', '--plugin=video-picker'], { allowFailure: true });
        await ensureVideoPickerScreenshotModule(context.installDir);
    },
});
