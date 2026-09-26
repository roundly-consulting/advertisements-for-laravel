<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Advertisements\Contracts\CreativeRenderer;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

beforeEach(function (): void {
    Storage::fake('public');
});

it('renders a responsive image when a creative exists', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Visual ad']);
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addCreative(UploadedFile::fake()->image('creative.jpg', 800, 600), $placement);

    $html = (string) $ad->renderCreative($placement);

    expect($html)->toContain('<img')->toContain('srcset');
});

it('renders a text ad when no creative exists', function (): void {
    $ad = Advertisement::factory()->create([
        'name' => 'Buy widgets',
        'description' => 'The best widgets in town',
    ]);
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $html = (string) $ad->renderCreative($placement);

    expect($html)
        ->toContain('Buy widgets')
        ->toContain('The best widgets in town')
        ->not->toContain('<img');
});

it('sizes the text ad to the placement dimensions', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Sized ad']);
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $html = (string) $ad->renderCreative($placement);

    expect($html)->toContain('width:300px')->toContain('height:250px');
});

it('uses the generic creative when no placement-specific creative exists', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Generic ad']);
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addMedia(UploadedFile::fake()->image('generic.jpg', 800, 600))
        ->toMediaBucket($ad->fallbackCreativeBucket());

    $html = (string) $ad->renderCreative($placement);

    expect($html)->toContain('<img');
});

it('renders the text ad in the active locale', function (): void {
    $ad = Advertisement::factory()->create();
    $ad->setTranslations('name', ['en' => 'Hello', 'sk' => 'Ahoj']);
    $ad->setTranslations('description', ['en' => 'English', 'sk' => 'Slovensky']);
    $ad->save();

    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    app()->setLocale('sk');

    expect((string) $ad->renderCreative($placement))
        ->toContain('Ahoj')
        ->toContain('Slovensky')
        ->not->toContain('Hello');
});

it('renders a text ad for a placement referenced by slug or id', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Ref ad']);
    $placement = Placement::factory()->create(['slug' => 'sidebar', 'width' => 120, 'height' => 600]);

    expect((string) $ad->renderCreative('sidebar'))->toContain('Ref ad')->toContain('width:120px')
        ->and((string) $ad->renderCreative($placement->id))->toContain('Ref ad');
});

it('includes the price in the text ad when set', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Priced ad']);
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    expect((string) $ad->renderCreative($placement))->toContain('advertisement-text-ad__price');
});

it('uses a custom renderer bound in the container', function (): void {
    app()->bind(CreativeRenderer::class, fn (): CreativeRenderer => new class implements CreativeRenderer
    {
        public function render($advertisement, $placement, array $attributes = []): HtmlString
        {
            return new HtmlString('<custom-ad></custom-ad>');
        }
    });

    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create();

    expect((string) $ad->renderCreative($placement))->toBe('<custom-ad></custom-ad>');
});

it('fails loudly when the configured text-ad view does not exist', function (): void {
    config()->set('advertisements.media.text_ad_view', 'missing::text-ad');

    $ad = Advertisement::factory()->create(['name' => 'Typo view']);
    $placement = Placement::factory()->create();

    expect(fn () => $ad->renderCreative($placement))
        ->toThrow(InvalidArgumentException::class, 'View [missing::text-ad] not found.');
});
