<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet['room'] }} - Passwort</title>
    <style>
        @page { margin: 10mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #1c1c1c; }
        table { border-collapse: collapse; }
        .page { width: 190mm; table-layout: fixed; page-break-after: always; }
        .page.last { page-break-after: auto; }
        .slot { width: 95mm; height: 69mm; padding: 0; vertical-align: top; }
        .slot.left { border-right: 0.6pt dashed #9a9a9a; }
        .cut .slot { border-bottom: 0.6pt dashed #9a9a9a; }
        .card { margin: 7mm 8mm 0 5mm; height: 56mm; overflow: hidden; border-left: 2.6pt solid #2f5fa8; padding-left: 4.5mm; }
        .head { width: 100%; }
        .school { font-size: 6pt; font-weight: bold; color: #2f5fa8; letter-spacing: 0.5pt; text-transform: uppercase; }
        .room { font-size: 6pt; color: #666; letter-spacing: 0.5pt; text-transform: uppercase; text-align: right; }
        .name { font-size: 12pt; font-weight: bold; margin: 3mm 0 3.5mm 0; }
        .name.long { font-size: 9.5pt; margin: 2.5mm 0 2.5mm 0; }
        .fields td { padding: 0 0 2mm 0; vertical-align: middle; }
        .label { width: 23mm; font-size: 5.5pt; color: #555; letter-spacing: 0.5pt; text-transform: uppercase; }
        .value { font-size: 8.5pt; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 9pt; }
        .password { font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; font-size: 13pt; letter-spacing: 1.6pt;
                    background: #eaf1fb; padding: 1.4mm 2.5mm; }
        .note { font-size: 5.5pt; color: #666; margin-top: 3mm; }
    </style>
</head>
<body>
@foreach (array_chunk($sheet['rows'], 8) as $page)
    <table class="page {{ $loop->last ? 'last' : '' }}">
        @foreach (array_chunk($page, 2) as $pair)
            <tr class="{{ $loop->last ? '' : 'cut' }}">
                @foreach ([0, 1] as $side)
                    <td class="slot {{ $side === 0 ? 'left' : '' }}">
                        @isset($pair[$side])
                            <div class="card">
                                <table class="head">
                                    <tr>
                                        <td class="school">{{ $tenant->name }}</td>
                                        <td class="room">Raum: {{ $sheet['room'] }}</td>
                                    </tr>
                                </table>
                                <div class="name {{ mb_strlen($pair[$side]['name']) > 26 ? 'long' : '' }}">{{ $pair[$side]['name'] }}</div>
                                <table class="fields">
                                    <tr>
                                        <td class="label">Benutzername</td>
                                        <td class="mono">{{ $pair[$side]['username'] }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Passwort</td>
                                        <td><span class="password">{{ $pair[$side]['password'] }}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Klasse</td>
                                        <td class="value">{{ $pair[$side]['classes'] !== '' ? $pair[$side]['classes'] : '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Schul-Code</td>
                                        <td class="mono">{{ $tenant->instance_code }}</td>
                                    </tr>
                                </table>
                                <div class="note">Bitte nach der ersten Anmeldung ein eigenes Passwort setzen.</div>
                            </div>
                        @endisset
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endforeach
</body>
</html>
