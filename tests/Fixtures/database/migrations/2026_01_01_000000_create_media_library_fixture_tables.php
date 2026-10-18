<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (require dirname(__DIR__, 7).'/vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();

        Schema::create('albums', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }
};
