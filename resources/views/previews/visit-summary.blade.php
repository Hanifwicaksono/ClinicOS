@php
    $mockVisits = [
        [
            'date' => '2 November 2026, 09:15',
            'datetime' => '2026-11-02T09:15:00+07:00',
            'doctor' => 'dr. Maya Putri, Sp.PD',
            'service' => 'Konsultasi penyakit dalam',
            'status' => 'COMPLETED',
        ],
        [
            'date' => '2 November 2026, 10:40',
            'datetime' => '2026-11-02T10:40:00+07:00',
            'doctor' => 'drg. Raka Adinata',
            'service' => 'Pemeriksaan gigi umum',
            'status' => 'IN_PROGRESS',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Preview Ringkasan Kunjungan - ClinicOS</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-clinic-background px-4 py-8 font-sans text-clinic-text sm:px-8 sm:py-12">
        <main class="mx-auto max-w-5xl space-y-8">
            <header>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-clinic-600">Component preview - mock data</p>
                <h1 class="mt-2 text-3xl font-extrabold text-clinic-950">Ringkasan kunjungan staf</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Preview presentasional tanpa API, database, atau state realtime.</p>
            </header>

            <x-visit-summary :visits="$mockVisits" />

            <x-visit-summary
                :visits="[]"
                title="Ringkasan kunjungan hari berikutnya"
                description="Contoh empty state dari props."
            />
        </main>
    </body>
</html>
