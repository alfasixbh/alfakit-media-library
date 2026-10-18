<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tests\Fixtures;

use AlfaSix\Alfakit\Forms\Components\TextInput;
use AlfaSix\Alfakit\Resources\Resource;
use AlfaSix\Alfakit\Schemas\Schema;
use AlfaSix\Alfakit\Tables\Columns\TextColumn;
use AlfaSix\Alfakit\Tables\Table;
use AlfaSix\AlfakitMediaLibrary\Forms\Components\SpatieMediaLibraryFileUpload;
use AlfaSix\AlfakitMediaLibrary\Infolists\Components\SpatieMediaLibraryImageEntry;
use AlfaSix\AlfakitMediaLibrary\Tables\Columns\SpatieMediaLibraryImageColumn;

class AlbumResource extends Resource
{
    protected static ?string $model = Album::class;

    protected static ?string $slug = 'albums';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            SpatieMediaLibraryFileUpload::make('cover')->collection('cover')->image()->conversion('thumb'),
            SpatieMediaLibraryFileUpload::make('gallery')
                ->collection('gallery')
                ->multiple()
                ->reorderable()
                ->customProperties(['source' => 'panel'])
                ->mediaName(fn (string $originalName): string => 'Gallery '.pathinfo($originalName, PATHINFO_FILENAME)),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            SpatieMediaLibraryImageEntry::make('gallery')->collection('gallery'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title'),
            SpatieMediaLibraryImageColumn::make('cover')->collection('cover')->conversion('thumb'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AlbumResource\Pages\ListAlbums::route('/'),
            'create' => AlbumResource\Pages\CreateAlbum::route('/create'),
            'view' => AlbumResource\Pages\ViewAlbum::route('/{record}'),
            'edit' => AlbumResource\Pages\EditAlbum::route('/{record}/edit'),
        ];
    }
}
