<?php
namespace verbb\videopicker\models;

use craft\base\Model;

class Collection extends Model
{
    // Properties
    // =========================================================================

    public ?string $name = null;
    public ?string $method = null;
    public array $options = [];
    public ?string $icon = null;
    
}
