<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\AlbumResource\Pages;

use AlfaSix\Alfakit\Resources\Pages\CreateRecord;
use AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\AlbumResource;

class CreateAlbum extends CreateRecord
{
    protected static string $resource = AlbumResource::class;
}
