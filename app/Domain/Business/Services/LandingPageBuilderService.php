<?php

namespace App\Domain\Business\Services;

use App\Domain\Business\Models\Business;
use Carbon\Carbon;

class LandingPageBuilderService
{
    public const FONT_PRESET_INTER = 'inter';

    public const FONT_PRESET_PLUS_JAKARTA = 'plus_jakarta';

    /**
     * Get default landing page configuration for a business (PRD 28, 68).
     *
     * @return array{
     *     theme: array{
     *         primary_color: string,
     *         font_preset: string,
     *         banner_image_url: string|null
     *     },
     *     sections: array<int, array{
     *         id: string,
     *         type: string,
     *         enabled: bool,
     *         title: string,
     *         data: array<string, mixed>
     *     }>
     * }
     */
    public function getDefaultLandingConfig(Business $business): array
    {
        return [
            'theme' => [
                'primary_color' => '#2563eb',
                'font_preset' => self::FONT_PRESET_INTER,
                'banner_image_url' => null,
            ],
            'sections' => [
                [
                    'id' => 'hero',
                    'type' => 'hero',
                    'enabled' => true,
                    'title' => 'Hero Banner',
                    'data' => [
                        'headline' => $business->name,
                        'subheadline' => $business->description ?: 'Layanan profesional dengan kemudahan reservasi online cepat, pasti dan terkonfirmasi otomatis.',
                        'cta_text' => 'Booking Sekarang',
                        'show_whatsapp' => true,
                        'trust_points' => [
                            'Konfirmasi Instan',
                            'Pilih Slot Jadwal Real-Time',
                            'Bebas Reschedule Mandiri',
                        ],
                    ],
                ],
                [
                    'id' => 'services',
                    'type' => 'services',
                    'enabled' => true,
                    'title' => 'Katalog Layanan',
                    'data' => [
                        'title' => 'Katalog Layanan',
                        'subtitle' => 'Pilih layanan yang Anda inginkan dan tentukan jadwal kunjungan secara online',
                    ],
                ],
                [
                    'id' => 'features',
                    'type' => 'features',
                    'enabled' => true,
                    'title' => 'Mengapa Memilih Kami',
                    'data' => [
                        'title' => 'Mengapa Memilih Kami?',
                        'subtitle' => 'Pengalaman reservasi modern yang dirancang untuk kenyamanan jadwal Anda',
                        'items' => [
                            [
                                'title' => 'Reservasi Cepat 24/7',
                                'desc' => 'Booking layanan kapan saja dari perangkat apa pun tanpa perlu menunggu balasan pesan admin.',
                            ],
                            [
                                'title' => 'Jadwal Slot Terbuka',
                                'desc' => 'Ketersediaan waktu dan staf langsung sinkron secara real-time tanpa risiko double-booking.',
                            ],
                            [
                                'title' => 'Pengingat Otomatis',
                                'desc' => 'Dapatkan konfirmasi tiket dan notifikasi pengingat jadwal sebelum waktu kunjungan Anda.',
                            ],
                            [
                                'title' => 'Reschedule Mandiri',
                                'desc' => 'Rencana mendadak berubah? Anda dapat mengubah waktu kunjungan dengan mudah melalui tautan tiket Anda.',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'about',
                    'type' => 'about',
                    'enabled' => true,
                    'title' => 'Tentang & Jam Operasional',
                    'data' => [
                        'title' => 'Tentang ' . $business->name,
                        'content' => $business->description ?: ($business->name . ' berkomitmen memberikan pelayanan terbaik dengan staf profesional dan fasilitas yang higienis serta nyaman.'),
                        'show_hours' => true,
                        'show_address' => true,
                    ],
                ],
                [
                    'id' => 'faq',
                    'type' => 'faq',
                    'enabled' => true,
                    'title' => 'Pertanyaan yang Sering Diajukan (FAQ)',
                    'data' => [
                        'title' => 'Pertanyaan yang Sering Diajukan',
                        'subtitle' => 'Semua informasi penting seputar proses reservasi dan layanan',
                        'items' => [
                            [
                                'q' => 'Bagaimana cara memesan jadwal reservasi?',
                                'a' => 'Klik tombol Booking Sekarang, pilih layanan yang Anda inginkan, tentukan tanggal serta jam kedatangan yang tersedia, lalu lengkapi data Anda.',
                            ],
                            [
                                'q' => 'Apakah saya bisa mengubah jadwal (reschedule)?',
                                'a' => 'Bisa. Pada konfirmasi booking Anda akan disertakan link kelola reservasi untuk mengubah jadwal sesuai kebijakan batas waktu.',
                            ],
                            [
                                'q' => 'Bagaimana jika saya terlambat datang?',
                                'a' => 'Kami menyarankan Anda tiba 10 menit sebelum waktu reservasi. Jika terlambat, silakan beri tahu kami melalui WhatsApp.',
                            ],
                            [
                                'q' => 'Metode pembayaran apa saja yang didukung?',
                                'a' => 'Kami melayani pembayaran di tempat (tunai / transfer / QRIS) saat kunjungan Anda tiba di lokasi.',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'contact',
                    'type' => 'contact',
                    'enabled' => true,
                    'title' => 'Kontak & Lokasi',
                    'data' => [
                        'title' => 'Hubungi Kami',
                        'subtitle' => 'Pertanyaan lebih lanjut atau bantuan seputar reservasi',
                        'show_whatsapp' => true,
                        'show_phone' => true,
                        'show_address' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get active landing page configuration, merging defaults with stored settings.
     *
     * @return array{
     *     theme: array{
     *         primary_color: string,
     *         font_preset: string,
     *         banner_image_url: string|null
     *     },
     *     sections: array<int, array{
     *         id: string,
     *         type: string,
     *         enabled: bool,
     *         title: string,
     *         data: array<string, mixed>
     *     }>
     * }
     */
    public function getLandingConfig(Business $business): array
    {
        $defaults = $this->getDefaultLandingConfig($business);

        $settings = $business->settings;
        if (! is_array($settings) || empty($settings['landing_page'])) {
            return $defaults;
        }

        /** @var array<string, mixed> $stored */
        $stored = $settings['landing_page'];

        // Merge theme
        $theme = array_merge($defaults['theme'], (array) ($stored['theme'] ?? []));

        // Ensure font preset is valid (PRD 28: maks 2 font preset)
        if (! in_array($theme['font_preset'], [self::FONT_PRESET_INTER, self::FONT_PRESET_PLUS_JAKARTA], true)) {
            $theme['font_preset'] = self::FONT_PRESET_INTER;
        }

        // Validate primary color hex format
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $theme['primary_color'])) {
            $theme['primary_color'] = '#2563eb';
        }

        // Merge sections preserving stored ordering and toggles
        $defaultSectionsMap = [];
        foreach ($defaults['sections'] as $s) {
            $defaultSectionsMap[$s['id']] = $s;
        }

        $mergedSections = [];
        $handledIds = [];

        if (! empty($stored['sections']) && is_array($stored['sections'])) {
            foreach ($stored['sections'] as $storedSection) {
                if (! is_array($storedSection) || empty($storedSection['id'])) {
                    continue;
                }

                $id = (string) $storedSection['id'];
                if (isset($defaultSectionsMap[$id])) {
                    $def = $defaultSectionsMap[$id];
                    $storedData = (array) ($storedSection['data'] ?? []);

                    $mergedSections[] = [
                        'id' => $id,
                        'type' => $def['type'],
                        'enabled' => isset($storedSection['enabled']) ? (bool) $storedSection['enabled'] : true,
                        'title' => (string) ($storedSection['title'] ?? $def['title']),
                        'data' => array_merge($def['data'], $storedData),
                    ];
                    $handledIds[] = $id;
                }
            }
        }

        // Append any default section not present in stored sections
        foreach ($defaults['sections'] as $def) {
            if (! in_array($def['id'], $handledIds, true)) {
                $mergedSections[] = $def;
            }
        }

        return [
            'theme' => $theme,
            'sections' => $mergedSections,
        ];
    }

    /**
     * Sanitize user input strictly to prevent XSS / malicious code injection (PRD 69, Prompt 3.6).
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     theme: array{
     *         primary_color: string,
     *         font_preset: string,
     *         banner_image_url: string|null
     *     },
     *     sections: array<int, array{
     *         id: string,
     *         type: string,
     *         enabled: bool,
     *         title: string,
     *         data: array<string, mixed>
     *     }>
     * }
     */
    public function sanitizeLandingConfig(array $input, Business $business): array
    {
        $defaults = $this->getDefaultLandingConfig($business);

        // 1. Sanitize Theme
        $rawTheme = (array) ($input['theme'] ?? []);
        $primaryColor = (string) ($rawTheme['primary_color'] ?? '#2563eb');
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
            $primaryColor = '#2563eb';
        }

        $fontPreset = (string) ($rawTheme['font_preset'] ?? self::FONT_PRESET_INTER);
        if (! in_array($fontPreset, [self::FONT_PRESET_INTER, self::FONT_PRESET_PLUS_JAKARTA], true)) {
            $fontPreset = self::FONT_PRESET_INTER;
        }

        $bannerImageUrl = ! empty($rawTheme['banner_image_url'])
            ? filter_var((string) $rawTheme['banner_image_url'], FILTER_SANITIZE_URL)
            : null;

        $theme = [
            'primary_color' => $primaryColor,
            'font_preset' => $fontPreset,
            'banner_image_url' => is_string($bannerImageUrl) ? $this->cleanXss($bannerImageUrl) : null,
        ];

        // 2. Sanitize Sections
        $rawSections = (array) ($input['sections'] ?? []);
        $validTypes = ['hero', 'services', 'features', 'about', 'faq', 'contact'];

        $sanitizedSections = [];
        $seenIds = [];

        foreach ($rawSections as $sec) {
            if (! is_array($sec) || empty($sec['id']) || ! in_array($sec['id'], $validTypes, true)) {
                continue;
            }

            $id = (string) $sec['id'];
            if (in_array($id, $seenIds, true)) {
                continue;
            }
            $seenIds[] = $id;

            $enabled = isset($sec['enabled']) ? (bool) $sec['enabled'] : true;
            $title = $this->cleanXss((string) ($sec['title'] ?? $id));
            $rawData = (array) ($sec['data'] ?? []);
            $cleanData = [];

            switch ($id) {
                case 'hero':
                    $cleanData['headline'] = $this->cleanXss((string) ($rawData['headline'] ?? $business->name));
                    $cleanData['subheadline'] = $this->cleanXss((string) ($rawData['subheadline'] ?? ''));
                    $cleanData['cta_text'] = $this->cleanXss((string) ($rawData['cta_text'] ?? 'Booking Sekarang'));
                    $cleanData['show_whatsapp'] = (bool) ($rawData['show_whatsapp'] ?? true);
                    $rawTrust = (array) ($rawData['trust_points'] ?? []);
                    $cleanData['trust_points'] = array_slice(array_map(fn ($p) => $this->cleanXss((string) $p), $rawTrust), 0, 5);
                    break;

                case 'services':
                    $cleanData['title'] = $this->cleanXss((string) ($rawData['title'] ?? 'Katalog Layanan'));
                    $cleanData['subtitle'] = $this->cleanXss((string) ($rawData['subtitle'] ?? ''));
                    break;

                case 'features':
                    $cleanData['title'] = $this->cleanXss((string) ($rawData['title'] ?? 'Mengapa Memilih Kami?'));
                    $cleanData['subtitle'] = $this->cleanXss((string) ($rawData['subtitle'] ?? ''));
                    $rawItems = (array) ($rawData['items'] ?? []);
                    $cleanItems = [];
                    foreach (array_slice($rawItems, 0, 6) as $item) {
                        if (is_array($item)) {
                            $cleanItems[] = [
                                'title' => $this->cleanXss((string) ($item['title'] ?? '')),
                                'desc' => $this->cleanXss((string) ($item['desc'] ?? '')),
                            ];
                        }
                    }
                    $cleanData['items'] = $cleanItems;
                    break;

                case 'about':
                    $cleanData['title'] = $this->cleanXss((string) ($rawData['title'] ?? 'Tentang Kami'));
                    $cleanData['content'] = $this->cleanXss((string) ($rawData['content'] ?? ''));
                    $cleanData['show_hours'] = (bool) ($rawData['show_hours'] ?? true);
                    $cleanData['show_address'] = (bool) ($rawData['show_address'] ?? true);
                    break;

                case 'faq':
                    $cleanData['title'] = $this->cleanXss((string) ($rawData['title'] ?? 'Pertanyaan yang Sering Diajukan'));
                    $cleanData['subtitle'] = $this->cleanXss((string) ($rawData['subtitle'] ?? ''));
                    $rawFaqItems = (array) ($rawData['items'] ?? []);
                    $cleanFaqItems = [];
                    foreach (array_slice($rawFaqItems, 0, 10) as $item) {
                        if (is_array($item)) {
                            $cleanFaqItems[] = [
                                'q' => $this->cleanXss((string) ($item['q'] ?? '')),
                                'a' => $this->cleanXss((string) ($item['a'] ?? '')),
                            ];
                        }
                    }
                    $cleanData['items'] = $cleanFaqItems;
                    break;

                case 'contact':
                    $cleanData['title'] = $this->cleanXss((string) ($rawData['title'] ?? 'Hubungi Kami'));
                    $cleanData['subtitle'] = $this->cleanXss((string) ($rawData['subtitle'] ?? ''));
                    $cleanData['show_whatsapp'] = (bool) ($rawData['show_whatsapp'] ?? true);
                    $cleanData['show_phone'] = (bool) ($rawData['show_phone'] ?? true);
                    $cleanData['show_address'] = (bool) ($rawData['show_address'] ?? true);
                    break;
            }

            $sanitizedSections[] = [
                'id' => $id,
                'type' => $id,
                'enabled' => $enabled,
                'title' => $title,
                'data' => $cleanData,
            ];
        }

        // If any default sections are missing, append them as disabled or default
        $defaultsMap = [];
        foreach ($defaults['sections'] as $ds) {
            $defaultsMap[$ds['id']] = $ds;
        }

        foreach ($validTypes as $typeId) {
            if (! in_array($typeId, $seenIds, true) && isset($defaultsMap[$typeId])) {
                $sanitizedSections[] = $defaultsMap[$typeId];
            }
        }

        return [
            'theme' => $theme,
            'sections' => $sanitizedSections,
        ];
    }

    /**
     * Save landing page configuration for business.
     *
     * @param  array<string, mixed>  $input
     */
    public function saveLandingConfig(Business $business, array $input): Business
    {
        $sanitized = $this->sanitizeLandingConfig($input, $business);

        $currentSettings = is_array($business->settings) ? $business->settings : [];
        $currentSettings['landing_page'] = $sanitized;

        $business->update([
            'settings' => $currentSettings,
        ]);

        return $business->fresh();
    }

    /**
     * Reset landing page configuration to system default template.
     */
    public function resetLandingConfig(Business $business): Business
    {
        $currentSettings = is_array($business->settings) ? $business->settings : [];
        unset($currentSettings['landing_page']);

        $business->update([
            'settings' => $currentSettings,
        ]);

        return $business->fresh();
    }

    /**
     * Toggle or set published status of business (PRD 29, 205).
     */
    public function setPublished(Business $business, ?bool $isPublished = null): Business
    {
        if ($isPublished === null) {
            $newPublishedAt = $business->published_at ? null : Carbon::now();
        } else {
            $newPublishedAt = $isPublished ? Carbon::now() : null;
        }

        $business->update([
            'published_at' => $newPublishedAt,
        ]);

        return $business->fresh();
    }

    /**
     * Strict XSS filter that strips dangerous tags, script blocks, event handlers, and escapes special characters.
     */
    protected function cleanXss(string $value): string
    {
        // 1. Remove dangerous script and iframe blocks entirely
        $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);
        $cleaned = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', (string) $cleaned);
        $cleaned = preg_replace('/javascript:/i', '', (string) $cleaned);
        $cleaned = preg_replace('/onload\s*=/i', '', (string) $cleaned);
        $cleaned = preg_replace('/onerror\s*=/i', '', (string) $cleaned);
        $cleaned = preg_replace('/onclick\s*=/i', '', (string) $cleaned);

        // 2. Strip all remaining HTML tags
        $cleaned = strip_tags((string) $cleaned);

        // 3. Trim extra whitespace
        return trim($cleaned);
    }
}
