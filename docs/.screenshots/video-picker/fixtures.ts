import { readFileSync } from 'node:fs';
import { copyFile, mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import type { ScreenshotSetupContext } from '@verbb/docs-screenshots/types';

export type VideoPickerDocsFixture = {
    fieldId: number;
    fieldHandle: string;
    settingsRoute: string;
    entryEditRoute: string;
    sourcesRoute: string;
};

const fixtureDir = dirname(fileURLToPath(import.meta.url));
const seedScript = readFileSync(join(fixtureDir, 'seed-docs-field.php'), 'utf8');

/**
 * Seed the Video Picker demo field + sources + entry with a resolved video preview.
 */
export async function seedVideoPickerDocsFixture(context: ScreenshotSetupContext): Promise<VideoPickerDocsFixture> {
    await ensureVideoPickerDocsModule(context.installDir);
    await ensureVideoPickerDocsAssets(context.installDir);

    const output = await context.runCraftScript(seedScript, { label: 'seed-video-picker-docs-field' });
    const fixture = JSON.parse(output.trim()) as VideoPickerDocsFixture;

    if (!fixture.fieldId || !fixture.settingsRoute || !fixture.entryEditRoute) {
        throw new Error(`Invalid Video Picker docs fixture payload: ${output}`);
    }

    return fixture;
}

/** Copy docs-only Demo Videos module + wire it into config/app.php. */
export async function ensureVideoPickerDocsModule(installDir: string): Promise<void> {
    const moduleDir = join(installDir, 'modules/videopickerdocs');
    await mkdir(moduleDir, { recursive: true });
    await copyFile(join(fixtureDir, 'Module.php'), join(moduleDir, 'Module.php'));
    await copyFile(join(fixtureDir, 'DocsDemoSource.php'), join(moduleDir, 'DocsDemoSource.php'));
    await copyFile(join(fixtureDir, 'DocsVimeoSource.php'), join(moduleDir, 'DocsVimeoSource.php'));

    const appPath = join(installDir, 'config/app.php');
    let contents = await readFile(appPath, 'utf8');

    if (contents.includes("'videoPickerDocs'")) {
        return;
    }

    if (!contents.includes("App::env('CRAFT_APP_ID')")) {
        throw new Error(`Unexpected Craft config/app.php shape; cannot register videoPickerDocs module.\n${contents}`);
    }

    contents = contents.replace(
        /return\s*\[\s*'id'\s*=>\s*App::env\('CRAFT_APP_ID'\)\s*\?:\s*'CraftCMS',\s*\];/s,
        `require_once dirname(__DIR__) . '/modules/videopickerdocs/Module.php';
require_once dirname(__DIR__) . '/modules/videopickerdocs/DocsDemoSource.php';
require_once dirname(__DIR__) . '/modules/videopickerdocs/DocsVimeoSource.php';
require_once dirname(__DIR__) . '/modules/videopickerdocs/DocsVimeoSource.php';

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => [
        'videoPickerDocs' => \\modules\\videopickerdocs\\Module::class,
    ],
    'bootstrap' => ['videoPickerDocs'],
];`,
    );

    if (!contents.includes("'videoPickerDocs'")) {
        throw new Error('Failed to patch config/app.php for videoPickerDocs module.');
    }

    await writeFile(appPath, contents);
}

/** Copy bundled screenshot assets into the Craft web root (local thumbs for capture). */
export async function ensureVideoPickerDocsAssets(installDir: string): Promise<void> {
    const { readdir } = await import('node:fs/promises');

    const assetDir = join(installDir, 'web/video-picker-docs');
    await mkdir(assetDir, { recursive: true });
    await copyFile(
        join(fixtureDir, 'assets/sea-turtle-thumb.jpg'),
        join(assetDir, 'sea-turtle-thumb.jpg'),
    );

    const explorerDir = join(fixtureDir, 'assets/explorer');
    const explorerAssetDir = join(assetDir, 'explorer');
    await mkdir(explorerAssetDir, { recursive: true });

    for (const filename of await readdir(explorerDir)) {
        if (!filename.endsWith('.jpg')) {
            continue;
        }

        await copyFile(join(explorerDir, filename), join(explorerAssetDir, filename));
    }
}
