import { afterEach, expect, it, vi } from 'vitest';
import { createVideoGrid } from './VideoCard.js';

// The tests run without a DOM, so a small element stand-in records what the empty state builds.
class FakeElement {
    attributes: Record<string, string> = {};
    children: (FakeElement | string)[] = [];
    listeners: Record<string, () => void> = {};
    className = '';
    textContent = '';

    constructor(readonly tagName: string) {}

    setAttribute(name: string, value: string) { this.attributes[name] = value; }
    append(...nodes: (FakeElement | string)[]) { this.children.push(...nodes); }
    appendChild(node: FakeElement) { this.children.push(node); }
    addEventListener(type: string, listener: () => void) { this.listeners[type] = listener; }
}

function setup() {
    vi.stubGlobal('Craft', {
        t: (_category: string, message: string, params: Record<string, string> = {}) =>
            message.replace(/\{(\w+)\}/g, (_match, key: string) => params[key] ?? ''),
    });
    vi.stubGlobal('document', { createElement: (tag: string) => new FakeElement(tag) });
}

const handlers = { onSelect: vi.fn(), onUse: vi.fn(), onPlay: vi.fn() };

afterEach(() => vi.unstubAllGlobals());

it('tells an empty collection apart from a search with no results', () => {
    setup();
    const browse = createVideoGrid([], null, handlers, { sourceName: 'YouTube', collectionName: 'Uploads' }) as unknown as FakeElement;

    expect(browse.tagName).toBe('pk-state-panel');
    expect(browse.attributes.heading).toBe('No videos here yet');
    expect(browse.children).toEqual(['Uploads on YouTube has no videos this field can use.']);

    const onClearSearch = vi.fn();
    const search = createVideoGrid([], null, handlers, { sourceName: 'YouTube', collectionName: 'Uploads', query: ' cats ', onClearSearch }) as unknown as FakeElement;

    expect(search.attributes.heading).toBe('No matching videos');
    expect(search.children[0]).toBe('Nothing on YouTube matches “cats”. Try other words, or clear the search to browse.');

    const clear = search.children[1] as FakeElement;
    expect(clear.tagName).toBe('pk-button');
    expect(clear.attributes.slot).toBe('actions');
    clear.listeners.click();
    expect(onClearSearch).toHaveBeenCalledOnce();
});

it('names only the source when there is no collection', () => {
    setup();
    const panel = createVideoGrid([], null, handlers, { sourceName: 'Vimeo' }) as unknown as FakeElement;

    expect(panel.children).toEqual(['Vimeo has no videos this field can use.']);
});
