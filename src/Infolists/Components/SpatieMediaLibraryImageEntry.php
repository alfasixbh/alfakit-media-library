<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Infolists\Components;

use AlfaSix\Alfakit\Infolists\Components\ImageEntry;
use AlfaSix\AlfakitMediaLibrary\Concerns\InteractsWithMediaLibrary;
use Closure;

/**
 * Shows the images of a Spatie Media Library collection of the record.
 *
 * ```php
 * SpatieMediaLibraryImageEntry::make('gallery')->collection('gallery')->conversion('preview')
 * ```
 */
class SpatieMediaLibraryImageEntry extends ImageEntry
{
    use InteractsWithMediaLibrary;

    public function getType(): string
    {
        return $this->type ?? 'ImageEntry';
    }

    /**
     * Show the media of every collection instead of one.
     */
    public function allCollections(bool|Closure $condition = true): static
    {
        $this->hasAllCollections = $condition;

        return $this;
    }

    /**
     * The UUIDs of the record's media, in their order.
     */
    public function getState(): mixed
    {
        if ($this->getStateUsing !== null) {
            return parent::getState();
        }

        $uuids = $this->getMediaItems($this->getRecord())->map(static fn ($media): string => (string) $media->uuid)->values()->all();

        if ($uuids === []) {
            return $this->evaluate($this->defaultState) ?? [];
        }

        return $uuids;
    }

    public function getImageUrl(?string $path): ?string
    {
        $media = $this->findMedia($this->getRecord(), $path);

        if ($media === null) {
            return parent::getImageUrl($path);
        }

        $visibility = $this->evaluate($this->visibility);

        return $this->getMediaUrl($media, is_string($visibility) ? $visibility === 'private' : self::isMediaDiskPrivate($media));
    }
}
