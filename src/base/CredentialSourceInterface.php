<?php
namespace verbb\videopicker\base;

interface CredentialSourceInterface extends SourceInterface
{
    // Public Methods
    // =========================================================================

    public function getCredentialAttributes(): array;
}
