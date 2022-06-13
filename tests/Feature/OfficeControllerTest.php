<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    /**
     *
     * @test
     */
    public function itListAllOfficesInPaginatedWay()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();
        $tags2 = Tag::factory(2)->create();
        
        Office::factory(30)->create();

        Office::factory()->for($user)->hasAttached($tags)->create();
        Office::factory()->hasAttached($tags2)->create();

        $response = $this->getJson('/offices');
     
        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(20, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'title']]]);
    }

    /**
     * @test
     * 
     */

    public function itOnlyListOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();

        Office::factory()->hidden()->create();
        Office::factory()->pending()->create();

        $response = $this->getJson('/offices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * @test
     * 
     */

    public function itListsOfficesIncludingHiddenAndUnApprovedIfFilteringForTheCurrentLoggedInUser()
    {
        $user =  User::factory()->create();
        Office::factory(3)->for($user)->create();

        Office::factory()->hidden()->for($user)->create();
        Office::factory()->pending()->for($user)->create();

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson("/offices?user_id={$user->id}");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }
    /**
     * @test
     * 
     */

    public function itFiltersByTags()
    {
        $tags = Tag::factory(2)->create();

        $office = Office::factory()->hasAttached($tags)->create();

        Office::factory()->hasAttached($tags->first())->create();
        Office::factory()->create();


        $response = $this->getJson(
            '/offices?' . http_build_query([
                'tags' => $tags->pluck('id')->toArray()
            ])
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id);
    }

    /**
     * @test
     * 
     */

    public function itFiltersByUserId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();


        $response = $this->getJson(
            "/offices?user_id={$user->id}"
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id);
    }

    /**
     * @test
     * 
     */

    public function itFiltersByVisitorId()
    {
        Office::factory(3)->create();

        $visitor = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($visitor)->create();

        $response = $this->getJson(
            "/offices?visitor_id={$visitor->id}"
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $office->id);
    }

    /**
     * @test
     * 
     */

    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        Office::factory()->for($user)->hasTags(1)->hasImages(1)->create();


        $response = $this->getJson('/offices');
        // $response->dump();
        $response->assertOk()
            ->assertJsonCount(1, 'data.0.tags')
            ->assertJsonCount(1, 'data.0.images')
            ->assertJsonPath('data.0.user.id', $user->id);
    }

    /**
     * @test
     * 
     */
    public function itReturnsTheNumberofActiveReservations()
    {

        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create();

        Reservation::factory()->for($office)->cancelled()->create();


        $response = $this->getJson('/offices');

        $response->assertOk()
            ->assertJsonPath('data.0.reservations_count', 1);
    }
    /**
     * @test
     * 
     */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        // 25.410847486334404, 68.36278413502485 Hyderabad -- Near
        // 30.1880652105351, 67.00220098092423  Quetta -- Far

        // 25.115076960811663, 67.21299936713562 Karachi -- Current Location


        Office::factory()->create([
            'lat' => '30.1880652105351',
            'lng' => '67.00220098092423',
            'title' => 'Quetta'
        ]);

        Office::factory()->create([
            'lat' => '25.410847486334404',
            'lng' => '68.36278413502485',
            'title' => 'Hyderabad'
        ]);

        // Looking distance from Karachi
        $response = $this->getJson('/offices?lat=25.115076960811663&lng=67.21299936713562');

        // dd($response->json());

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Hyderabad')
            ->assertJsonPath('data.1.title', 'Quetta');

        $response = $this->getJson('/offices');

        $response->assertOk()
            ->assertJsonPath('data.0.title', 'Quetta')
            ->assertJsonPath('data.1.title', 'Hyderabad');
    }

    /**
     * @test
     * 
     */

    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->hasTags(1)->hasImages(1)->create();

        Reservation::factory()->for($office)->create();
        Reservation::factory()->for($office)->cancelled()->create();


        $response = $this->getJson("/offices/{$office->id}");

        $response->assertOk()
            ->assertJsonPath('data.reservations_count', 1)
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.user.id', $user->id);
    }


    /**
     * @test
     * 
     */
    public function itCreatesAnOffice()
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create();
        $tags = Tag::factory(2)->create();

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/offices', [
            'title' => 'Office in Quetta',
            'description' => 'Description',
            'lat' => '30.1880652105351',
            'lng' => '67.00220098092423',
            'address_line1' => 'Address of Quetta office',
            'price_per_day' => 10_000,
            'monthly_discount' => 5,
            'tags' => $tags->pluck('id')->toArray()
        ]);

        // dd($response->json());
        $response->assertCreated()
            ->assertJsonPath('data.title', 'Office in Quetta')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.reservations_count', 0)
            ->assertJsonCount(2, 'data.tags');


        $this->assertDatabaseHas('offices', [
            'title' => 'Office in Quetta'
        ]);
        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     * 
     */
    public function itDoesnotAllowCreatingIfScopeIsNotProvided()
    {
        $user = User::factory()->createQuietly();

        // $token = $user->createToken('test', []);
        Sanctum::actingAs($user, []);


        $response = $this->postJson('/offices');
        // dd($response->status());
        // dd($response->json());
        $response->assertForbidden();
    }

    /**
     * @test
     * 
     */
    public function itAllowsCreatingIfScopeProvided()
    {
        $user = User::factory()->createQuietly();
        Sanctum::actingAs($user, ['office.create']);

        $response = $this->postJson('/offices');

        $this->assertFalse($response->isForbidden());
    }

    /**
     * @test
     * 
     */
    public function itUpdatesAnOffice()
    {
        $user = User::factory()->create();
        $tags = Tag::factory(3)->create();
        $anotherTag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson("/offices/{$office->id}", [
            'title' => 'Amazing Office in Quetta',
            'tags' => [$tags[0]->id, $anotherTag->id]
        ]);


        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $anotherTag->id)
            ->assertJsonPath('data.title', 'Amazing Office in Quetta');
    }

    /**
     * @test
     * 
     */
    public function itUpdatesTheFeaturedImageOfAnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image =  $office->images()->create([
            'path' => 'image.jpg'
        ]);

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/offices/{$office->id}", [
            'featured_image_id' => $image->id,
        ]);


        $response->assertOk()
            ->assertJsonPath('data.featured_image_id', $image->id);
    }
    /**
     * @test
     * 
     */
    public function itDoesnotUpdateTheFeaturedImageThatBelongsToAnotherOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image =  $office2->images()->create([
            'path' => 'image.jpg'
        ]);

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/offices/{$office->id}", [
            'featured_image_id' => $image->id,
        ]);


        $response->assertUnprocessable()
            ->assertInvalid('featured_image_id');
    }

    /**
     * @test
     * 
     */
    public function itDoesnotUpdatesOfficeThatNotBelongToUser()
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();
        $office = Office::factory()->for($anotherUser)->create();


        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/offices/{$office->id}", [
            'title' => 'Amazing Office in Quetta',
        ]);

        $response->assertForbidden();
    }

    /**
     * @test
     * 
     */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();


        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson("/offices/{$office->id}", [
            'price_per_day' => 20_000,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
     * @test
     * 
     */
    public function itCanDeleteOffices()
    {
        Storage::put('/office_image.jpg', 'empty');
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/offices/{$office->id}");

        $response->assertOk();
        $this->assertSoftDeleted($office);
        $this->assertModelMissing($image);

        Storage::assertMissing('office_image.jpg');
    }

    /**
     * @test
     * 
     */
    public function itCannotDeleteAnOfficeThatHasReservations()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();


        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson("/offices/{$office->id}");

        $response->assertUnprocessable();
        $this->assertNotSoftDeleted($office);
    }
}
