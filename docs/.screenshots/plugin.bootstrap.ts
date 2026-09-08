import type { ScreenshotSetupContext } from '@verbb/docs-screenshots/types';
import { registerPluginBootstrap } from '@verbb/docs-screenshots/api';

import { ensureVideoPickerDocsModule } from './video-picker/fixtures';

export default registerPluginBootstrap({
    id: 'video-picker',
    async setup(context: ScreenshotSetupContext) {
        await context.runCraft(['migrate/up', '--plugin=video-picker'], { allowFailure: true });
        await ensureVideoPickerDocsModule(context.installDir);
    },
});
