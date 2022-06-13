<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\NewHostReservation;
use App\Notifications\NewUserReservation;
use DB;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Notification;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    /**
     * @test
     */
    public function itListsReservationsThatBelongToTheUser()
    {
        $user = User::factory()->create();

        [$reservation] = Reservation::factory(2)->for($user)->create();

        $image = $reservation->office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $reservation->office()->update([
            'featured_image_id' => $image->id
        ]);

        Reservation::factory(3)->create();

        // $this->actingAs($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/reservations');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

    /**
     * @test
     */
    public function itListsReservationsFilteredByDateRange()
    {
        $user = User::factory()->create();

        $fromDate = '2022-03-03';
        $toDate = '2022-04-04';


        // Within the date range
        $reservations =  Reservation::factory()->for($user)->createMany(
            [
                [
                    'start_date' => '2022-03-01',
                    'end_date' => '2022-03-15'
                ],
                [
                    'start_date' => '2022-03-25',
                    'end_date' => '2022-04-15'
                ],
                [
                    'start_date' => '2022-03-25',
                    'end_date' => '2022-03-29'
                ],
                [
                    'start_date' => '2022-03-01',
                    'end_date' => '2022-04-15'
                ]
            ]
        );


        // Within the date range but belongs to a different user
        Reservation::factory()->create(
            [
                'start_date' => '2022-03-25',
                'end_date' => '2022-03-29'
            ]
        );
        // Outside the date range

        Reservation::factory()->for($user)->create(
            [
                'start_date' => '2022-01-25',
                'end_date' => '2022-03-01'
            ]
        );

        Reservation::factory()->for($user)->create(
            [
                'start_date' => '2022-05-25',
                'end_date' => '2022-06-01'
            ]
        );


        Sanctum::actingAs($user, ['*']);


        $response = $this->getJson('/api/reservations?' . http_build_query([
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]));

        $response->assertJsonCount(4, 'data');

        $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());
    }

    /**
     * @test
     */
    public function itFiltersResultsByStatus()
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE
        ]);
        Reservation::factory()->for($user)->cancelled()->create();
        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson('/api/reservations?' . http_build_query([
            'status' => Reservation::STATUS_ACTIVE
        ]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }

    /**
     * @test
     */
    public function itFiltersResultsByOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($user)->for($office)->create();

        Reservation::factory()->for($user)->cancelled()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/reservations?' . http_build_query([
            'office_id' => $office->id
        ]));

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }

    /**
     * @test
     */
    public function itMakesReservations()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create([
            'price_per_day' => 1_000,
            'monthly_discount' => 10
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(40)->toDateString()
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.price', 36000)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.status', Reservation::STATUS_ACTIVE);
    }

    /**
     * @test
     */
    public function itCannotMakeReservationOnNonExistingOffice()
    {
        $user = User::factory()->create();


        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => 1000,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(40)->toDateString()
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'Invalid office_id']);
    }
    /**
     * @test
     */
    public function itCannotMakeReservationOwnOffice()
    {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(40)->toDateString()
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'You cannot make a reservation on your own office']);
    }
    /**
     * @test
     */
    public function itCannotMakeReservationLessThan2Days()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(1)->toDateString()
        ]);
        // dd($response->json());
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date' => 'The end date must be a date after start date.']);
    }

    /**
     * @test
     */
    public function itCannotMakeReservationOnSameDay()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString()
        ]);
        // dd($response->json());
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date' => 'The start date must be a date after today.']);
    }
    /**
     * @test
     */
    public function itCannotMakeReservationFor2Days()
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString()
        ]);
        // dd($response->json());
        $response->assertCreated();
    }

    /**
     * @test
     */
    public function itCannotMakeReservationThatsConflicting()
    {
        $user = User::factory()->create();

        $fromDate = now()->addDays(2)->toDateString();
        $toDate = now()->addDays(15)->toDateString();

        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create([
            'start_date' =>  now()->addDays(2)->toDateString(),
            'end_date' => $toDate
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => $fromDate,
            'end_date' => $toDate
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'You cannot make a reservation during this time']);
    }

    /**
     * @test
     */
    public function itCannotMakeReservationsOnHiddenOrPendingOffices()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create([
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        $office2 = Office::factory()->create([
            'hidden' => true
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(40)->toDateString()
        ]);

        $response2 = $this->postJson('/api/reservations', [
            'office_id' => $office2->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(40)->toDateString()
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'You cannot make a reservation on a hidden or pending office']);

        $response2->assertUnprocessable()
            ->assertJsonValidationErrors(['office_id' => 'You cannot make a reservation on a hidden or pending office']);
    }

     /**
     * @test
     */
    public function itSendNotificationsOnNewReservation()
    {
        Notification::fake();
        $user = User::factory()->create();
        $office = Office::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/reservations', [
            'office_id' => $office->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString()
        ]);
        // dd($response->json());

        Notification::assertSentTo($user, NewUserReservation::class);
        Notification::assertSentTo($office->user, NewHostReservation::class);
        $response->assertCreated();
    }
}
