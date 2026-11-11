<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Temporary passwords - {{ $tenant->name }}</title>
    <style>
        @page { margin: 18mm 16mm 20mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1a1a1a; }
        .sheet { page-break-after: always; }
        .sheet.last { page-break-after: auto; }
        .school { font-size: 9pt; color: #555; text-transform: uppercase; letter-spacing: 0.6pt; }
        h1 { font-size: 20pt; margin: 2pt 0 4pt 0; }
        .meta { font-size: 9pt; color: #555; margin-bottom: 12pt; }
        .meta strong { color: #1a1a1a; font-family: 'DejaVu Sans Mono', monospace; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #555;
             border-bottom: 1.2pt solid #1a1a1a; padding: 4pt 6pt; }
        td { padding: 6pt; border-bottom: 0.4pt solid #bbb; vertical-align: top; }
        tr { page-break-inside: avoid; }
        .password { font-family: 'DejaVu Sans Mono', monospace; font-size: 11pt; letter-spacing: 0.8pt; white-space: nowrap; }
        .username { font-family: 'DejaVu Sans Mono', monospace; }
        .note { margin-top: 12pt; font-size: 8pt; color: #555; }
    </style>
</head>
<body>
@foreach ($sheets as $sheet)
    <div class="sheet {{ $loop->last ? 'last' : '' }}">
        <div class="school">{{ $tenant->name }}</div>
        <h1>{{ $sheet['room'] }}</h1>
        <div class="meta">
            School code <strong>{{ $tenant->instance_code }}</strong>
            &nbsp;&middot;&nbsp; {{ count($sheet['rows']) }} {{ count($sheet['rows']) === 1 ? 'user' : 'users' }}
            &nbsp;&middot;&nbsp; {{ $generatedAt->format('d.m.Y H:i') }}
        </div>
        <table>
            <thead>
            <tr>
                <th style="width: 32%">Full name</th>
                <th style="width: 26%">Username</th>
                <th style="width: 20%">Temporary password</th>
                <th style="width: 22%">Class</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($sheet['rows'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="username">{{ $row['username'] }}</td>
                    <td class="password">{{ $row['password'] }}</td>
                    <td>{{ $row['classes'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="note">Passwords are listed in plain text. Hand out each line separately and destroy this sheet afterwards.</div>
    </div>
@endforeach
</body>
</html>
