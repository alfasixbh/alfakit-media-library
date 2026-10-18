<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary;

use Illuminate\Support\ServiceProvider;

class AlfakitMediaLibraryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(dirname(__DIR__).'/resources/lang', 'alfakit-media-library');
    }
}
