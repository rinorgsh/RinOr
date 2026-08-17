<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_manifeste_est_servi_et_valide(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        // `display: standalone` est ce qui retire la barre d'adresse : sans lui,
        // l'icône n'est qu'un marque-page.
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('RinOr', $manifest['short_name']);
        $this->assertNotEmpty($manifest['theme_color']);
    }

    public function test_toutes_les_icones_declarees_existent_a_la_bonne_taille(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path, "Icône déclarée mais absente : {$icon['src']}");

            [$expectedW, $expectedH] = array_map('intval', explode('x', $icon['sizes']));
            [$actualW, $actualH] = getimagesize($path);

            $this->assertSame($expectedW, $actualW, "Largeur inattendue pour {$icon['src']}");
            $this->assertSame($expectedH, $actualH, "Hauteur inattendue pour {$icon['src']}");
        }
    }

    public function test_une_icone_maskable_est_fournie(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $purposes = array_column($manifest['icons'], 'purpose');

        // Sans icône maskable, Android rogne l'icône « any » dans un cercle et
        // coupe le dessin.
        $this->assertContains('maskable', $purposes);
    }

    public function test_l_icone_apple_existe(): void
    {
        // iOS ignore les icônes du manifeste : sans ce fichier, l'écran
        // d'accueil affiche une capture floue de la page.
        $path = public_path('icons/apple-touch-icon.png');
        $this->assertFileExists($path);
        $this->assertSame([180, 180], array_slice(getimagesize($path), 0, 2));
    }

    public function test_le_html_declare_le_manifeste_et_les_balises_ios(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/')->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('/manifest.webmanifest', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('name="apple-mobile-web-app-title"', $html);
        $this->assertStringContainsString('name="mobile-web-app-capable"', $html);
        $this->assertStringContainsString('viewport-fit=cover', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
    }
}
