<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = [
            ['name' => 'Andaman and Nicobar Islands',       'gst_code' => '35'],
            ['name' => 'Andhra Pradesh',                    'gst_code' => '37'],
            ['name' => 'Arunachal Pradesh',                 'gst_code' => '12'],
            ['name' => 'Assam',                             'gst_code' => '18'],
            ['name' => 'Bihar',                             'gst_code' => '10'],
            ['name' => 'Chandigarh',                        'gst_code' => '04'],
            ['name' => 'Chhattisgarh',                      'gst_code' => '22'],
            ['name' => 'Dadra and Nagar Haveli and Daman and Diu', 'gst_code' => '26'],
            ['name' => 'Delhi',                             'gst_code' => '07'],
            ['name' => 'Goa',                               'gst_code' => '30'],
            ['name' => 'Gujarat',                           'gst_code' => '24'],
            ['name' => 'Haryana',                           'gst_code' => '06'],
            ['name' => 'Himachal Pradesh',                  'gst_code' => '02'],
            ['name' => 'Jammu and Kashmir',                 'gst_code' => '01'],
            ['name' => 'Jharkhand',                         'gst_code' => '20'],
            ['name' => 'Karnataka',                         'gst_code' => '29'],
            ['name' => 'Kerala',                            'gst_code' => '32'],
            ['name' => 'Ladakh',                            'gst_code' => '38'],
            ['name' => 'Lakshadweep',                       'gst_code' => '31'],
            ['name' => 'Madhya Pradesh',                    'gst_code' => '23'],
            ['name' => 'Maharashtra',                       'gst_code' => '27'],
            ['name' => 'Manipur',                           'gst_code' => '14'],
            ['name' => 'Meghalaya',                         'gst_code' => '17'],
            ['name' => 'Mizoram',                           'gst_code' => '15'],
            ['name' => 'Nagaland',                          'gst_code' => '13'],
            ['name' => 'Odisha',                            'gst_code' => '21'],
            ['name' => 'Puducherry',                        'gst_code' => '34'],
            ['name' => 'Punjab',                            'gst_code' => '03'],
            ['name' => 'Rajasthan',                         'gst_code' => '08'],
            ['name' => 'Sikkim',                            'gst_code' => '11'],
            ['name' => 'Tamil Nadu',                        'gst_code' => '33'],
            ['name' => 'Telangana',                         'gst_code' => '36'],
            ['name' => 'Tripura',                           'gst_code' => '16'],
            ['name' => 'Uttar Pradesh',                     'gst_code' => '09'],
            ['name' => 'Uttarakhand',                       'gst_code' => '05'],
            ['name' => 'West Bengal',                       'gst_code' => '19'],
        ];

        foreach ($states as $state) {
            State::firstOrCreate($state);
        }
    }
}
