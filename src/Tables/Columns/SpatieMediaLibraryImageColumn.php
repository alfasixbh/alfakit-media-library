<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tables\Columns;

use AlfaSix\Alfakit\Tables\Columns\ImageColumn;
use AlfaSix\AlfakitMediaLibrary\Concerns\InteractsWithMediaLibrary;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Shows the images of a Spatie Media Library collection of each row. The `media`
 * relation is eager loaded.
 *
 * ```php
 * SpatieMediaLibraryImageColumn::make('avatar')->collection('avatars')->conversion('thumb')->circular()
 * ```
 */
class SpatieMediaLibraryImageColumn extends ImageColumn
{
    use InteractsWithMediaLibrary;

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

        $record = $this->getRecord();
        $uuids = $this->getMediaItems($record instanceof Model ? $record : null)->map(static fn ($media): string => (string) $media->uuid)->values()->all();

        if ($uuids === []) {
            return $this->evaluate($this->defaultState) ?? [];
        }

        return $uuids;
    }

    public function getImageUrl(?string $path): ?string
    {
        $record = $this->getRecord();
        $media = $this->findMedia($record instanceof Model ? $record : null, $path);

        if ($media === null) {
            // `getStateUsing()` / `default()` may still return paths or URLs.
            return parent::getImageUrl($path);
        }

        $visibility = $this->evaluate($this->visibility);

        return $this->getMediaUrl($media, is_string($visibility) ? $visibility === 'private' : self::isMediaDiskPrivate($media));
    }

    public function getRelationshipsToEagerLoad(): array
    {
        return array_values(array_unique([...parent::getRelationshipsToEagerLoad(), 'media']));
    }
}
