<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Forms\Components;

use AlfaSix\Alfakit\Forms\Components\FileUpload;
use AlfaSix\Alfakit\Support\TemporaryUpload;
use AlfaSix\AlfakitMediaLibrary\Concerns\InteractsWithMediaLibrary;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * A `FileUpload` that stores its files in a Spatie Media Library collection of the
 * record instead of an attribute. The state is the list of media UUIDs; new uploads are
 * added with `addMediaFromDisk()`, removed media are deleted and the order is saved to
 * the `order_column` after the record is saved.
 *
 * ```php
 * SpatieMediaLibraryFileUpload::make('gallery')->collection('gallery')->multiple()->reorderable()
 * ```
 */
class SpatieMediaLibraryFileUpload extends FileUpload
{
    use InteractsWithMediaLibrary;

    protected string|Closure|null $conversionsDisk = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $customHeaders = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $customProperties = null;

    /** @var array<string, array<string, mixed>>|Closure|null */
    protected array|Closure|null $manipulations = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $properties = null;

    protected string|Closure|null $mediaName = null;

    protected bool|Closure $hasResponsiveImages = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Files are added to the media library in `saveRelationships()`, never moved to a
        // path that would be saved in an attribute.
        $this->dehydrateStateUsing(null);
    }

    public function getType(): string
    {
        return $this->type ?? 'FileUpload';
    }

    // ---------------------------------------------------------------- Configuration

    /**
     * The disk conversions are stored on (`storingConversionsOnDisk()`).
     */
    public function conversionsDisk(string|Closure|null $disk): static
    {
        $this->conversionsDisk = $disk;

        return $this;
    }

    /**
     * Headers sent to remote disks when the file is stored, e.g. `['CacheControl' => 'max-age=86400']`.
     *
     * @param  array<string, mixed>|Closure|null  $headers
     */
    public function customHeaders(array|Closure|null $headers): static
    {
        $this->customHeaders = $headers;

        return $this;
    }

    /**
     * Custom properties stored with each new media item. A closure receives the upload as
     * `$file` (a `TemporaryUpload`).
     *
     * @param  array<string, mixed>|Closure|null  $properties
     */
    public function customProperties(array|Closure|null $properties): static
    {
        $this->customProperties = $properties;

        return $this;
    }

    /**
     * Manipulations per conversion, e.g. `['thumb' => ['orientation' => '90']]`.
     *
     * @param  array<string, array<string, mixed>>|Closure|null  $manipulations
     */
    public function manipulations(array|Closure|null $manipulations): static
    {
        $this->manipulations = $manipulations;

        return $this;
    }

    /**
     * Attributes set on each new media model (`withProperties()`).
     *
     * @param  array<string, mixed>|Closure|null  $properties
     */
    public function properties(array|Closure|null $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * The media `name`; defaults to the original file name without its extension. A
     * closure receives the upload as `$file` and its `$originalName`.
     */
    public function mediaName(string|Closure|null $name): static
    {
        $this->mediaName = $name;

        return $this;
    }

    /**
     * Generate responsive images for new media (`withResponsiveImages()`).
     */
    public function responsiveImages(bool|Closure $condition = true): static
    {
        $this->hasResponsiveImages = $condition;

        return $this;
    }

    /**
     * Spatie's name for {@see self::responsiveImages()}.
     */
    public function withResponsiveImages(bool|Closure $condition = true): static
    {
        return $this->responsiveImages($condition);
    }

    // ---------------------------------------------------------------- Resolution

    public function getCollection(): string
    {
        $collection = $this->evaluate($this->collection);

        return is_string($collection) && $collection !== '' ? $collection : 'default';
    }

    /**
     * `disk()`, else the disk of the model's registered media collection, else the media
     * library's `disk_name`.
     */
    public function getDiskName(): string
    {
        $disk = $this->evaluate($this->disk);

        if (is_string($disk) && $disk !== '') {
            return $disk;
        }

        $collectionDisk = $this->getMediaCollectionDefinition()?->diskName;

        return is_string($collectionDisk) && $collectionDisk !== ''
            ? $collectionDisk
            : (string) config('media-library.disk_name', 'public');
    }

    /**
     * The model's registered media collection (`registerMediaCollections()`), if any.
     */
    public function getMediaCollectionDefinition(): ?MediaCollection
    {
        $record = $this->getRecord();

        if (! $record instanceof HasMedia) {
            $model = $this->getModel();
            $record = $model !== null && is_subclass_of($model, HasMedia::class) ? new $model : null;
        }

        if (! $record instanceof HasMedia) {
            return null;
        }

        try {
            return $record->getMediaCollection($this->getCollection());
        } catch (Throwable) {
            return null;
        }
    }

    public function getConversionsDisk(): ?string
    {
        $disk = $this->evaluate($this->conversionsDisk);

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    /**
     * The media items of the record's collection, by UUID.
     *
     * @return array<string, Media>
     */
    protected function getRecordMediaByUuid(): array
    {
        $media = [];

        foreach ($this->getMediaItems($this->getRecord()) as $item) {
            $media[(string) $item->uuid] = $item;
        }

        return $media;
    }

    // ---------------------------------------------------------------- State

    public function loadStateFromRelationships(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof HasMedia || ! $record->exists) {
            return;
        }

        $this->state(array_keys($this->getRecordMediaByUuid()));
    }

    /**
     * Saved through the media relation in `saveRelationships()`.
     */
    public function isDehydrated(): bool
    {
        return false;
    }

    /**
     * The record's media of the collection may be submitted back.
     *
     * @return array<string>
     */
    public function getExistingFiles(): array
    {
        return array_values(array_unique([...$this->hydratedFiles, ...array_keys($this->getRecordMediaByUuid())]));
    }

    public function getStoredFileNames(): array
    {
        return array_map(static fn (Media $media): string => (string) $media->file_name, $this->getRecordMediaByUuid());
    }

    protected function getStoredFileInformation(string $path): array
    {
        $media = $this->getRecordMediaByUuid()[$path] ?? null;

        if ($media === null || ! $this->evaluate($this->shouldFetchFileInformation)) {
            return [];
        }

        return ['size' => (int) $media->size];
    }

    public function getFileUrl(string $path): ?string
    {
        if (TemporaryUpload::isKey($path)) {
            return null;
        }

        $media = $this->getRecordMediaByUuid()[$path] ?? null;

        return $media === null ? null : \AlfaSix\Alfakit\Support\Url::sanitize($this->getMediaUrl($media, $this->isPrivate($media)));
    }

    protected function isPrivate(Media $media): bool
    {
        $visibility = $this->evaluate($this->visibility);

        if (is_string($visibility)) {
            return $visibility === 'private';
        }

        return self::isMediaDiskPrivate($media);
    }

    // ---------------------------------------------------------------- Saving

    /**
     * Add the new uploads to the collection, delete the media that were removed and save
     * the order of the rest.
     */
    public function saveRelationships(): void
    {
        if (! $this->shouldSaveRelationships()) {
            return;
        }

        $record = $this->getRecord();

        if (! $record instanceof HasMedia || ! $record->exists) {
            return;
        }

        $record->load('media');
        $existing = $this->getRecordMediaByUuid();
        $ids = [];
        $kept = [];

        foreach ($this->normalizeFiles($this->getState()) as $file) {
            if (! is_string($file)) {
                continue;
            }

            if (isset($existing[$file])) {
                $ids[] = $existing[$file]->getKey();
                $kept[$file] = true;

                continue;
            }

            if (! TemporaryUpload::isKey($file)) {
                continue;
            }

            $upload = TemporaryUpload::find($file);

            if ($upload === null || $this->getTemporaryUploadError($upload) !== null) {
                continue;
            }

            $ids[] = $this->addUploadToMediaLibrary($record, $upload)->getKey();
        }

        foreach ($existing as $uuid => $media) {
            if (! isset($kept[$uuid])) {
                $media->delete();
            }
        }

        if ($ids !== []) {
            /** @var class-string<Media> $mediaClass */
            $mediaClass = config('media-library.media_model', Media::class);
            $mediaClass::setNewOrder($ids);
        }

        $record->load('media');
    }

    protected function addUploadToMediaLibrary(HasMedia&Model $record, TemporaryUpload $upload): Media
    {
        $originalName = $upload->originalName();
        $arguments = ['file' => $upload, 'upload' => $upload, 'originalName' => $originalName];

        try {
            $adder = $record
                ->addMediaFromDisk($upload->path(), (string) config('alfakit.uploads.temporary_disk', config('filesystems.default')))
                ->usingFileName($this->resolveStoredFileName($originalName, $this->getUploadExtension($upload)))
                ->usingName((string) ($this->evaluate($this->mediaName, $arguments) ?? pathinfo($originalName, PATHINFO_FILENAME)));

            if (is_array($properties = $this->evaluate($this->customProperties, $arguments))) {
                $adder->withCustomProperties($properties);
            }

            if (is_array($manipulations = $this->evaluate($this->manipulations, $arguments))) {
                $adder->withManipulations($manipulations);
            }

            if (is_array($attributes = $this->evaluate($this->properties, $arguments))) {
                $adder->withProperties($attributes);
            }

            if (is_array($headers = $this->evaluate($this->customHeaders, $arguments))) {
                $adder->addCustomHeaders($headers);
            }

            if (($conversionsDisk = $this->getConversionsDisk()) !== null) {
                $adder->storingConversionsOnDisk($conversionsDisk);
            }

            if ((bool) $this->evaluate($this->hasResponsiveImages)) {
                $adder->withResponsiveImages();
            }

            $disk = $this->evaluate($this->disk);
            $media = $adder->toMediaCollection($this->getCollection(), is_string($disk) ? $disk : '');
        } catch (FileCannotBeAdded) {
            throw ValidationException::withMessages([
                $this->getStatePath() => __('alfakit-media-library::media-library.file_cannot_be_added'),
            ]);
        }

        $upload->delete();

        return $media;
    }
}
