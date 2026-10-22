# Bukti Implementasi Odontogram dan Allergy Alert

Tanggal verifikasi: 22 Oktober 2026  
Status: implementasi Tugas 3 selesai

## Hasil kerja

Halaman pemeriksaan menerima kontrak odontogram dari `VisitController`, lalu merendernya melalui komponen Blade presentasional. Interaksi hanya mengubah state Alpine lokal dan mengirim custom event. Komponen tidak membuat request, field form, atau penyimpanan server.

Editor odontogram tetap interaktif pada Draft maupun Modification. Prop `read_only` tetap tersedia untuk pemakaian komponen di konteks histori atau tampilan non-editor.

### Komponen

| Komponen | Tanggung jawab |
| --- | --- |
| `resources/views/components/dental-chart.blade.php` | Odontogram 32 gigi FDI, selection, kondisi, legend, read-only, disabled state, dan callback event. |
| `resources/views/components/critical-allergy-banner.blade.php` | Peringatan alergi kritis dari data input controller. |
| `app/Http/Controllers/Doctor/VisitController.php` | Menyediakan struktur awal gigi, daftar kondisi, selected state, read-only state, dan data alergi. |

## Kontrak data odontogram

```php
[
    'teeth' => [[
        'id' => '11',
        'label' => 'Gigi 11',
        'quadrant' => 'upper-left',
        'condition' => 'caries',
        'disabled' => false,
    ]],
    'conditions' => [[
        'value' => 'caries',
        'label' => 'Karies',
    ]],
    'selected_tooth' => '11',
    'read_only' => false,
]
```

Kondisi awal yang didukung:

- `unknown` — Belum dicatat;
- `healthy` — Sehat;
- `caries` — Karies;
- `filled` — Tambalan;
- `missing` — Hilang;
- `crown` — Mahkota.

## Callback integrasi

Komponen mengirim dua bubbling custom event:

```js
window.addEventListener('dental-tooth-selected', event => {
    console.log(event.detail.tooth);
});

window.addEventListener('dental-condition-changed', event => {
    console.log(event.detail.tooth);
});
```

Hook markup:

```html
<section
    data-dental-chart
    data-persists="false"
    data-tooth-selected-event="dental-tooth-selected"
    data-condition-changed-event="dental-condition-changed"
>
```

Tidak ada `name` pada kontrol dental, sehingga submit RME saat ini tidak mengirim payload odontogram.

## Kontrak allergy alert

```php
[
    [
        'substance' => 'Penisilin',
        'reaction' => 'Anafilaksis',
        'severity' => 'Kritis',
    ],
]
```

Banner tidak dirender bila array kosong. Frontend tidak menyimpulkan alergi; seluruh isi berasal dari input controller.

## Accessibility dan responsive

- Setiap gigi serta kondisi memakai elemen `button` native.
- Selection memakai ring visual, teks “Gigi terpilih”, dan `aria-pressed`.
- Kondisi menampilkan label teks serta tanda centang; tidak hanya warna.
- Disabled dan read-only memakai atribut native.
- Empat kolom per kuadran mencegah horizontal overflow pada mobile.
- Legend memiliki label kondisi eksplisit.
- Allergy banner memakai ikon, judul, `role="alert"`, `tabindex="0"`, dan focus ring.

## Bukti testing

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
php artisan test --compact tests/Feature/DentalChartComponentTest.php tests/Feature/VisitWorkflowTest.php tests/Feature/MedicalRecordCorrectionTest.php
```

Hasil:

```text
PASS  16 tests, 172 assertions
```

Pemeriksaan tambahan:

```text
Laravel Pint: PASS
Vite 8.3.2 build: PASS
git diff --check: PASS
```

Test membuktikan selected, disabled, read-only, legend, callback event, tanpa persistence langsung, render controller, banner terisi, dan banner kosong.

## Batas implementasi

- Kontrak API final Dhomi/Vioni belum tersedia. Controller memakai daftar FDI standar dengan kondisi awal `unknown`.
- Data alergi belum tersedia pada model pasien; controller mengirim array kosong agar aplikasi tidak menampilkan alert klinis palsu.
- Screenshot browser belum disimpan. Bukti saat ini berupa component/feature test, build frontend, formatter, dan dokumentasi kontrak.
