<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeiraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Feira::updateOrCreate(
            ['id' => 1],
            [
                'nome' => 'Feira de Saquarema 2025',
                'evento_id_api' => '771',
                'user_id_api' => '38881',
                'data_inicio' => '2025-09-21 00:00:00',
                'data_fim' => '2025-11-20 23:59:59',
                'status' => \App\Enums\FeiraStatus::PLANEJADA,
            ]
        );
    }
}
