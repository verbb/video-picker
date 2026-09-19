import { readFileSync } from 'node:fs';
import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/craft-screenshots/types';

export type VideoPickerFixture = {
    entryEditRoute: string;
    sourcesRoute: string;
};

const supportDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(supportDir, 'seed', 'seed-video-picker.php'), 'utf8');

export async function seedVideoPickerFixture(context: ScreenshotSetupContext): Promise<VideoPickerFixture> {
    await ensureVideoPickerScreenshotModule(context.installDir);
    await ensureVideoPickerScreenshotAssets(context);

    const output = await context.runCraftScript(seedScript, { label: 'seed-video-picker' });
    const fixture = JSON.parse(output.trim()) as VideoPickerFixture;

    if (!fixture.entryEditRoute || !fixture.sourcesRoute) {
        throw new Error(`Invalid Video Picker fixture payload: ${output}`);
    }

    return fixture;
}

export async function ensureVideoPickerScreenshotModule(installDir: string): Promise<void> {
    const moduleDir = join(installDir, 'modules/videopickerscreenshots');
    await mkdir(moduleDir, { recursive: true });

    for (const filename of ['Module.php', 'ScreenshotVimeoSource.php', 'ScreenshotYouTubeSource.php']) {
        await copyFile(join(supportDir, 'module', filename), join(moduleDir, filename));
    }

    const appPath = join(installDir, 'config/app.php');
    let contents = await readFile(appPath, 'utf8');

    if (contents.includes("'videoPickerScreenshots'")) {
        return;
    }

    contents = contents.replace(
        /return\s*\[\s*'id'\s*=>\s*App::env\('CRAFT_APP_ID'\)\s*\?:\s*'CraftCMS',\s*\];/s,
        `require_once dirname(__DIR__) . '/modules/videopickerscreenshots/Module.php';
require_once dirname(__DIR__) . '/modules/videopickerscreenshots/ScreenshotVimeoSource.php';
require_once dirname(__DIR__) . '/modules/videopickerscreenshots/ScreenshotYouTubeSource.php';

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => [
        'videoPickerScreenshots' => \\modules\\videopickerscreenshots\\Module::class,
    ],
    'bootstrap' => ['videoPickerScreenshots'],
];`,
    );

    if (!contents.includes("'videoPickerScreenshots'")) {
        throw new Error('Failed to register the Video Picker screenshot module.');
    }

    await writeFile(appPath, contents);
}

async function ensureVideoPickerScreenshotAssets(context: ScreenshotSetupContext): Promise<void> {
    const { readdir } = await import('node:fs/promises');
    const sourceDir = join(context.captureRoot, 'assets', 'explorer');
    const targetDir = join(context.installDir, 'web', 'video-picker-screenshots', 'explorer');
    await mkdir(targetDir, { recursive: true });

    for (const filename of await readdir(sourceDir)) {
        if (filename.endsWith('.jpg')) {
            await copyFile(join(sourceDir, filename), join(targetDir, filename));
        }
    }

    await copyFile(
        join(context.captureRoot, 'assets', 'sea-turtle-thumb.jpg'),
        join(context.installDir, 'web', 'video-picker-screenshots', 'sea-turtle-thumb.jpg'),
    );
}
