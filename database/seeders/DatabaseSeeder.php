<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Raheel Ahmed',
            'email' => 'raheel.ahmed@srinnovation.com',
            'password' => Hash::make('password'),
        ]);

        $office1 = Office::factory()->create();
        $office2 = Office::factory()->create();
        $office3 = Office::factory()->create();


        $office1->images()->update([
            'featured_image_id' => $office1->images()->create([
                'path' => 'storage/1.jpg'
            ])->id
        ]);

        $office2->images()->update([
            'featured_image_id' => $office2->images()->create([
                'path' => 'storage/1.jpg'
            ])->id
        ]);

        $office3->images()->update([
            'featured_image_id' => $office3->images()->create([
                'path' => 'storage/1.jpg'
            ])->id
        ]);

        Reservation::factory()->for($user)->for($office3)->create();
        // dd($office->id);
    }
}
