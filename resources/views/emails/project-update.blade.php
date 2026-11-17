<!doctype html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $heading }}</title>
<style>table{border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt}td,p,a,h1{font-family:Arial,Helvetica,sans-serif} @media(max-width:620px){.email-content{padding:22px 18px!important}}</style></head>
<body style="margin:0;padding:0;background-color:#F2F4F3;color:#263B31;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F2F4F3"><tr><td align="center" style="padding:24px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF" style="max-width:600px;border:1px solid #DFE6E1;">
<tr><td bgcolor="#315F58" style="padding:18px 24px;">
<p style="margin:0;font-size:18px;line-height:24px;font-weight:bold;color:#FFFFFF;">GS ProjectFlow</p>
<p style="margin:3px 0 0;font-size:11px;line-height:16px;color:#DDEAE4;">Project workspace</p>
</td></tr>
<tr><td class="email-content" style="padding:26px 24px;">
<p style="margin:0 0 8px;font-size:10px;line-height:16px;letter-spacing:1px;font-weight:bold;text-transform:uppercase;color:#52796A;">{{ $eyebrow }}</p>
<h1 style="margin:0 0 18px;font-size:22px;line-height:29px;color:#243E31;">{{ $heading }}</h1>
<p style="margin:0 0 10px;font-size:14px;line-height:22px;">Hello {{ $recipient->name }},</p>
<p style="margin:0 0 20px;font-size:14px;line-height:22px;color:#586B60;">{{ $intro }}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#F8FAF9" style="border:1px solid #DFE7E1;">
<tr><td colspan="2" style="padding:14px 16px;border-bottom:1px solid #DFE7E1;">
<p style="margin:0;font-size:15px;line-height:21px;font-weight:bold;">{{ $project->name }}</p>
<p style="margin:4px 0 0;font-size:11px;line-height:17px;color:#6B7F72;">{{ $project->code }}@if($project->subsidiary) &middot; {{ $project->subsidiary->name }}@endif</p>
</td></tr>
<tr><td width="45%" style="padding:12px 16px 5px;font-size:12px;line-height:18px;color:#6B7F72;">Project manager</td><td style="padding:12px 16px 5px;font-size:12px;line-height:18px;">{{ $project->manager?->name ?? 'Not assigned' }}</td></tr>
<tr><td style="padding:5px 16px;font-size:12px;line-height:18px;color:#6B7F72;">Start date</td><td style="padding:5px 16px;font-size:12px;line-height:18px;">{{ $project->starts_on?->format('d M Y') ?? 'Not scheduled' }}</td></tr>
<tr><td style="padding:5px 16px 12px;font-size:12px;line-height:18px;color:#6B7F72;">Target end date</td><td style="padding:5px 16px 12px;font-size:12px;line-height:18px;">{{ $project->ends_on?->format('d M Y') ?? 'Not scheduled' }}</td></tr>
</table>
@if($project->description)<p style="margin:16px 0 0;font-size:13px;line-height:21px;color:#586B60;">{{ \Illuminate\Support\Str::limit($project->description,500) }}</p>@endif
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-top:22px;">
<!--[if mso]><v:roundrect href="{{ $actionUrl }}" style="height:42px;v-text-anchor:middle;width:240px;" arcsize="10%" strokecolor="#315F58" fillcolor="#315F58"><w:anchorlock/><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;">{{ $actionLabel }}</center></v:roundrect><![endif]-->
<!--[if !mso]><!--><a href="{{ $actionUrl }}" style="display:inline-block;background-color:#315F58;border-radius:5px;padding:12px 22px;color:#FFFFFF;font-size:13px;line-height:18px;font-weight:bold;text-decoration:none;">{{ $actionLabel }}</a><!--<![endif]-->
</td></tr></table>
<p style="margin:16px 0 0;font-size:12px;line-height:19px;color:#718176;">{{ $guidance }}</p>
</td></tr>
<tr><td style="padding:16px 24px;border-top:1px solid #E4EAE6;">
<p style="margin:0 0 6px;font-size:11px;line-height:17px;color:#7A8980;">This is an automated notification from GS ProjectFlow.</p>
<p style="margin:0;font-size:11px;line-height:17px;color:#7A8980;">If the button does not work, open this link:</p>
<a href="{{ $actionUrl }}" style="font-size:11px;line-height:17px;color:#315F58;word-break:break-all;">{{ $actionUrl }}</a>
</td></tr></table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr></table>
</body></html>
