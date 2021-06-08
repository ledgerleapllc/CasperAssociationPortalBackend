<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = \Faker\Factory::create();
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            array_push($users, [
                'first_name' => $faker->name(),
                'last_name' => $faker->name(),
                'email' => $faker->unique()->safeEmail,
                'password' => bcrypt('12345678'),
                'last_login_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('users')->insert($users);
    }
}
