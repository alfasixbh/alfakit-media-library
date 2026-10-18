<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\AlbumResource\Pages;

use AlfaSix\Alfakit\Resources\Pages\EditRecord;
use AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\AlbumResource;

class EditAlbum extends EditRecord
{
    protected static string $resource = AlbumResource::class;
}
