<?php

namespace Tests\Feature;

use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class PortalSearchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function configureDomains(bool $enabled): void
    {
        config(['portals.enabled' => $enabled, 'portals.public_domain' => 'rhu.test',
            'portals.management_domain' => 'management.rhu.test', 'portals.surface' => 'both',
            'portals.https_only' => false]);
        app('router')->setRoutes(new RouteCollection);
        app('router')->middleware('web')->group(base_path('routes/web.php'));
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    public function test_only_the_public_portal_is_indexable_and_its_alias_uses_the_main_url(): void
    {
        $this->configureDomains(true);
        foreach (['/', '/public?q=medicine'] as $path) {
            $this->get('http://rhu.test'.$path)->assertOk()
                ->assertHeaderMissing('X-Robots-Tag')
                ->assertSee('<link rel="canonical" href="'.route('public.home').'">', false)
                ->assertDontSee('name="password"', false);
        }
        $medicine = Medicine::create(['medicine_name' => 'Public fixture', 'is_dispensable' => true]);
        $this->get('http://rhu.test/medicine/'.$medicine->id)->assertOk()
            ->assertSee('<link rel="canonical" href="http://rhu.test/medicine/'.$medicine->id.'">', false);
    }

    public function test_management_login_and_guest_redirects_tell_search_engines_not_to_index_them(): void
    {
        $this->configureDomains(true);
        $this->get('http://management.rhu.test/login')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        foreach (['/', '/inventory'] as $path) {
            $this->get('http://management.rhu.test'.$path)
                ->assertRedirect('http://management.rhu.test/login')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->withHeader('X-Forwarded-Host', 'rhu.test')
            ->get('http://management.rhu.test/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_single_host_development_still_opens_public_search_and_keeps_login_unindexed(): void
    {
        $this->configureDomains(false);
        $this->get('/')->assertOk()->assertViewIs('public.home')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/inventory')->assertRedirect(route('login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
