import { afterEach, expect, it, vi } from 'vitest';
import { ExplorerDialog } from './ExplorerDialog.js';

function deferred() {
    let resolve!: (value: unknown) => void;
    const promise = new Promise((res) => { resolve = res; });
    return { promise, resolve };
}

function fixture() {
    const requests: ReturnType<typeof deferred>[] = [];
    vi.stubGlobal('Craft', { sendActionRequest: vi.fn(() => {
        const request = deferred();
        requests.push(request);
        return request.promise;
    }) });
    // Exercise request ordering without replacing the explorer's request handlers.
    const dialog = Object.assign(Object.create(ExplorerDialog.prototype), {
        options: {}, closed: false, requestVersion: 0, loadingMore: false,
        currentSource: { handle: 'first', sections: [] },
        currentCollection: { method: 'videos', options: {} },
        query: '', videos: [], sources: [], nextPage: 'next',
        render: vi.fn(), renderMoreState: vi.fn(), announceVideoCount: vi.fn(),
        captureFocusedVideoId: vi.fn(), restoreVideoCardFocus: vi.fn(),
        debouncedSearch: { cancel: vi.fn() }, debouncedFetchVideos: { cancel: vi.fn() },
    });
    return { dialog, requests };
}

async function settle(request: ReturnType<typeof deferred>, data: unknown) {
    request.resolve({ data });
    await new Promise((resolve) => setTimeout(resolve, 0));
}

afterEach(() => vi.unstubAllGlobals());

it('keeps the latest collection when an earlier response arrives last', async () => {
    const { dialog, requests } = fixture();
    dialog.fetchVideos();
    dialog.currentCollection = { method: 'other' };
    dialog.fetchVideos();
    await settle(requests[1], { videos: [{ id: 'latest' }], nextPage: null });
    await settle(requests[0], { videos: [{ id: 'old' }], nextPage: 'obsolete' });
    expect(dialog.videos).toEqual([{ id: 'latest' }]);
    expect(dialog.nextPage).toBeNull();
});

it('does not append a previous page after starting a search', async () => {
    const { dialog, requests } = fixture();
    dialog.fetchMoreVideos();
    dialog.query = 'current';
    dialog.searchVideos();
    await settle(requests[1], { videos: [{ id: 'search' }], nextPage: null });
    await settle(requests[0], { videos: [{ id: 'browse' }], nextPage: 'old' });
    expect(dialog.videos).toEqual([{ id: 'search' }]);
    expect(dialog.loadingMore).toBe(false);
});

it('does not restore an earlier source after its hydration completes', async () => {
    const { dialog, requests } = fixture();
    const first = dialog.currentSource;
    dialog.sources = [first, { handle: 'second', sections: [] }];
    const hydration = dialog.ensureSourceSections(first);
    dialog.currentSource = dialog.sources[1];
    dialog.fetchVideos();
    await settle(requests[0], [{ handle: 'first', sections: [{ collections: [] }] }]);
    await hydration;
    expect(dialog.currentSource.handle).toBe('second');
});

it('ignores requests that finish after the explorer closes', async () => {
    const { dialog, requests } = fixture();
    dialog.fetchVideos();
    dialog.closed = true;
    dialog.render.mockClear();
    await settle(requests[0], { videos: [{ id: 'late' }], nextPage: null });
    expect(dialog.videos).toEqual([]);
    expect(dialog.render).not.toHaveBeenCalled();
});
