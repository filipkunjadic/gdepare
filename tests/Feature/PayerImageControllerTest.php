<?php

use App\Models\Receiver;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests receive 401 when uploading or removing payer images', function () {
    $this->postJson(route('payers.image.store', 1))->assertUnauthorized();
    $this->deleteJson(route('payers.image.destroy', 1))->assertUnauthorized();
})->group('no-database');

test('payer images are immediately converted to centered cover crops in 200 pixel WebP format', function (int $width, int $height, string $extension) {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $file = UploadedFile::fake()->image('payer.'.$extension, $width, $height);
    $source = imagecreatetruecolor($width, $height);
    imagefill($source, 0, 0, imagecolorallocate($source, 0, 0, 255));
    $side = min($width, $height);
    $left = (int) (($width - $side) / 2);
    $top = (int) (($height - $side) / 2);
    imagefilledrectangle($source, $left, $top, $left + $side - 1, $top + $side - 1, imagecolorallocate($source, 0, 255, 0));
    match ($extension) {
        'png' => imagepng($source, $file->getPathname()),
        'jpeg' => imagejpeg($source, $file->getPathname()),
        'webp' => imagewebp($source, $file->getPathname()),
    };

    $response = $this->actingAs($payer->user)->postJson(route('payers.image.store', $payer), ['image' => $file])->assertOk();

    $media = $payer->getFirstMedia('image');
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
    $dimensions = getimagesize($media->getPath('avatar'));
    expect([$dimensions[0], $dimensions[1], $dimensions['mime']])->toBe([200, 200, 'image/webp']);
    $converted = imagecreatefromwebp($media->getPath('avatar'));
    foreach ([[10, 10], [190, 10], [100, 100], [10, 190], [190, 190]] as [$x, $y]) {
        $color = imagecolorsforindex($converted, imagecolorat($converted, $x, $y));
        expect($color['green'])->toBeGreaterThan(230);
        expect($color['blue'])->toBeLessThan(20);
        expect($color['red'])->toBeLessThan(20);
    }
    $response->assertExactJson(['image_url' => $media->getUrl('avatar')]);
    $this->getJson(route('payers.index'))->assertExactJson([
        ['id' => $payer->id, 'name' => $payer->name, 'image_url' => $media->getUrl('avatar'), 'default_tags' => [], 'transactions_count' => 0],
    ]);
})->with([
    'landscape PNG' => [600, 200, 'png'],
    'portrait JPEG' => [200, 600, 'jpeg'],
    'small WebP' => [40, 80, 'webp'],
])->group('database');

test('replacing a payer image removes the previous original and conversion', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $oldMedia = $payer->addMedia(UploadedFile::fake()->image('old.png'))->toMediaCollection('image');
    $originalPath = $oldMedia->getPathRelativeToRoot();
    $conversionPath = $oldMedia->getPathRelativeToRoot('avatar');

    $response = $this->actingAs($payer->user)->postJson(route('payers.image.store', $payer), [
        'image' => UploadedFile::fake()->image('new.png'),
    ])->assertOk();

    $media = $payer->refresh()->getFirstMedia('image');
    expect($media->id)->not->toBe($oldMedia->id);
    $this->assertModelMissing($oldMedia);
    $this->assertDatabaseCount('media', 1);
    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('public')->assertMissing($conversionPath);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
    $response->assertJsonPath('image_url', $media->getUrl('avatar'));
})->group('database');

test('removing a payer image deletes its files and leaves the payer intact', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');
    $originalPath = $media->getPathRelativeToRoot();
    $conversionPath = $media->getPathRelativeToRoot('avatar');

    $this->actingAs($payer->user)->deleteJson(route('payers.image.destroy', $payer))->assertNoContent();

    $this->assertModelExists($payer);
    $this->assertModelMissing($media);
    Storage::disk('local')->assertMissing($originalPath);
    Storage::disk('public')->assertMissing($conversionPath);
    $this->getJson(route('payers.index'))->assertJsonPath('0.image_url', null);
    $this->deleteJson(route('payers.image.destroy', $payer))->assertNoContent();
})->group('database');

test('other users and missing payers return 404 without modifying images', function () {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');
    $this->actingAs(User::factory()->create());

    foreach ([$payer->id, 999999] as $id) {
        $this->postJson(route('payers.image.store', $id), ['image' => UploadedFile::fake()->image('new.png')])->assertNotFound();
        $this->deleteJson(route('payers.image.destroy', $id))->assertNotFound();
    }

    $this->assertModelExists($media);
    $this->assertDatabaseCount('media', 1);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
})->group('database');

test('invalid uploads return 422 and preserve the existing image', function (Closure $upload, string $message) {
    Storage::fake('local');
    Storage::fake('public');
    $payer = Receiver::factory()->create();
    $media = $payer->addMedia(UploadedFile::fake()->image('payer.png'))->toMediaCollection('image');

    $this->actingAs($payer->user)->postJson(route('payers.image.store', $payer), ['image' => $upload()])
        ->assertUnprocessable()->assertJsonValidationErrors('image')->assertJsonPath('errors.image.0', $message);

    $this->assertModelExists($media);
    $this->assertDatabaseCount('media', 1);
    Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('avatar'));
})->with([
    'missing image' => [fn () => null, 'The image field is required.'],
    'disguised text' => [fn () => UploadedFile::fake()->createWithContent('payer.png', 'This is not an image.')->mimeType('text/plain'), 'The image field must be an image.'],
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('payer.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), 'The image field must be an image.'],
    'GIF' => [fn () => UploadedFile::fake()->image('payer.gif'), 'The image field must be a file of type: jpg, jpeg, png, webp.'],
    'over 2 MB' => [fn () => UploadedFile::fake()->image('payer.png')->size(2049), 'The image field must not be greater than 2048 kilobytes.'],
    'too wide' => [fn () => UploadedFile::fake()->image('payer.png', 4097, 10), 'The image field has invalid image dimensions.'],
    'too tall' => [fn () => UploadedFile::fake()->image('payer.png', 10, 4097), 'The image field has invalid image dimensions.'],
])->group('database');
