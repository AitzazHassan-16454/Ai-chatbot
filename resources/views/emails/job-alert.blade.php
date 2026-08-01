<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
</head>
<body style="margin:0;padding:0;background:#f4f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f9;padding:36px 16px;">
<tr><td align="center">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">

    {{-- Brand Header --}}
    <tr><td style="padding:0 4px 28px 4px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0"><tr>
                        <td style="width:34px;height:34px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:9px;text-align:center;vertical-align:middle;font-size:16px;">
                            <span style="color:#ffffff;">&#9889;</span>
                        </td>
                        <td style="padding-left:10px;vertical-align:middle;">
                            <div style="font-size:16px;font-weight:800;color:#111827;letter-spacing:-0.3px;line-height:1.2;">AI Chatbot</div>
                            <div style="font-size:10.5px;color:#9ca3af;font-weight:600;letter-spacing:0.4px;text-transform:uppercase;">Job Intelligence</div>
                        </td>
                    </tr></table>
                </td>
                <td style="text-align:right;vertical-align:middle;">
                    <span style="display:inline-block;padding:5px 12px;background:#eef2ff;color:#6366f1;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.2px;">
                        {{ now()->format('l, F j, Y') }}
                    </span>
                </td>
            </tr>
        </table>
    </td></tr>

    {{-- Hero Banner --}}
    <tr><td style="padding:0 0 28px 0;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#a855f7 100%);border-radius:20px;overflow:hidden;">
            <tr><td style="padding:40px 32px;text-align:center;">
                <table cellpadding="0" cellspacing="0" style="margin:0 auto 16px auto;"><tr>
                    <td style="width:56px;height:56px;background:rgba(255,255,255,0.15);border-radius:16px;text-align:center;vertical-align:middle;font-size:26px;">&#127919;</td>
                </tr></table>
                <div style="font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;margin-bottom:8px;">
                    {{ $jobs->count() }} New Job{{ $jobs->count() !== 1 ? 's' : '' }} Found
                </div>
                <div style="font-size:14px;color:rgba(255,255,255,0.85);line-height:1.6;max-width:420px;margin:0 auto;">
                    Matching your search for
                    <strong style="color:#ffffff;">{{ $user->job_keywords }}</strong>
                    @if($user->job_location)
                        in <strong style="color:#ffffff;">{{ $user->job_location }}</strong>
                    @endif
                </div>
            </td></tr>
        </table>
    </td></tr>

    {{-- Section Label --}}
    <tr><td style="padding:0 4px 12px 4px;">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
            <td style="font-size:12px;color:#6b7280;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;">
                Latest Matches
            </td>
            <td style="text-align:right;font-size:12px;color:#9ca3af;font-weight:600;">
                {{ $jobs->count() }} result{{ $jobs->count() !== 1 ? 's' : '' }}
            </td>
        </tr></table>
    </td></tr>

    {{-- Job Listings --}}
    @foreach($jobs as $job)
    <tr><td style="padding:0 0 14px 0;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #eef0f4;border-radius:16px;">
            <tr>
                {{-- Accent bar --}}
                <td width="4" style="background:linear-gradient(180deg,#6366f1,#a855f7);border-radius:16px 0 0 16px;">&nbsp;</td>
                <td style="padding:22px 22px 22px 20px;">
                    <table width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td style="vertical-align:top;">
                            @if($job->source ?? null)
                                <span style="display:inline-block;padding:3px 9px;background:#eef2ff;color:#4f46e5;border-radius:6px;font-size:10px;font-weight:700;letter-spacing:0.4px;text-transform:uppercase;margin-bottom:10px;">{{ $job->source }}</span>
                            @endif
                            <div style="font-size:16px;font-weight:700;color:#111827;line-height:1.4;margin-top:6px;">
                                <a href="{{ $job->url }}" style="color:#111827;text-decoration:none;">{{ $job->title }}</a>
                            </div>
                            <div style="font-size:13.5px;color:#6b7280;margin-top:3px;font-weight:600;">{{ $job->company }}</div>

                            <table cellpadding="0" cellspacing="0" style="margin-top:12px;"><tr>
                                @if($job->location ?? null)
                                <td style="padding-right:14px;font-size:12px;color:#6b7280;font-weight:500;">
                                    <span style="color:#a1a5b0;">&#128205;</span> {{ $job->location }}
                                </td>
                                @endif
                                @if($job->salary_text ?? null)
                                <td style="padding-right:14px;font-size:12px;color:#6b7280;font-weight:500;">
                                    <span style="color:#a1a5b0;">&#128176;</span> {{ $job->salary_text }}
                                </td>
                                @endif
                                @if($job->posted_at ?? null)
                                <td style="font-size:12px;color:#6b7280;font-weight:500;">
                                    <span style="color:#a1a5b0;">&#128336;</span> {{ $job->posted_at }}
                                </td>
                                @endif
                            </tr></table>

                            <div style="margin-top:16px;">
                                <a href="{{ $job->url }}" style="display:inline-block;padding:11px 22px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#ffffff;border-radius:9px;font-size:12.5px;text-decoration:none;font-weight:700;letter-spacing:0.2px;">View Job &rarr;</a>
                            </div>
                        </td>
                    </tr></table>
                </td>
            </tr>
        </table>
    </td></tr>
    @endforeach

    {{-- Manage Preferences CTA --}}
    <tr><td style="padding:8px 0 0 0;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#fafafa;border:1px dashed #d8dae3;border-radius:16px;">
            <tr><td style="padding:24px;text-align:center;">
                <div style="font-size:13.5px;color:#4b5563;margin-bottom:14px;font-weight:500;">
                    Want to fine-tune your alerts or search criteria?
                </div>
                <a href="{{ url('/profile') }}" style="display:inline-block;padding:10px 22px;background:#ffffff;color:#111827;border:1px solid #d1d5db;border-radius:9px;font-size:12.5px;text-decoration:none;font-weight:700;">Manage Preferences</a>
            </td></tr>
        </table>
    </td></tr>

    {{-- Footer --}}
    <tr><td style="padding:32px 4px 0 4px;text-align:center;">
        <div style="font-size:11px;color:#c4c7d0;line-height:1.7;">
            Powered by <strong style="color:#9ca3af;">AI Chatbot</strong> &middot; Job Intelligence System<br>
            You're receiving this because job alerts are enabled in your
            <a href="{{ url('/profile') }}" style="color:#8b8fa3;text-decoration:underline;">profile settings</a>.
        </div>
    </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
