<?php

declare(strict_types=1);

use verbb\videopicker\sources\BunnyStream;
use verbb\videopicker\sources\SproutVideo;
use verbb\videopicker\sources\Vimeo;
use verbb\videopicker\sources\Wistia;
use verbb\videopicker\sources\YouTube;

trait CollectionPageResponses
{
    public array $requestedPages = [];

    public function cachedRequest(string $method = 'GET', string $uri = '', array $options = [])
    {
        $page = (int)($options['query']['page'] ?? (!empty($options['query']['pageToken']) ? 2 : 1));
        $this->requestedPages[$uri][] = $page;
        if ($page > 2) { throw new RuntimeException('Unexpected extra collection request'); }
        $item = ['id' => 'collection-' . $page, 'guid' => 'collection-' . $page, 'hashedId' => 'collection-' . $page, 'name' => 'Collection page ' . $page, 'snippet' => ['title' => 'Collection page ' . $page]];
        $count = $page === 1 ? (int)($options['query']['per_page'] ?? $options['query']['itemsPerPage'] ?? 50) : 1;
        $items = array_fill(0, $count, $item);

        if ($this instanceof YouTube) {
            return ['items' => $items, 'nextPageToken' => $page === 1 ? 'second' : null];
        }
        if ($this instanceof Vimeo) {
            foreach ($items as &$value) {
                $type = str_contains($uri, 'folders') ? 'projects' : basename($uri);
                $value['uri'] = '/users/fixture/' . $type . '/' . $value['id'];
            }
            return ['data' => $items, 'paging' => ['next' => $page === 1 ? '/next?page=2' : null]];
        }
        if ($this instanceof BunnyStream) {
            return ['items' => $items, 'totalItems' => 51, 'currentPage' => $page, 'itemsPerPage' => 50];
        }
        if ($this instanceof SproutVideo) {
            return ['folders' => $items, 'next_page' => $page === 1 ? 'https://api.sproutvideo.com/v1/folders?page=2' : null];
        }
        return $items;
    }
}

it('includes collections beyond the first provider page', function(Closure $factory) {
    $source = $factory();
    $sections = $source->getExplorerSections();
    foreach (array_slice($sections, 1) as $section) {
        expect(array_column($section->collections, 'name'))->toContain('Collection page 2');
    }
    expect($sections)->toHaveCount($source instanceof Vimeo ? 4 : 2);
    foreach ($source->requestedPages as $pages) {
        expect($pages)->toBe([1, 2]);
    }
})->with([
    'YouTube' => fn() => new class extends YouTube { use CollectionPageResponses; },
    'Vimeo' => fn() => new class extends Vimeo { use CollectionPageResponses; },
    'Bunny Stream' => fn() => new class extends BunnyStream { use CollectionPageResponses; },
    'Sprout Video' => fn() => new class extends SproutVideo { use CollectionPageResponses; },
    'Wistia' => fn() => new class extends Wistia { use CollectionPageResponses; },
]);

it('reports a failed YouTube playlist request instead of caching an empty library', function() {
    $source = new class extends YouTube {
        public function cachedRequest(string $method = 'GET', string $uri = '', array $options = [])
        {
            throw new RuntimeException('Provider unavailable');
        }
    };
    expect(fn() => $source->getExplorerSections())->toThrow(RuntimeException::class, 'Provider unavailable');
});
