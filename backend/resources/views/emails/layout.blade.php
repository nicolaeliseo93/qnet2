<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>{{ $appName ?? config('app.name') }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f7; color: #1f2933; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; background-color: #f4f4f7; padding: 24px 0; }
        .content { max-width: 520px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e4e7eb; }
        .header { padding: 24px 32px; background-color: #1f2933; color: #ffffff; font-size: 18px; font-weight: 600; }
        .body { padding: 32px; font-size: 15px; line-height: 1.6; }
        .body h1 { font-size: 20px; margin: 0 0 16px; }
        .body p { margin: 0 0 16px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #1f2933; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .muted { color: #7b8794; font-size: 13px; }
        .break { word-break: break-all; }
        .footer { padding: 20px 32px; color: #9aa5b1; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="content">
            <div class="header">{{ $appName ?? config('app.name') }}</div>
            <div class="body">
                @yield('content')
            </div>
            <div class="footer">
                &copy; {{ date('Y') }} {{ $appName ?? config('app.name') }}.
                {{ __('This is an automated message, please do not reply.') }}
            </div>
        </div>
    </div>
</body>
</html>
