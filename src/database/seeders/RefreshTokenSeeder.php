<?php

namespace Dx\Payroll\Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Dx\Payroll\Models\RefreshToken;
use Illuminate\Database\Seeder;

class RefreshTokenSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        RefreshToken::create([
            'name' => 'admin sbs',
            'zoho_token' =>  env('REFRESH_TOKEN_TBL_ZOHO_TOKEN' ,'1000.d6cce481be4a0f53d7d42a58c9e96e01.68f4955e10a863d8f6768551c9f04f94'),
            'refresh_token' => env('REFRESH_TOKEN_TBL_REFRESH_TOKEN' ,'1000.dc6dfc0808919a52caf7d57a85af7635.109170f8b4fc9bf4ec89670e4a15790a'),
            'client_id' => env('REFRESH_TOKEN_TBL_CLIENT_ID' ,'1000.LV997840YI28NLJUYEKWEASEC754AE'),
            'client_secret' => env('REFRESH_TOKEN_TBL_CLIENT_SECRET' ,'aa2af218bc4789d1045fb6957a1a61cf2df8a472ac'),
            'grant_type' => 'refresh_token',
            'status' => 1,
        ]);
    }
}
