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
    vi.stubGlobal('Craft', { t: (_category: string, message: string) => message, sendActionRequest: vi.fn(() => new Promise((resolve, reject) => {
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

it('resolves a custom provider URL when the editor commits the input', async () => {
    const { input, requests } = fixture();
    const url = 'https://videos.example.com/watch/custom-123';
    input.setPostedUrl(url);
    input.commitVideoFetch();
    expect(Craft.sendActionRequest).toHaveBeenCalledWith('POST', 'video-picker/videos/get-video', {
        data: { url, fieldId: undefined },
    });
    requests[0].resolve({ data: { url, title: 'Custom provider video' } });
    await settle();
    expect(input.currentVideo.title).toBe('Custom provider video');
});

it.each([
    { state: 'the preview is hidden', sourceCount: 1, showPreview: false, errors: {} },
    { state: 'sources are unavailable', sourceCount: 0, showPreview: true, errors: {} },
    { state: 'the preview has an error', sourceCount: 1, showPreview: true, errors: { url: ['Unavailable'] } },
])('allows clearing browse-only selections when $state', ({ sourceCount, showPreview, errors }) => {
    const { input } = fixture();
    vi.stubGlobal('document', { createElement: () => ({ setAttribute: vi.fn(), appendChild: vi.fn() }) });
    input.settings.sourceCount = sourceCount;
    input.allowUrlInput = false;
    input.enablePreview = showPreview;
    input.currentVideo = { url: input.videoUrl, errors };
    input.previewHost = { replaceChildren: vi.fn(), appendChild: vi.fn() };
    input.syncBusyState = vi.fn();
    const remove = { addEventListener: vi.fn() };
    input.iconButton = vi.fn(() => remove);
    // Render the actual field branches, replacing only DOM/control primitives.
    delete input.syncPreview;
    input.syncPreview();

    expect(input.previewHost.appendChild).toHaveBeenCalledWith(remove);
    const [, onClick] = remove.addEventListener.mock.calls[0];
    onClick({ preventDefault: vi.fn() });
    expect(input.valueInput.value).toBe('');
    expect(input.currentVideo).toBeNull();
    expect(input.valueInput.dispatchEvent).toHaveBeenCalled();
});
