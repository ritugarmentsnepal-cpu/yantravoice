<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AvatarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $avatars = [
            [
                'heygen_avatar_id' => 'Daisy-inTshirt-20220818',
                'name' => 'Daisy (Casual)',
                'gender' => 'Female',
                'preview_image_url' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=200&h=200&fit=crop',
                'is_custom' => false
            ],
            [
                'heygen_avatar_id' => 'Blake_Professional_Tie',
                'name' => 'Blake (Professional)',
                'gender' => 'Male',
                'preview_image_url' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=200&h=200&fit=crop',
                'is_custom' => false
            ],
            [
                'heygen_avatar_id' => 'Tyler-inSuit-20220818',
                'name' => 'Tyler (Suit)',
                'gender' => 'Male',
                'preview_image_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=200&h=200&fit=crop',
                'is_custom' => false
            ]
        ];

        foreach ($avatars as $avatar) {
            \App\Models\Avatar::updateOrCreate(
                ['heygen_avatar_id' => $avatar['heygen_avatar_id']],
                $avatar
            );
        }
    }
}
