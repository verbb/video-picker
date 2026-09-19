import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';
import { registerPluginBootstrap } from '@verbb/craft-screenshots/api';

import { ensureVideoPickerDocsModule } from './docs/fixtures';

export default registerPluginBootstrap({
    id: 'video-picker',
    async setup(context: ScreenshotSetupContext) {
        await context.runCraft(['migrate/up', '--plugin=video-picker'], { allowFailure: true });
        await ensureVideoPickerDocsModule(context.installDir);
    },
});
