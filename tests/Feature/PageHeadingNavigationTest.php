<?php

namespace Tests\Feature;

use App\Models\HealthUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PageHeadingNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    private function hasClass(string $name): string
    {
        return 'contains(concat(" ", normalize-space(@class), " "), " '.$name.' ")';
    }

    public function test_shared_headers_keep_their_title_navigation_and_action_structure_for_supported_roles(): void
    {
        $unit = HealthUnit::create(['name' => 'Heading navigation RHU', 'facility_type' => 'Rural Health Unit']);
        $managementRoutes = ['medicine.index', 'medicine.stock_on_hand', 'inventory.index', 'stock_logs.index',
            'allocation.index', 'reports.inventory', 'reports.stock', 'reports.allocation',
            'reports.dispensing', 'reports.near_expiry', 'elmis.export', 'notifications.index'];
        foreach ([['admin', null], ['staff', $unit->id], ['midwife', $unit->id]] as [$role, $unitId]) {
            $user = User::factory()->create(['role' => $role, 'health_unit_id' => $unitId]);
            $routes = $role === 'midwife' ? ['medicine.index', 'medicine.stock_on_hand', 'dispensing.index', 'notifications.index'] : $managementRoutes;
            if ($role === 'staff') {
                $routes[] = 'dispensing.index';
            } elseif ($unitId === null) {
                $routes = array_values(array_diff($routes, ['reports.dispensing']));
            }
            foreach ($routes as $route) {
                $page = $this->withSession(['user_id' => $user->id])->get(route($route));
                $this->assertSame(200, $page->getStatusCode(), $role.' '.$route.' redirected to '.$page->headers->get('Location'));
                $xpath = $this->xpath($page->getContent());
                $headers = $xpath->query('//*[@id="main-content"]//*['.$this->hasClass('page-heading').']');
                $this->assertGreaterThan(0, $headers->length, $role.' '.$route.' needs an adaptable heading.');
                foreach ($headers as $header) {
                    $main = $xpath->query('./*['.$this->hasClass('page-heading-main').']', $header);
                    $this->assertCount(1, $main, $role.' '.$route);
                    $titles = $xpath->query('./*['.$this->hasClass('page-heading-title').']', $main->item(0));
                    $this->assertCount(1, $titles, $role.' '.$route);
                    $this->assertGreaterThan(0, $xpath->query('.//h1 | .//h2 | .//h3', $titles->item(0))->length);
                    foreach ($xpath->query('.//*['.$this->hasClass('page-section-nav').']', $header) as $nav) {
                        $this->assertSame($main->item(0), $nav->parentNode, $role.' '.$route.' section navigation must share the title row.');
                        $this->assertNotSame('', $nav->getAttribute('aria-label'));
                        $this->assertGreaterThan(0, $xpath->query('./a[@href]', $nav)->length);
                        $this->assertCount(1, $xpath->query('./a[@aria-current="page" or contains(concat(" ", normalize-space(@class), " "), " is-active ")]', $nav), $role.' '.$route.' must retain selected section.');
                    }
                    foreach ($xpath->query('.//*['.$this->hasClass('page-heading-actions').']', $header) as $actions) {
                        $this->assertSame($header, $actions->parentNode, $role.' '.$route.' actions must be a direct heading child.');
                        foreach ($xpath->query('.//form', $actions) as $form) {
                            $this->assertCount(0, $xpath->query('ancestor::form', $form));
                            $this->assertNotSame('', $form->getAttribute('action'));
                        }
                    }
                }
                $this->assertStringContainsString('/css/page-headings.css', $page->getContent());
                $this->assertStringContainsString('/js/page-headings.js', $page->getContent());
            }
            if ($role !== 'midwife') {
                $page = $this->get(route('inventory.index', ['view' => 'history']))->assertOk();
                $xpath = $this->xpath($page->getContent());
                $this->assertCount(1, $xpath->query('//*[@id="inventory-history-title"]/parent::*['.$this->hasClass('page-heading-title').']/parent::*['.$this->hasClass('page-heading-main').']'));
            }
        }
    }

    public function test_section_links_keep_the_original_destinations_and_inventory_filters(): void
    {
        $unit = HealthUnit::create(['name' => 'Filtered heading RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $this->withSession(['user_id' => $viewer->id]);
        $filters = ['rhu_filter' => (string) $unit->id, 'year' => '2024', 'date_from' => '2024-01-01', 'date_to' => '2024-01-31'];
        foreach ([['inventory.index', [], 0], ['stock_logs.index', [], 1], ['inventory.index', ['view' => 'history'], 2]] as [$route, $extra, $active]) {
            $page = $this->get(route($route, $extra + $filters))->assertOk();
            $xpath = $this->xpath($page->getContent());
            $links = $xpath->query('//nav[@aria-label="Inventory sections" and '.$this->hasClass('page-section-nav').']/a');
            $expected = [route('inventory.index', $filters), route('stock_logs.index', $filters), route('inventory.index', ['view' => 'history'] + $filters)];
            $this->assertSame($expected, array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($links)));
            $this->assertSame('page', $links->item($active)->getAttribute('aria-current'));
        }
        foreach (['medicine.index', 'medicine.stock_on_hand'] as $route) {
            $page = $this->get(route($route, ['rhu_filter' => $unit->id]))->assertOk();
            $links = $this->xpath($page->getContent())->query('//*[@aria-label="Medicine sections"]/a');
            $this->assertSame([route('medicine.index', ['rhu_filter' => $unit->id]), route('medicine.stock_on_hand', ['rhu_filter' => $unit->id])],
                array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($links)));
        }
        $page = $this->get(route('notifications.index', ['view' => 'archived']))->assertOk();
        $links = $this->xpath($page->getContent())->query('//*[@aria-label="Notification sections"]/a');
        $this->assertSame(array_map(fn ($view) => route('notifications.index', ['view' => $view]), ['active', 'archived', 'all']),
            array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($links)));
        $this->assertSame('page', $links->item(1)->getAttribute('aria-current'));
    }

    public function test_inventory_actions_use_native_dropdowns_and_preserve_both_filters(): void
    {
        $unit = HealthUnit::create(['name' => 'Inventory filter RHU', 'facility_type' => 'Rural Health Unit']);
        $viewer = User::factory()->create(['role' => 'admin', 'health_unit_id' => null]);
        $filters = ['rhu_filter' => (string) $unit->id, 'expiry_filter' => 'near_expiry'];
        $page = $this->withSession(['user_id' => $viewer->id])->get(route('inventory.index', $filters))->assertOk();
        $xpath = $this->xpath($page->getContent());
        $actions = '//*['.$this->hasClass('page-heading-actions').']/form';

        foreach ($filters as $name => $value) {
            $selects = $xpath->query($actions.'/select[@name="'.$name.'"][@data-native-select]');
            $this->assertCount(1, $selects);
            $select = $selects->item(0);
            $this->assertSame($value, $xpath->evaluate('string(./option[@selected]/@value)', $select));
            $this->assertSame($value, $xpath->evaluate('string('.$actions.'/input[@type="hidden"][@name="'.$name.'"]/@value)'));
        }
    }

    public function test_actual_heading_script_preserves_navigation_and_keyboard_actions_in_compact_modes(): void
    {
        $node = (new ExecutableFinder)->find('node');
        if ($node === null) {
            $this->markTestSkipped('Node.js is required for heading interaction checks.');
        }
        $process = new Process([$node, base_path('tests/Support/page-headings.cjs'), public_path('js/page-headings.js')]);
        $process->mustRun();
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(40, $result['assertions']);
        $this->assertSame([1440, 1024, 640, 320], array_column($result['widths'], 'width'));
        $this->assertFalse($result['browser_geometry_verified']);
    }
}
