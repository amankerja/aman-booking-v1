<?php

namespace App\Domain\Business\Templates;

class BusinessTemplateRegistry
{
    /**
     * Get all available business templates.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'barbershop' => self::barbershop(),
            'salon' => self::salon(),
            'spa' => self::spa(),
            'sports_court' => self::sportsCourt(),
            'rental' => self::rental(),
            'custom' => self::custom(),
        ];
    }

    /**
     * Get a specific template by its identifier.
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $id): ?array
    {
        $all = self::all();

        return $all[$id] ?? null;
    }

    /**
     * Check if a template ID exists.
     */
    public static function exists(string $id): bool
    {
        return array_key_exists($id, self::all());
    }

    /**
     * 1. Barbershop Template (PRD 169)
     *
     * @return array<string, mixed>
     */
    public static function barbershop(): array
    {
        return [
            'id' => 'barbershop',
            'name' => 'Barbershop & Grooming Pria',
            'category_name' => 'Layanan Pangkas & Perawatan Pria',
            'description' => 'Preset untuk usaha barbershop, potong rambut pria, dan grooming profesional.',
            'icon' => 'Scissors',
            'version' => '1.0.0',
            'origin' => 'SYSTEM_TEMPLATE',
            'customization_questions' => [
                [
                    'key' => 'barber_count',
                    'label' => 'Berapa jumlah barber (kapster) aktif?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'chair_count',
                    'label' => 'Berapa jumlah kursi barber?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'customer_picks_staff',
                    'label' => 'Apakah pelanggan boleh memilih barber tertentu?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'open_time',
                    'label' => 'Jam Buka',
                    'type' => 'time',
                    'default' => '10:00',
                ],
                [
                    'key' => 'close_time',
                    'label' => 'Jam Tutup',
                    'type' => 'time',
                    'default' => '21:00',
                ],
            ],
            'services' => [
                [
                    'name' => 'Potong Rambut Reguler',
                    'slug' => 'potong-rambut-reguler',
                    'description' => 'Potong rambut rapi sesuai gaya pilihan + keramas ringan + styling pomade.',
                    'price_idr' => 50000,
                    'duration_minutes' => 45,
                    'buffer_after' => 5,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Hair Wash & Massage',
                    'slug' => 'hair-wash-massage',
                    'description' => 'Pencucian rambut menyeluruh disertai pijat relaksasi kepala dan leher.',
                    'price_idr' => 30000,
                    'duration_minutes' => 30,
                    'buffer_after' => 5,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Shaving & Beard Trim',
                    'slug' => 'shaving-beard-trim',
                    'description' => 'Cukur jenggot dan kumis presisi dengan handuk hangat dan aftershave soothing.',
                    'price_idr' => 25000,
                    'duration_minutes' => 25,
                    'buffer_after' => 5,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Paket Komplit Barber (Cut + Wash + Shave)',
                    'slug' => 'paket-komplit-barber',
                    'description' => 'Layanan perawatan lengkap: potong rambut, keramas pijat, dan shaving kumis/jenggot.',
                    'price_idr' => 95000,
                    'duration_minutes' => 75,
                    'buffer_after' => 10,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
            ],
            'resource_types' => [
                ['code' => 'STAFF', 'name' => 'Barber', 'icon' => 'User', 'is_staff' => true],
                ['code' => 'CHAIR', 'name' => 'Kursi Barber', 'icon' => 'Armchair', 'is_space' => true],
            ],
            'default_resources' => [
                ['name' => 'Barber Ahmad', 'type_code' => 'STAFF', 'capacity' => 1],
                ['name' => 'Barber Dani', 'type_code' => 'STAFF', 'capacity' => 1],
                ['name' => 'Kursi 01', 'type_code' => 'CHAIR', 'capacity' => 1],
                ['name' => 'Kursi 02', 'type_code' => 'CHAIR', 'capacity' => 1],
            ],
            'hours' => [
                'open_time' => '10:00:00',
                'close_time' => '21:00:00',
                'days' => [0, 1, 2, 3, 4, 5, 6], // Buka setiap hari
                'breaks' => [
                    ['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
                    ['name' => 'Istirahat Sore', 'start_time' => '18:00:00', 'end_time' => '18:30:00'],
                ],
            ],
            'workflow' => [
                'name' => 'Alur Barbershop Cepat (PRD 49)',
                'steps' => ['PENDING', 'CONFIRMED', 'COMPLETED'],
                'auto_confirm' => true,
            ],
            'form' => [
                ['name' => 'customer_name', 'label' => 'Nama Lengkap', 'type' => 'text', 'required' => true],
                ['name' => 'customer_phone', 'label' => 'Nomor WhatsApp', 'type' => 'tel', 'required' => true],
                ['name' => 'notes', 'label' => 'Permintaan Khusus (Model Rambut)', 'type' => 'textarea', 'required' => false],
            ],
            'landing' => [
                'hero_title' => 'Potongan Presisi, Tampil Lebih Percaya Diri',
                'hero_tagline' => 'Pesan antrean potong rambut online tanpa perlu menunggu berjam-jam di tempat.',
                'about_text' => 'Kami adalah barbershop profesional dengan kapster berpengalaman yang siap memberikan potongan rambut terbaik sesuai karakter Anda.',
                'features' => [
                    'Booking Online Tanpa Antre',
                    'Kapster Berpengalaman & Ramah',
                    'Alat Bersih & Steril Setiap Sesi',
                    'Ruangan Ber-AC & Nyaman',
                ],
            ],
        ];
    }

    /**
     * 2. Salon Wanita Template
     *
     * @return array<string, mixed>
     */
    public static function salon(): array
    {
        return [
            'id' => 'salon',
            'name' => 'Salon Kecantikan & Rambut',
            'category_name' => 'Hair & Beauty Treatments',
            'description' => 'Preset untuk salon kecantikan wanita, perawatan rambut, creambath, dan pewarnaan.',
            'icon' => 'Sparkles',
            'version' => '1.0.0',
            'origin' => 'SYSTEM_TEMPLATE',
            'customization_questions' => [
                [
                    'key' => 'stylist_count',
                    'label' => 'Berapa jumlah penata rambut (stylist)?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'chair_count',
                    'label' => 'Berapa kursi styling yang tersedia?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'customer_picks_staff',
                    'label' => 'Bolehkah pelanggan memilih penata rambut favorit?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'open_time',
                    'label' => 'Jam Buka',
                    'type' => 'time',
                    'default' => '09:00',
                ],
                [
                    'key' => 'close_time',
                    'label' => 'Jam Tutup',
                    'type' => 'time',
                    'default' => '19:00',
                ],
            ],
            'services' => [
                [
                    'name' => 'Cuci Blow & Styling Natural',
                    'slug' => 'cuci-blow-styling',
                    'description' => 'Pencucian rambut menggunakan shampoo premium + blow dry halus dan styling tahan lama.',
                    'price_idr' => 65000,
                    'duration_minutes' => 45,
                    'buffer_after' => 10,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Creambath Tradisional & Totok Wajah',
                    'slug' => 'creambath-tradisional',
                    'description' => 'Perawatan akar rambut dengan krim nutrisi alami disertai pijat leher, pundak, dan totok wajah.',
                    'price_idr' => 95000,
                    'duration_minutes' => 60,
                    'buffer_after' => 10,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Hair Coloring & Toning Eksklusif',
                    'slug' => 'hair-coloring-toning',
                    'description' => 'Pewarnaan rambut profesional berkualitas tinggi dengan konsultasi warna sesuai undertone kulit.',
                    'price_idr' => 250000,
                    'duration_minutes' => 120,
                    'buffer_after' => 15,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Manicure & Pedicure Spa',
                    'slug' => 'manicure-pedicure-spa',
                    'description' => 'Perawatan kuku tangan dan kaki, pengangkatan kutikula, scrubbing halus, dan poles kuteks.',
                    'price_idr' => 120000,
                    'duration_minutes' => 60,
                    'buffer_after' => 10,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'STAFF',
                ],
            ],
            'resource_types' => [
                ['code' => 'STAFF', 'name' => 'Stylist / Beautician', 'icon' => 'User', 'is_staff' => true],
                ['code' => 'CHAIR', 'name' => 'Kursi Salon', 'icon' => 'Armchair', 'is_space' => true],
            ],
            'default_resources' => [
                ['name' => 'Stylist Rina', 'type_code' => 'STAFF', 'capacity' => 1],
                ['name' => 'Stylist Maya', 'type_code' => 'STAFF', 'capacity' => 1],
                ['name' => 'Kursi Salon 1', 'type_code' => 'CHAIR', 'capacity' => 1],
                ['name' => 'Kursi Salon 2', 'type_code' => 'CHAIR', 'capacity' => 1],
            ],
            'hours' => [
                'open_time' => '09:00:00',
                'close_time' => '19:00:00',
                'days' => [1, 2, 3, 4, 5, 6], // Senin - Sabtu (Minggu libur default)
                'breaks' => [
                    ['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
                ],
            ],
            'workflow' => [
                'name' => 'Alur Salon Kecantikan',
                'steps' => ['PENDING', 'CONFIRMED', 'COMPLETED'],
                'auto_confirm' => true,
            ],
            'form' => [
                ['name' => 'customer_name', 'label' => 'Nama Pelanggan', 'type' => 'text', 'required' => true],
                ['name' => 'customer_phone', 'label' => 'WhatsApp Aktif', 'type' => 'tel', 'required' => true],
                ['name' => 'notes', 'label' => 'Catatan Khusus / Permintaan Treatment', 'type' => 'textarea', 'required' => false],
            ],
            'landing' => [
                'hero_title' => 'Rambut Sehat Berkilau, Pancarkan Pesona Anda',
                'hero_tagline' => 'Perawatan rambut dan kecantikan premium dengan penata rambut berpengalaman.',
                'about_text' => 'Kami menghadirkan sentuhan perawatan kecantikan terbaik menggunakan produk higienis dan bersertifikat demi kenyamanan Anda.',
                'features' => [
                    'Produk Berkualitas Internasional',
                    'Konsultasi Gaya Rambut Gratis',
                    'Stylist Bersertifikat',
                    'Tempat Privat & Nyaman untuk Wanita',
                ],
            ],
        ];
    }

    /**
     * 3. Spa & Sauna Template
     *
     * @return array<string, mixed>
     */
    public static function spa(): array
    {
        return [
            'id' => 'spa',
            'name' => 'Spa, Sauna & Terapi Relaksasi',
            'category_name' => 'Relaxation & Wellness Therapy',
            'description' => 'Preset untuk tempat spa, refleksi, pijat tradisional, sauna, dan wellness center.',
            'icon' => 'HeartHandshake',
            'version' => '1.0.0',
            'origin' => 'SYSTEM_TEMPLATE',
            'customization_questions' => [
                [
                    'key' => 'therapist_count',
                    'label' => 'Berapa jumlah terapis yang bertugas?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'room_count',
                    'label' => 'Berapa ruang terapi (private room) yang tersedia?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'customer_picks_staff',
                    'label' => 'Apakah pelanggan dapat memilih terapis?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'open_time',
                    'label' => 'Jam Buka',
                    'type' => 'time',
                    'default' => '10:00',
                ],
                [
                    'key' => 'close_time',
                    'label' => 'Jam Tutup',
                    'type' => 'time',
                    'default' => '21:00',
                ],
            ],
            'services' => [
                [
                    'name' => 'Traditional Body Massage (60 Menit)',
                    'slug' => 'traditional-body-massage-60',
                    'description' => 'Pijat tradisional seluruh tubuh untuk melancarkan sirkulasi darah dan meredakan ketegangan otot.',
                    'price_idr' => 150000,
                    'duration_minutes' => 60,
                    'buffer_after' => 15,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Aromatherapy Full Body Spa (90 Menit)',
                    'slug' => 'aromatherapy-spa-90',
                    'description' => 'Perpaduan relaksasi massage dengan minyak esensial aromaterapi alami dan scrub lulur badan.',
                    'price_idr' => 220000,
                    'duration_minutes' => 90,
                    'buffer_after' => 15,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Reflexology & Foot Acupressure (45 Menit)',
                    'slug' => 'reflexology-foot-acupressure-45',
                    'description' => 'Titik tekan refleksi telapak kaki untuk memulihkan kebugaran tubuh secara instan.',
                    'price_idr' => 85000,
                    'duration_minutes' => 45,
                    'buffer_after' => 10,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'STAFF',
                ],
                [
                    'name' => 'Sauna & Herbal Bath (45 Menit)',
                    'slug' => 'sauna-herbal-bath-45',
                    'description' => 'Detoksifikasi tubuh dengan uap hangat rempah herbal dan berendam air hangat menenangkan.',
                    'price_idr' => 100000,
                    'duration_minutes' => 45,
                    'buffer_after' => 15,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'ROOM',
                ],
            ],
            'resource_types' => [
                ['code' => 'STAFF', 'name' => 'Terapis', 'icon' => 'User', 'is_staff' => true],
                ['code' => 'ROOM', 'name' => 'Ruang Pijat', 'icon' => 'DoorClosed', 'is_space' => true],
            ],
            'default_resources' => [
                ['name' => 'Terapis Sari', 'type_code' => 'STAFF', 'capacity' => 1],
                ['name' => 'Terapis Dewi', 'type_code' => 'STAFF', 'capacity' => 1],
                ['name' => 'Ruang Teratai (Privat)', 'type_code' => 'ROOM', 'capacity' => 1],
                ['name' => 'Ruang Melati (Privat)', 'type_code' => 'ROOM', 'capacity' => 1],
            ],
            'hours' => [
                'open_time' => '10:00:00',
                'close_time' => '21:00:00',
                'days' => [0, 1, 2, 3, 4, 5, 6],
                'breaks' => [
                    ['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
                    ['name' => 'Istirahat Sore', 'start_time' => '18:00:00', 'end_time' => '18:30:00'],
                ],
            ],
            'workflow' => [
                'name' => 'Alur Spa & Relaksasi',
                'steps' => ['PENDING', 'CONFIRMED', 'COMPLETED'],
                'auto_confirm' => false, // Spa sering perlu review jadwal terapis
            ],
            'form' => [
                ['name' => 'customer_name', 'label' => 'Nama Tamu', 'type' => 'text', 'required' => true],
                ['name' => 'customer_phone', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => true],
                ['name' => 'notes', 'label' => 'Keluhan / Fokus Pijatan Khusus', 'type' => 'textarea', 'required' => false],
            ],
            'landing' => [
                'hero_title' => 'Kembalikan Kebugaran Tubuh & Ketenangan Jiwa',
                'hero_tagline' => 'Rasakan pengalaman pijat relaksasi mendalam dari terapis bersertifikasi dalam suasana privat.',
                'about_text' => 'Dedikasi kami adalah menghadirkan ketenangan sejati melalui terapi tubuh menyeluruh dan aroma herbal nusantara.',
                'features' => [
                    'Terapis Profesional & Berpengalaman',
                    'Minyak Aromaterapi 100% Organik',
                    'Kamar Terapi Privat & Tenang',
                    'Minuman Herbal Hangat Gratis',
                ],
            ],
        ];
    }

    /**
     * 4. Sports Court (Badminton / Futsal) Template
     *
     * @return array<string, mixed>
     */
    public static function sportsCourt(): array
    {
        return [
            'id' => 'sports_court',
            'name' => 'Lapangan Olahraga (Badminton / Futsal)',
            'category_name' => 'Sewa Lapangan & Fasilitas',
            'description' => 'Preset untuk sewa lapangan bulu tangkis, futsal, basket, atau tenis per jam.',
            'icon' => 'Trophy',
            'version' => '1.0.0',
            'origin' => 'SYSTEM_TEMPLATE',
            'customization_questions' => [
                [
                    'key' => 'court_count',
                    'label' => 'Berapa jumlah lapangan yang disewakan?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 10,
                ],
                [
                    'key' => 'customer_picks_court',
                    'label' => 'Apakah pemesan dapat memilih nomor lapangan?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'open_time',
                    'label' => 'Jam Operasional Buka',
                    'type' => 'time',
                    'default' => '07:00',
                ],
                [
                    'key' => 'close_time',
                    'label' => 'Jam Operasional Tutup',
                    'type' => 'time',
                    'default' => '23:00',
                ],
            ],
            'services' => [
                [
                    'name' => 'Sewa Lapangan Badminton (1 Jam)',
                    'slug' => 'sewa-lapangan-badminton-1-jam',
                    'description' => 'Sewa lapangan bulutangkis karpet vinyl standar PBSI dengan pencahayaan LED terang.',
                    'price_idr' => 60000,
                    'duration_minutes' => 60,
                    'buffer_after' => 0,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'COURT',
                ],
                [
                    'name' => 'Sewa Lapangan Badminton (2 Jam)',
                    'slug' => 'sewa-lapangan-badminton-2-jam',
                    'description' => 'Paket main 2 jam lebih hemat untuk sparing atau latihan ganda bersama komunitas.',
                    'price_idr' => 115000,
                    'duration_minutes' => 120,
                    'buffer_after' => 0,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'COURT',
                ],
                [
                    'name' => 'Sewa Lapangan Futsal (1 Jam)',
                    'slug' => 'sewa-lapangan-futsal-1-jam',
                    'description' => 'Lapangan rumput sintetis interlock premium dengan jaring pengaman lengkap.',
                    'price_idr' => 150000,
                    'duration_minutes' => 60,
                    'buffer_after' => 0,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'COURT',
                ],
            ],
            'resource_types' => [
                ['code' => 'COURT', 'name' => 'Lapangan Olahraga', 'icon' => 'Activity', 'is_space' => true],
            ],
            'default_resources' => [
                ['name' => 'Lapangan 1 (Karpet Vinyl)', 'type_code' => 'COURT', 'capacity' => 1],
                ['name' => 'Lapangan 2 (Karpet Vinyl)', 'type_code' => 'COURT', 'capacity' => 1],
            ],
            'hours' => [
                'open_time' => '07:00:00',
                'close_time' => '23:00:00',
                'days' => [0, 1, 2, 3, 4, 5, 6],
                'breaks' => [],
            ],
            'workflow' => [
                'name' => 'Alur Booking Lapangan',
                'steps' => ['PENDING', 'CONFIRMED', 'COMPLETED'],
                'auto_confirm' => true,
            ],
            'form' => [
                ['name' => 'customer_name', 'label' => 'Nama Pemesan / Tim', 'type' => 'text', 'required' => true],
                ['name' => 'customer_phone', 'label' => 'Nomor WhatsApp PIC', 'type' => 'tel', 'required' => true],
                ['name' => 'notes', 'label' => 'Catatan Tambahan (misal: perlu bola/raket sewa)', 'type' => 'textarea', 'required' => false],
            ],
            'landing' => [
                'hero_title' => 'Main Nyaman, Lapangan Standar Pertandingan',
                'hero_tagline' => 'Cek jadwal kosong secara live dan amankan slot lapangan favoritmu dalam hitungan detik.',
                'about_text' => 'Gedung olahraga modern dengan karpet berkualitas, sirkulasi udara baik, kantin, dan area parkir luas.',
                'features' => [
                    'Jadwal Real-Time Bebas Bentrok',
                    'Karpet Lapangan Standar Nasional',
                    'Pencahayaan LED Terang Anti-Silau',
                    'Kamar Mandi Bersih & Musholla',
                ],
            ],
        ];
    }

    /**
     * 5. Rental Kendaraan & Transportasi Template
     *
     * @return array<string, mixed>
     */
    public static function rental(): array
    {
        return [
            'id' => 'rental',
            'name' => 'Rental Mobil, Motor & Unit',
            'category_name' => 'Sewa Unit & Transportasi',
            'description' => 'Preset untuk usaha rental mobil harian, sewa motor matic, mobil pengantin, atau studio.',
            'icon' => 'Car',
            'version' => '1.0.0',
            'origin' => 'SYSTEM_TEMPLATE',
            'customization_questions' => [
                [
                    'key' => 'vehicle_count',
                    'label' => 'Berapa unit kendaraan utama yang tersedia?',
                    'type' => 'number',
                    'default' => 2,
                    'min' => 1,
                    'max' => 15,
                ],
                [
                    'key' => 'customer_picks_unit',
                    'label' => 'Apakah penyewa dapat memilih unit spesifik?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'open_time',
                    'label' => 'Jam Buka Pelayanan',
                    'type' => 'time',
                    'default' => '06:00',
                ],
                [
                    'key' => 'close_time',
                    'label' => 'Jam Tutup Pelayanan',
                    'type' => 'time',
                    'default' => '22:00',
                ],
            ],
            'services' => [
                [
                    'name' => 'Sewa Mobil Harian (24 Jam) Lepas Kunci',
                    'slug' => 'sewa-mobil-harian-lepas-kunci',
                    'description' => 'Sewa mobil MPV 7-seater untuk keluarga atau bisnis, transmisi matic/manual, kondisi prima.',
                    'price_idr' => 350000,
                    'duration_minutes' => 1440, // 24 jam
                    'buffer_after' => 60,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'VEHICLE',
                ],
                [
                    'name' => 'Sewa Mobil 12 Jam + Driver Profesional',
                    'slug' => 'sewa-mobil-12-jam-driver',
                    'description' => 'Paket sewa mobil termasuk jasa driver ramah dan berpengalaman keliling area dalam kota.',
                    'price_idr' => 450000,
                    'duration_minutes' => 720, // 12 jam
                    'buffer_after' => 60,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'VEHICLE',
                ],
                [
                    'name' => 'Sewa Motor Matic Harian (24 Jam)',
                    'slug' => 'sewa-motor-matic-harian',
                    'description' => 'Sewa motor matic lincah dan hemat bensin sudah termasuk 2 helm SNI dan jas hujan.',
                    'price_idr' => 100000,
                    'duration_minutes' => 1440,
                    'buffer_after' => 30,
                    'capacity' => 1,
                    'is_featured' => false,
                    'resource_type' => 'VEHICLE',
                ],
            ],
            'resource_types' => [
                ['code' => 'VEHICLE', 'name' => 'Armada Kendaraan', 'icon' => 'Car', 'is_equipment' => true],
            ],
            'default_resources' => [
                ['name' => 'Avanza G Matic (B 1234 ABC)', 'type_code' => 'VEHICLE', 'capacity' => 1],
                ['name' => 'Innova Reborn Diesel (B 5678 XYZ)', 'type_code' => 'VEHICLE', 'capacity' => 1],
            ],
            'hours' => [
                'open_time' => '06:00:00',
                'close_time' => '22:00:00',
                'days' => [0, 1, 2, 3, 4, 5, 6],
                'breaks' => [],
            ],
            'workflow' => [
                'name' => 'Alur Verifikasi Rental',
                'steps' => ['PENDING', 'CONFIRMED', 'COMPLETED'],
                'auto_confirm' => false, // Verifikasi KTP/SIM diperlukan
            ],
            'form' => [
                ['name' => 'customer_name', 'label' => 'Nama Lengkap Penyewa (sesuai KTP)', 'type' => 'text', 'required' => true],
                ['name' => 'customer_phone', 'label' => 'Nomor WhatsApp', 'type' => 'tel', 'required' => true],
                ['name' => 'notes', 'label' => 'Tujuan Perjalanan / Lokasi Antar Unit', 'type' => 'textarea', 'required' => false],
            ],
            'landing' => [
                'hero_title' => 'Sewa Kendaraan Nyaman, Bebas Eksplor Kapan Saja',
                'hero_tagline' => 'Unit terawat, armada bersih, dan proses verifikasi cepat tanpa ribet.',
                'about_text' => 'Layanan rental terpercaya dengan armada terbaru yang selalu rutin diservis berkala demi keselamatan perjalanan Anda.',
                'features' => [
                    'Armada Bersih, Wangi & Diservis Rutin',
                    'Bisa Antar-Jemput Bandara / Stasiun',
                    'Asuransi & Pelayanan Darurat 24 Jam',
                    'Harga Transparan Tanpa Biaya Tersembunyi',
                ],
            ],
        ];
    }

    /**
     * 6. Custom Business Template (PRD 192)
     *
     * @return array<string, mixed>
     */
    public static function custom(): array
    {
        return [
            'id' => 'custom',
            'name' => 'Custom Business (Fleksibel)',
            'category_name' => 'Layanan Bisnis Umum',
            'description' => 'Konfigurasi fleksibel untuk segala jenis bisnis jasa, konsultasi, studio, atau privat.',
            'icon' => 'Sliders',
            'version' => '1.0.0',
            'origin' => 'SYSTEM_TEMPLATE',
            'customization_questions' => [
                [
                    'key' => 'what_is_booked',
                    'label' => 'A. Apa yang dibooking pelanggan? (PRD 192)',
                    'type' => 'select',
                    'options' => [
                        'service' => 'Layanan / Jasa Umum',
                        'staff' => 'Staf / Praktisi / Mentor',
                        'room' => 'Ruangan / Kamar',
                        'court' => 'Lapangan / Venue',
                        'vehicle' => 'Kendaraan / Alat',
                    ],
                    'default' => 'service',
                ],
                [
                    'key' => 'customer_picks_resource',
                    'label' => 'B. Apakah customer memilih staf atau resource spesifik?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'has_duration',
                    'label' => 'C. Apakah layanan memiliki durasi waktu tetap?',
                    'type' => 'boolean',
                    'default' => true,
                ],
                [
                    'key' => 'service_name',
                    'label' => 'Nama Layanan Utama',
                    'type' => 'text',
                    'default' => 'Sesi Konsultasi & Layanan Utama',
                ],
                [
                    'key' => 'price_idr',
                    'label' => 'Tarif Layanan (Rp)',
                    'type' => 'number',
                    'default' => 100000,
                ],
                [
                    'key' => 'duration_minutes',
                    'label' => 'Durasi Layanan (Menit)',
                    'type' => 'number',
                    'default' => 60,
                ],
                [
                    'key' => 'requires_approval',
                    'label' => 'D. Apakah reservasi memerlukan persetujuan manual (approval)?',
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
            'services' => [
                [
                    'name' => 'Sesi Layanan & Konsultasi',
                    'slug' => 'sesi-layanan-konsultasi',
                    'description' => 'Layanan profesional dengan jadwal terjadwal rapi dan reservasi mudah.',
                    'price_idr' => 100000,
                    'duration_minutes' => 60,
                    'buffer_after' => 15,
                    'capacity' => 1,
                    'is_featured' => true,
                    'resource_type' => 'STAFF',
                ],
            ],
            'resource_types' => [
                ['code' => 'STAFF', 'name' => 'Petugas / Staf', 'icon' => 'User', 'is_staff' => true],
            ],
            'default_resources' => [
                ['name' => 'Staf Pelaksana', 'type_code' => 'STAFF', 'capacity' => 1],
            ],
            'hours' => [
                'open_time' => '09:00:00',
                'close_time' => '17:00:00',
                'days' => [1, 2, 3, 4, 5], // Senin - Jumat
                'breaks' => [
                    ['name' => 'Istirahat Siang', 'start_time' => '12:00:00', 'end_time' => '13:00:00'],
                ],
            ],
            'workflow' => [
                'name' => 'Alur Bisnis Fleksibel',
                'steps' => ['PENDING', 'CONFIRMED', 'COMPLETED'],
                'auto_confirm' => true,
            ],
            'form' => [
                ['name' => 'customer_name', 'label' => 'Nama Pemesan', 'type' => 'text', 'required' => true],
                ['name' => 'customer_phone', 'label' => 'Nomor WhatsApp', 'type' => 'tel', 'required' => true],
                ['name' => 'customer_email', 'label' => 'Alamat Email', 'type' => 'email', 'required' => false],
                ['name' => 'notes', 'label' => 'Catatan Tambahan', 'type' => 'textarea', 'required' => false],
            ],
            'landing' => [
                'hero_title' => 'Layanan Berkualitas, Reservasi Praktis & Tepat Waktu',
                'hero_tagline' => 'Jadwalkan janji temu Anda secara online tanpa perlu konfirmasi manual yang lama.',
                'about_text' => 'Kami mengutamakan kepuasan pelanggan dengan pelayanan ramah, tepat waktu, dan standar kerja tinggi.',
                'features' => [
                    'Jadwal Otomatis & Terorganisir',
                    'Pelayanan Profesional',
                    'Pengingat Jadwal via WhatsApp',
                    'Privasi Data Terjamin',
                ],
            ],
        ];
    }
}
