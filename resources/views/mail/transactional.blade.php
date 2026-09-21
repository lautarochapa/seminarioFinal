<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }}</title></head>
<body style="margin:0;background:#f4f7f6;color:#242629;font-family:Arial,sans-serif;line-height:1.6">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff">
<tr><td style="padding:20px 28px;background:#242629;color:#ffffff;font-size:20px;font-weight:bold">CocinaComidaControl</td></tr>
<tr><td style="padding:28px">
<h1 style="font-size:24px;line-height:1.3;margin:0 0 20px">{{ $title }}</h1>
@foreach($paragraphs as $paragraph)<p>{{ $paragraph }}</p>@endforeach
<p style="margin:28px 0"><a href="{{ $actionUrl }}" style="display:inline-block;background:#007d66;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:bold">{{ $actionLabel }}</a></p>
<p style="font-size:13px;color:#52605b">Si el bot&oacute;n no abre, us&aacute; este enlace:</p>
<p style="font-size:13px;overflow-wrap:anywhere;word-break:break-all"><a href="{{ $actionUrl }}" style="color:#007d66">{{ $actionUrl }}</a></p>
<p>Equipo de CocinaComidaControl</p>
</td></tr></table>
</td></tr></table>
</body></html>
