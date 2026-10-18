<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tests\Fixtures;

use AlfaSix\Alfakit\Panel;
use AlfaSix\Alfakit\PanelProvider;
use AlfaSix\AlfakitMediaLibrary\MediaLibraryPlugin;

class MediaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('media')
            ->path('media')
            ->login()
            ->middleware(['web'])
            ->resources([AlbumResource::class])
            ->plugin(MediaLibraryPlugin::make());
    }
}
