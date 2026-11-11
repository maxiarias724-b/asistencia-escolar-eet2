<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verifica los archivos y enlaces de la PWA (manifest, íconos, service worker, página offline).
 * No requiere base de datos.
 */
class PwaTest extends TestCase
{
    public function test_login_page_links_manifest_icons_and_pwa_scripts(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.json', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('css/pwa.css', false)
            ->assertSee('js/pwa.js', false)
            ->assertSee('data-sw=', false)
            ->assertSee('sw.js', false)
            ->assertSee('name="viewport"', false);
    }

    public function test_manifest_is_valid_and_its_icons_exist(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);
        $this->assertIsArray($manifest, 'manifest.json no es un JSON válido');

        foreach (['name', 'short_name', 'start_url', 'scope', 'display', 'background_color', 'theme_color', 'icons'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "Falta la clave '$key' en el manifest");
        }
        $this->assertSame('standalone', $manifest['display']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path($icon['src']), "No existe el ícono {$icon['src']}");
            $sizes[] = $icon['sizes'];
        }
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function test_service_worker_never_caches_html_navigation_and_offline_page_exists(): void
    {
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));

        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString('OFFLINE_URL', $sw);
        // Solo GET: nunca se interceptan formularios (POST) ni peticiones de otro origen.
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        $this->assertStringContainsString('url.origin !== self.location.origin', $sw);
    }
}
