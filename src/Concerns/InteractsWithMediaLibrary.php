<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * The media collection, conversion and filter shared by the field, the column and the
 * entry.
 */
trait InteractsWithMediaLibrary
{
    protected string|Closure|null $collection = null;

    protected bool|Closure $hasAllCollections = false;

    protected string|Closure|null $conversion = null;

    protected ?Closure $filterMediaUsing = null;

    /**
     * The media collection to read (and, for the field, write). Defaults to `default`.
     */
    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * The conversion to show (`thumb`), used once it has been generated; the original
     * file is shown until then.
     */
    public function conversion(string|Closure|null $conversion): static
    {
        $this->conversion = $conversion;

        return $this;
    }

    /**
     * Narrow the media shown: `fn (Collection $media): Collection => $media->where('custom_properties.gallery', true)`.
     */
    public function filterMediaUsing(?Closure $callback): static
    {
        $this->filterMediaUsing = $callback;

        return $this;
    }

    /**
     * The collection name, or null when media of every collection is read.
     */
    public function getCollection(): ?string
    {
        if ((bool) $this->evaluate($this->hasAllCollections)) {
            return null;
        }

        $collection = $this->evaluate($this->collection);

        return is_string($collection) && $collection !== '' ? $collection : 'default';
    }

    public function getConversion(): ?string
    {
        $conversion = $this->evaluate($this->conversion);

        return is_string($conversion) && $conversion !== '' ? $conversion : null;
    }

    /**
     * The record's media of the collection, in their order, after `filterMediaUsing()`.
     *
     * @return Collection<int, Media>
     */
    public function getMediaItems(?Model $record): Collection
    {
        if (! $record instanceof HasMedia || ! $record->exists) {
            return new Collection;
        }

        $collection = $this->getCollection();

        /** @var Collection<int, Media> $media */
        $media = collect($record->getRelationValue('media') ?? [])
            ->filter(static fn (mixed $item): bool => $item instanceof Media && ($collection === null || $item->collection_name === $collection))
            ->sortBy(static fn (Media $item): int => (int) $item->order_column)
            ->values();

        if ($this->filterMediaUsing !== null) {
            $filtered = $this->evaluate($this->filterMediaUsing, ['media' => $media]);
            $media = $filtered instanceof Collection ? $filtered->values() : $media;
        }

        return $media;
    }

    /**
     * The media item with this UUID among the record's media.
     */
    public function findMedia(?Model $record, ?string $uuid): ?Media
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return $this->getMediaItems($record)->first(static fn (Media $media): bool => $media->uuid === $uuid);
    }

    /**
     * The media URL, of the conversion once it is generated; a signed temporary URL for
     * private files. Null when the disk can't produce one.
     */
    public function getMediaUrl(Media $media, bool $isPrivate): ?string
    {
        $conversion = $this->getConversion();
        $conversion = $conversion !== null && $media->hasGeneratedConversion($conversion) ? $conversion : '';

        try {
            if ($isPrivate) {
                return $media->getTemporaryUrl($this->getTemporaryUrlExpiration(), $conversion);
            }

            return $media->getUrl($conversion);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a media item lives on a private disk (its `visibility` is `private`), when
     * the component has no `visibility()` of its own.
     */
    protected static function isMediaDiskPrivate(Media $media): bool
    {
        return config("filesystems.disks.{$media->disk}.visibility") === 'private';
    }
}
