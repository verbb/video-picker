import { afterEach, expect, it, vi } from 'vitest';
import { VideoPickerInput } from './VideoPickerInput.js';

const explorer = vi.hoisted(() => ({ options: null as any }));
vi.mock('../explorer/ExplorerDialog.js', () => ({
    ExplorerDialog: class {
        constructor(options: unknown) { explorer.options = options; }
        open() {}
    },
}));

function fixture() {
    const requests: { resolve: (value: unknown) => void; reject: (error: unknown) => void }[] = [];
    vi.stubGlobal('Craft', { sendActionRequest: vi.fn(() => new Promise((resolve, reject) => {
        requests.push({ resolve, reject });
    })) });
    // Keep the real field actions and request handlers; rendering is exercised in Craft.
    const input = Object.assign(Object.create(VideoPickerInput.prototype), {
        settings: { sourceCount: 1 }, videoUrl: 'https://vimeo.com/1',
        urlInput: { value: 'https://vimeo.com/1' },
        valueInput: { value: 'https://vimeo.com/1', dispatchEvent: vi.fn() },
        currentVideo: null, videoError: null, loadingVideo: false,
        enableExplorer: true, explorerOpen: false, requestVersion: 0,
        syncPreview: vi.fn(), debouncedFetchVideo: { cancel: vi.fn() },
    });
    return { input, requests };
}

async function settle() {
    await new Promise((resolve) => setTimeout(resolve, 0));
}

afterEach(() => vi.unstubAllGlobals());

it('shows an explorer selection immediately while an older URL lookup finishes', async () => {
    const { input, requests } = fixture();
    input.fetchVideo();
    input.openExplorer();
    const selected = { url: 'https://vimeo.com/2', title: 'Selected in explorer' };
    explorer.options.onSelect(selected);
    expect(input.loadingVideo).toBe(false);
    expect(input.valueInput.value).toBe(selected.url);
    requests[0].resolve({ data: { url: 'https://vimeo.com/1', title: 'Earlier URL' } });
    await settle();
    expect(input.currentVideo).toEqual(selected);
    expect(input.loadingVideo).toBe(false);
});

it('cancels a queued URL lookup when the explorer supplies the selection', () => {
    const { input } = fixture();
    input.openExplorer();
    explorer.options.onSelect({ url: 'https://vimeo.com/1', title: 'Selected' });
    expect(input.debouncedFetchVideo.cancel).toHaveBeenCalled();
});

it('retains the latest result when duplicate URL lookups finish out of order', async () => {
    const { input, requests } = fixture();
    input.fetchVideo();
    input.fetchVideo();
    requests[1].resolve({ data: { url: input.videoUrl, title: 'Current metadata' } });
    await settle();
    requests[0].resolve({ data: { error: 'Earlier lookup failed' } });
    await settle();
    expect(input.currentVideo.title).toBe('Current metadata');
    expect(input.videoError).toBeNull();
});

it('does not end loading when an older duplicate lookup finishes first', async () => {
    const { input, requests } = fixture();
    input.fetchVideo();
    input.fetchVideo(true);
    requests[0].resolve({ data: { url: input.videoUrl, title: 'Old metadata' } });
    await settle();
    expect(input.loadingVideo).toBe(true);
    expect(input.currentVideo).toBeNull();
    requests[1].resolve({ data: { url: input.videoUrl, title: 'Refreshed metadata' } });
    await settle();
    expect(input.loadingVideo).toBe(false);
    expect(input.currentVideo.title).toBe('Refreshed metadata');
});
