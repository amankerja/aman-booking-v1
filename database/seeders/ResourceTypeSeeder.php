<?php

namespace Database\Seeders;

use App\Domain\Resource\Models\ResourceType;
use Illuminate\Database\Seeder;

class ResourceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'STAFF',
                'name' => 'Staff / Terapis / Praktisi',
                'icon' => 'User',
                'is_staff' => true,
                'is_space' => false,
                'is_equipment' => false,
            ],
            [
                'code' => 'ROOM',
                'name' => 'Ruangan / Kamar',
                'icon' => 'DoorClosed',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'CHAIR',
                'name' => 'Kursi / Barber Chair',
                'icon' => 'Armchair',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'BED',
                'name' => 'Tempat Tidur / Bed',
                'icon' => 'Bed',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'COURT',
                'name' => 'Lapangan Olahraga',
                'icon' => 'Activity',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'VEHICLE',
                'name' => 'Kendaraan / Armada',
                'icon' => 'Car',
                'is_staff' => false,
                'is_space' => false,
                'is_equipment' => true,
            ],
            [
                'code' => 'BAY',
                'name' => 'Bay / Area Cuci / Servis',
                'icon' => 'Warehouse',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'STATION',
                'name' => 'Station / Pos Kerja',
                'icon' => 'Laptop',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'MACHINE',
                'name' => 'Mesin / Perangkat Khusus',
                'icon' => 'Cpu',
                'is_staff' => false,
                'is_space' => false,
                'is_equipment' => true,
            ],
            [
                'code' => 'TABLE',
                'name' => 'Meja / Spot Reservasi',
                'icon' => 'Grid',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'STUDIO',
                'name' => 'Studio Foto / Musik',
                'icon' => 'Camera',
                'is_staff' => false,
                'is_space' => true,
                'is_equipment' => false,
            ],
            [
                'code' => 'EQUIPMENT',
                'name' => 'Peralatan / Perlengkapan',
                'icon' => 'Wrench',
                'is_staff' => false,
                'is_space' => false,
                'is_equipment' => true,
            ],
            [
                'code' => 'CUSTOM',
                'name' => 'Custom Resource',
                'icon' => 'Box',
                'is_staff' => false,
                'is_space' => false,
                'is_equipment' => false,
            ],
        ];

        foreach ($types as $type) {
            ResourceType::updateOrCreate(
                [
                    'tenant_id' => null,
                    'code' => $type['code'],
                ],
                [
                    'name' => $type['name'],
                    'icon' => $type['icon'],
                    'is_staff' => $type['is_staff'],
                    'is_space' => $type['is_space'],
                    'is_equipment' => $type['is_equipment'],
                    'is_active' => true,
                ]
            );
        }
    }
}
