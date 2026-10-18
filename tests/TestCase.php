<?php

declare(strict_types=1);

namespace AlfaSix\AlfakitMediaLibrary\Tests;

use AlfaSix\Alfakit\Tests\Fixtures\Models\User;
use AlfaSix\Alfakit\Tests\TestCase as BaseTestCase;
use AlfaSix\AlfakitMediaLibrary\AlfakitMediaLibraryServiceProvider;
use AlfaSix\AlfakitMediaLibrary\Tests\Fixtures\MediaPanelProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use LogicException;
use Pest\TestSuite;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            MediaLibraryServiceProvider::class,
            AlfakitMediaLibraryServiceProvider::class,
            MediaPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('media-library.disk_name', 'public');
        $app['config']->set('media-library.queue_conversions_by_default', true);
        $app['config']->set('media-library.queue_connection_name', 'null');
        $app['config']->set('filesystems.disks.secret', [
            'driver' => 'local',
            'root' => storage_path('app/media-secret'),
            'visibility' => 'private',
            'serve' => true,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }

    /**
     * The running test (Pest binds `$this` to it; this keeps static analysis typed).
     */
    public static function current(): static
    {
        $test = TestSuite::getInstance()->test;

        if (! $test instanceof static) {
            throw new LogicException('No test of this suite is running.');
        }

        return $test;
    }

    public function admin(): User
    {
        return User::query()->create(['name' => 'Admin', 'email' => 'admin'.uniqid().'@example.com', 'password' => 'password']);
    }

    public function inertiaGet(string $url): TestResponse
    {
        return $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => '']);
    }

    /**
     * @return array<string, mixed>
     */
    public function pageProps(string $url): array
    {
        return $this->inertiaGet($url)->assertOk()->json('props.page');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function runAction(string $token, string $action, array $data = []): TestResponse
    {
        return $this->post('/media/_alfakit/actions', ['token' => $token, 'action' => $action, 'data' => $data], [
            'X-Inertia' => 'true', 'X-Inertia-Version' => '', 'Accept' => 'application/json',
        ]);
    }

    public function upload(string $name = 'photo.png'): string
    {
        return (string) $this->post('/media/_alfakit/uploads', ['file' => UploadedFile::fake()->image($name, 10, 10)], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('key');
    }
}
