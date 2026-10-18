<?php

declare(strict_types=1);

use AlfaSix\Alfakit\Schemas\Schema;
use AlfaSix\AlfakitMediaLibrary\Forms\Components\SpatieMediaLibraryFileUpload;
use AlfaSix\AlfakitMediaLibrary\Infolists\Components\SpatieMediaLibraryImageEntry;
use AlfaSix\AlfakitMediaLibrary\MediaLibraryPlugin;
use AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\Album;
use AlfaSix\AlfakitMediaLibrary\Tests\TestCase as MediaLibraryTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(MediaLibraryTestCase::class);

function mlTest(): MediaLibraryTestCase
{
    return MediaLibraryTestCase::current();
}

function mlAlbum(string $title = 'Holiday'): Album
{
    return Album::query()->create(['title' => $title]);
}

function mlAddMedia(Album $album, string $collection, string $name = 'photo.png'): Media
{
    return $album->addMedia(UploadedFile::fake()->image($name, 10, 10))->toMediaCollection($collection);
}

/**
 * @param  array<string, mixed>  $form
 * @return array<string, mixed>
 */
function mlComponent(array $form, string $name): array
{
    return collect($form['components'])->firstWhere('name', $name);
}

it('registers the plugin on a panel', function (): void {
    $panel = alfakit()->getPanel('media');

    expect($panel->hasPlugin('media-library'))->toBeTrue()
        ->and($panel->getPlugin('media-library'))->toBeInstanceOf(MediaLibraryPlugin::class);
});

