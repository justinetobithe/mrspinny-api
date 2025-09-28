<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('customers')->insert([
            [
                'country'     => 'Philippines',
                'language'    => 'English',
                'name'        => 'Justine Doloiras',
                'email'       => 'justinetobitheee@gmail.com',
                'phone'       => '+63 912 345 6789',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'country'     => 'Romania',
                'language'    => 'English',
                'name'        => 'Gil',
                'email'       => 'Gilgil54@gmail.com',
                'phone'       => '+40 755 967 591',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'country'     => 'Australia',
                'language'    => 'English',
                'name'        => 'Demo Player',
                'email'       => 'Mespinnymangmnet@gmail.com',
                'phone'       => '+61 400 000 000',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
