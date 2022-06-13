<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    /**
     * @test
     */
    public function itUploadsAnImageAndStoresItUnderTheOffice()
    {
        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson("/api/offices/{$office->id}/images", [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        // Storage::assertExists(
        //     $response->json('data.path')
        // );
    }

    /**
     * @test
     */
    public function itDeletesAnImage()
    {
        Storage::put('/office_image.jpg', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.jpg'
        ]);
        $image =  $office->images()->create([
            'path' => 'office_image.jpg'
        ]);
        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertOk();
        $this->assertModelMissing($image);

        Storage::assertMissing('office_image.jpg');
    }

    /**
     * @test
     */
    public function itDoesnotDeleteTheOnlyImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image =  $office->images()->create([
            'path' => 'office_image.jpg'
        ]);
        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Can not delete the only image']);;
    }

    /**
     * @test
     */
    public function itDoesnotDeleteTheFeaturedImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();


        $office->images()->create([
            'path' => 'image.jpg'
        ]);
        $image =  $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->update(['featured_image_id' => $image->id]);
        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Can not delete the featured image']);
    }

    /**
     * @test
     */
    public function itDoesnotDeleteTheImageThatBelongsToAnotherResource()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();


        $image = $office2->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->update(['featured_image_id' => $image->id]);
        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertNotFound();
    }
}
