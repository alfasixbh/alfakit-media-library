# AlfaKit Media Library

<p><picture><source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/alfasixbh/alfakit/main/art/alfasix/alfa-six-micro-white.svg"><img src="https://raw.githubusercontent.com/alfasixbh/alfakit/main/art/alfasix/alfa-six-micro-navy.svg" alt="" width="24" height="18" align="top"></picture> <sub>by <a href="https://alfasixbh.com">Alfa Six</a></sub></p>

> This is a read-only split of the [AlfaKit monorepo](https://github.com/alfasixbh/alfakit/tree/main/packages/plugins/media-library). Please open issues and pull requests there.

Spatie Laravel Media Library fields, columns and entries for [AlfaKit](https://alfakit.dev) panels. They render in React, Vue and Svelte with AlfaKit's built-in components.

## Installation

```bash
composer require alfasix/alfakit-media-library
```

Install and migrate [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary), then add `HasMedia` / `InteractsWithMedia` to your model.

Registering the plugin is optional:

```php
use AlfaSix\AlfakitMediaLibrary\MediaLibraryPlugin;

$panel->plugin(MediaLibraryPlugin::make());
```

## Usage

```php
use AlfaSix\AlfakitMediaLibrary\Forms\Components\SpatieMediaLibraryFileUpload;
use AlfaSix\AlfakitMediaLibrary\Infolists\Components\SpatieMediaLibraryImageEntry;
use AlfaSix\AlfakitMediaLibrary\Tables\Columns\SpatieMediaLibraryImageColumn;

// Form: new uploads are added to the collection, removed media deleted, the order saved.
SpatieMediaLibraryFileUpload::make('gallery')
    ->collection('gallery')
    ->multiple()
    ->reorderable()
    ->conversion('thumb')
    ->customProperties(['source' => 'admin'])
    ->responsiveImages();

// Table
SpatieMediaLibraryImageColumn::make('cover')->collection('cover')->conversion('thumb')->circular();

// Infolist
SpatieMediaLibraryImageEntry::make('gallery')->collection('gallery')->stacked();
```

The field also takes `conversionsDisk()`, `manipulations()`, `properties()`, `customHeaders()`, `mediaName()` and `filterMediaUsing()`, plus every `FileUpload` option. Without `disk()` it uses the disk of the model's media collection; private disks get signed temporary URLs.

Full documentation: [alfakit.dev/docs/plugins/media-library](https://alfakit.dev/docs/plugins/media-library).

## License

MIT. See [LICENSE.md](LICENSE.md).
