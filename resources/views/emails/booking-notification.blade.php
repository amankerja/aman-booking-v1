<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 24px 32px; border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
                            <span style="font-size: 18px; font-weight: 700; color: #0f172a; letter-spacing: -0.02em;">
                                {{ $businessName }}
                            </span>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 32px; font-size: 15px; color: #334155; line-height: 1.7;">
                            <div style="white-space: pre-line; word-break: break-word;">
                                {!! nl2br(e($bodyContent)) !!}
                            </div>

                            @if (!empty($actionUrl))
                                <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid #f1f5f9; text-align: center;">
                                    <a href="{{ $actionUrl }}"
                                       target="_blank"
                                       style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; font-size: 14px; font-weight: 600; border-radius: 8px; letter-spacing: 0.01em;">
                                        {{ $actionText }}
                                    </a>
                                    <p style="margin-top: 12px; font-size: 12px; color: #94a3b8; word-break: break-all;">
                                        Atau buka tautan: <br>
                                        <a href="{{ $actionUrl }}" style="color: #2563eb; text-decoration: underline;">{{ $actionUrl }}</a>
                                    </p>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b;">
                            <p style="margin: 0;">Pesan ini dikirim secara otomatis oleh sistem notifikasi {{ $businessName }}.</p>
                            <p style="margin: 4px 0 0 0; color: #94a3b8;">Didukung oleh AMAN BOOKING — Sistem Reservasi & Manajemen Jadwal</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
