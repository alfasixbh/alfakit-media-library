<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\AlbumResource\Pages;

use AlfaSix\Alfakit\Resources\Pages\ListRecords;
use AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\AlbumResource;

class ListAlbums extends ListRecords
{
    protected static string $resource = AlbumResource::class;
}