it('adds uploads to the media collections when a record is created', function (): void {
    mlTest()->actingAs(mlTest()->admin());
    $token = mlTest()->pageProps('/media/albums/create')['token'];

    $cover = mlTest()->upload('cover.png');
    $first = mlTest()->upload('first.png');
    $second = mlTest()->upload('second.png');

    mlTest()->runAction($token, 'create', ['title' => 'Summer', 'cover' => [$cover], 'gallery' => [$first, $second]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $album = Album::query()->where('title', 'Summer')->sole();
    $gallery = $album->getMedia('gallery');

    expect($album->getMedia('cover'))->toHaveCount(1)
        ->and($gallery->pluck('name')->all())->toBe(['Gallery first', 'Gallery second'])
        ->and($gallery->first()?->getCustomProperty('source'))->toBe('panel')
        ->and($gallery->first()?->disk)->toBe('public');
});

it('fills the field with the media and serialises it as a file upload', function (): void {
    $album = mlAlbum();
    $one = mlAddMedia($album, 'gallery', 'one.png');
    $two = mlAddMedia($album, 'gallery', 'two.png');
    mlTest()->actingAs(mlTest()->admin());

    $form = mlTest()->pageProps("/media/albums/{$album->id}/edit")['content'][0];
    $field = mlComponent($form, 'gallery');

    expect($field['type'])->toBe('FileUpload')
        ->and($form['state']['gallery'])->toBe([$one->uuid, $two->uuid])
        ->and(array_column($field['files'], 'name'))->toBe([$one->file_name, $two->file_name])
        ->and($field['files'][0]['url'])->toBe($one->getUrl());
});

it('adds, removes and reorders media when the record is saved', function (): void {
    $album = mlAlbum();
    $one = mlAddMedia($album, 'gallery', 'one.png');
    $two = mlAddMedia($album, 'gallery', 'two.png');
    $three = mlAddMedia($album, 'gallery', 'three.png');
    mlTest()->actingAs(mlTest()->admin());
    $token = mlTest()->pageProps("/media/albums/{$album->id}/edit")['token'];
    $new = mlTest()->upload('new.png');

    mlTest()->runAction($token, 'save', ['title' => 'Holiday', 'cover' => [], 'gallery' => [$three->uuid, $new, $one->uuid]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $gallery = $album->refresh()->getMedia('gallery');

    expect($gallery->pluck('name')->all())->toBe(['three', 'Gallery new', 'one'])
        ->and(Media::query()->whereKey($two->id)->exists())->toBeFalse();
});

it('rejects media of another record', function (): void {
    $album = mlAlbum();
    $other = mlAlbum('Other');
    $foreign = mlAddMedia($other, 'gallery');
    mlTest()->actingAs(mlTest()->admin());
    $token = mlTest()->pageProps("/media/albums/{$album->id}/edit")['token'];

    mlTest()->postJson('/media/_alfakit/actions', ['token' => $token, 'action' => 'save', 'data' => ['title' => 'Holiday', 'gallery' => [$foreign->uuid]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('gallery.0');

    expect($other->refresh()->getMedia('gallery'))->toHaveCount(1)
        ->and($album->refresh()->getMedia('gallery'))->toHaveCount(0);
});

it('replaces the media of a single file collection', function (): void {
    $album = mlAlbum();
    $old = mlAddMedia($album, 'cover', 'old.png');
    mlTest()->actingAs(mlTest()->admin());
    $token = mlTest()->pageProps("/media/albums/{$album->id}/edit")['token'];
    $new = mlTest()->upload('fresh.png');

    mlTest()->runAction($token, 'save', ['title' => 'Holiday', 'cover' => $new, 'gallery' => []])->assertSessionHasNoErrors();

    $cover = $album->refresh()->getMedia('cover');

    expect($cover)->toHaveCount(1)
        ->and($cover->first()?->name)->toBe('fresh')
        ->and(Media::query()->whereKey($old->id)->exists())->toBeFalse();
});

it('requires a signed-in user to save', function (): void {
    mlTest()->actingAs(mlTest()->admin());
    $token = mlTest()->pageProps('/media/albums/create')['token'];

    auth()->logout();

    $status = mlTest()->postJson('/media/_alfakit/actions', ['token' => $token, 'action' => 'create', 'data' => ['title' => 'Nope']])->status();

    expect($status)->toBeIn([401, 403]);

    expect(Album::query()->count())->toBe(0);
});

it('shows the media in table columns, using generated conversions', function (): void {
    $album = mlAlbum();
    $cover = mlAddMedia($album, 'cover');
    mlTest()->actingAs(mlTest()->admin());

    $table = mlTest()->pageProps('/media/albums')['content'][0];
    $column = collect($table['columns'])->firstWhere('name', 'cover');

    expect($column['type'])->toBe('ImageColumn')
        ->and($table['records'][0]['cells']['cover']['state'])->toBe([$cover->getUrl()]);

    // Once the conversion exists it is shown instead of the original.
    $cover->markAsConversionGenerated('thumb');
    $cover->save();

    $table = mlTest()->pageProps('/media/albums')['content'][0];

    expect($table['records'][0]['cells']['cover']['state'])->toBe([$cover->getUrl('thumb')]);
});

it('shows the media in infolist entries', function (): void {
    $album = mlAlbum();
    $one = mlAddMedia($album, 'gallery', 'one.png');
    mlAddMedia($album, 'cover', 'cover.png');
    mlTest()->actingAs(mlTest()->admin());

    $infolist = collect(mlTest()->pageProps("/media/albums/{$album->id}")['content'])->firstWhere('type', 'Infolist');
    $entry = mlComponent($infolist, 'gallery');

    expect($entry['type'])->toBe('ImageEntry')
        ->and($entry['state'])->toBe([$one->getUrl()]);
});

it('filters the media and can read every collection', function (): void {
    $album = mlAlbum();
    $keep = mlAddMedia($album, 'gallery', 'keep.png');
    mlAddMedia($album, 'gallery', 'skip.png');
    $cover = mlAddMedia($album, 'cover', 'cover.png');

    $entry = SpatieMediaLibraryImageEntry::make('gallery')
        ->collection('gallery')
        ->filterMediaUsing(fn (Collection $media): Collection => $media->where('name', 'keep'));
    Schema::make()->components([$entry])->record($album->refresh());

    $all = SpatieMediaLibraryImageEntry::make('everything')->allCollections();
    Schema::make()->components([$all])->record($album);

    expect($entry->getState())->toBe([$keep->uuid])
        ->and($all->getState())->toEqualCanonicalizing([$keep->uuid, $album->getMedia('gallery')->last()?->uuid, $cover->uuid]);
});

it('uses the disk of the collection and signed urls for private disks', function (): void {
    $album = mlAlbum();
    $document = $album->addMedia(UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'))->toMediaCollection('documents');

    $field = SpatieMediaLibraryFileUpload::make('documents')->collection('documents');
    Schema::make()->components([$field])->record($album->refresh())->fill();

    expect($document->disk)->toBe('secret')
        ->and($field->getDiskName())->toBe('secret')
        ->and($field->getState())->toBe([$document->uuid])
        ->and($field->getFileUrl($document->uuid))->toContain('signature=');

    $explicit = SpatieMediaLibraryFileUpload::make('gallery')->collection('gallery')->disk('local');
    Schema::make()->components([$explicit])->model(Album::class);

    expect($explicit->getDiskName())->toBe('local')
        ->and(SpatieMediaLibraryFileUpload::make('gallery')->collection('gallery')->getCollection())->toBe('gallery');
});
