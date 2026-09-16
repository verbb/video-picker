<?php

declare(strict_types=1);

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;

it('serializes play counts above the GraphQL 32-bit integer limit', function() {
    $field = new VideoPickerField(['handle' => 'largePlayCount']);
    $schema = new Schema(['query' => new ObjectType([
        'name' => 'Query',
        'fields' => ['video' => [
            'type' => $field->getContentGqlType(),
            'resolve' => fn() => new Video(['title' => 'Popular video', 'plays' => 3_000_000_000]),
        ]],
    ])]);
    $result = GraphQL::executeQuery($schema, '{ video { title plays } }')->toArray();
    expect($result)->not->toHaveKey('errors')
        ->and($result['data']['video']['plays'])->toEqual(3_000_000_000);
});
