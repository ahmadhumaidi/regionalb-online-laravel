<?php

namespace Tests\Feature;

use App\Http\Controllers\SocialContentUploadController;
use App\Models\RsmUser;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\Menu;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SocialContentUploadPageTest extends TestCase
{
    public function test_menu_and_native_content_routes_are_registered(): void
    {
        $this->assertSame('Upload Konten Sosmed', Menu::title('upload-konten-sosmed'));
        $this->assertStringEndsWith('/upload-konten-sosmed', Menu::routeFor('upload-konten-sosmed'));
        $this->assertSame('GET', Route::getRoutes()->getByName('upload-konten-sosmed')->methods()[0]);
        $this->assertContains('POST', Route::getRoutes()->getByName('upload-konten-sosmed.store')->methods());
        $this->assertContains('PATCH', Route::getRoutes()->getByName('upload-konten-sosmed.update')->methods());
        $this->assertContains('DELETE', Route::getRoutes()->getByName('upload-konten-sosmed.destroy')->methods());
    }

    public function test_content_score_follows_monitoring_rules(): void
    {
        $method = new \ReflectionMethod(new SocialContentUploadController, 'score');
        $method->setAccessible(true);
        $controller = new SocialContentUploadController;

        $this->assertSame(10, $method->invoke($controller, 'feed', false));
        $this->assertSame(20, $method->invoke($controller, 'reels', true));
        $this->assertSame(10, $method->invoke($controller, 'story', true));
        $this->assertSame(15, $method->invoke($controller, 'tiktok', false));
        $this->assertSame(10, $method->invoke($controller, 'facebook', false));
    }

    public function test_feed_and_story_archive_links_are_configured(): void
    {
        $this->assertStringContainsString('1eJJYaOYYo-ePmPX--IfFzpIIy9y1crYw05SaRF515kE', config('services.social_content_sheets.feed_url'));
        $this->assertStringContainsString('1oFcweYV_5rsm-PguG2AsB-Yh_QrH99xcBlRUwPrPL6Q', config('services.social_content_sheets.story_url'));
    }

    public function test_staff_campus_option_keeps_the_staff_regional(): void
    {
        $staff = new RsmUser([
            'name' => 'Reni Nurliani',
            'role' => RsmUser::ROLE_STAFF,
            'area' => 'Regional B',
            'regional' => 'Regional 5',
            'campus_name' => 'IMA',
        ]);

        $options = ReferenceOptionsService::build('Regional B', $staff);

        $this->assertSame([
            'id' => null,
            'label' => 'IMA',
            'wilayah' => 'Regional 5',
        ], $options['campuses'][0]);
    }
}
