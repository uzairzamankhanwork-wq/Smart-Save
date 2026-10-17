<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_icons_exist(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_service_worker_never_caches_pages(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        // Navigations go to the network; only /build/ and /icons/ are put in the cache.
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString('event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)))', $sw);
        $this->assertSame(1, substr_count($sw, 'cache.put('));
    }

    public function test_layouts_link_the_manifest(): void
    {
        $this->get('/login')->assertOk()->assertSee('rel="manifest"', false)->assertSee('apple-touch-icon', false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('rel="manifest"', false)->assertSee('Install app');
    }
}
