---
name: ClinicOS
description: Panduan visual berdasarkan landing page ClinicOS pada index.html.
source: index.html
supporting-reference: stitch-prompt.md
colors:
  primary: "#22C55E"
  primary-hover: "#16A34A"
  primary-light: "#DCFCE7"
  primary-tint: "#F0FDF4"
  primary-border: "#BBF7D0"
  secondary: "#10B981"
  background: "#FAFAFA"
  surface: "#FFFFFF"
  surface-muted: "#F4F7F5"
  surface-dark: "#0D1F18"
  surface-dark-inner: "#071510"
  text-base: "#0A0A0A"
  text-heading: "#0F172A"
  text-secondary: "#64748B"
  text-muted: "#94A3B8"
  text-on-dark: "#FFFFFF"
  border: "#E2E8F0"
  success: "#16A34A"
  warning: "#F59E0B"
  error: "#EF4444"
typography:
  display-family: "'Plus Jakarta Sans', sans-serif"
  body-family: "'Inter', sans-serif"
  hero-size: "36px / 48px / 60px"
  hero-line-height: 1.1
  section-size: "24px / 30px"
  card-title-size: "18px"
  body-size: "14px"
  compact-body-size: "12px"
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  full: "9999px"
layout:
  max-width: "1280px"
  mobile-gutter: "16px"
  wide-gutter: "32px"
  mobile-section-gap: "48px"
  wide-section-gap: "64px"
  card-padding: "24px"
---

# Panduan Desain WizardZ & TeamHub

## 1. Acuan dan ruang lingkup

Dokumen ini menurunkan sistem visual dari `index.html`, dengan `stitch-prompt.md` sebagai referensi pendukung. HTML menjadi acuan utama jika keduanya berbeda. Panduan disusun melalui pemeriksaan struktur, kelas Tailwind, konfigurasi warna, dan interaksi JavaScript; bukan melalui verifikasi screenshot.

Desain yang tersedia adalah landing page agensi digital dengan bagian operasional TeamHub. Dashboard eksekutif, sidebar permanen, grafik pertumbuhan tahunan, dan tabel invoice merupakan permintaan dalam prompt Stitch yang belum diwujudkan pada HTML. Jangan mendokumentasikannya sebagai komponen yang sudah tersedia.

Identitas referensi tetap WizardZ. Bagian terakhir menjelaskan cara menggunakan bahasa visual ini untuk ClinicFlow tanpa mengubah stack atau cakupan MVP.

## 2. Karakter visual

Gunakan kanvas hampir putih, kartu putih berbingkai tipis, sudut membulat, dan aksen hijau emerald. Hierarki dibangun dengan judul besar, ruang kosong yang cukup, label kecil, dan satu panel forest gelap yang menonjol di antara bagian terang.

CTA utama pada hero menggunakan latar gelap dengan teks putih dan ikon dalam lingkaran hijau. CTA pada panel gelap menggunakan latar hijau dengan teks forest. Hijau juga muncul pada badge, indikator aktivitas, angka hasil, dan wadah ikon.

Ilustrasi memakai ikon Lucide, lapisan geometris, rotasi ringan, glow, dan bayangan untuk memberi kesan kedalaman. Megafon pada hero adalah komposisi CSS dan ikon, bukan aset 3D eksternal.

## 3. Warna

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| Primary | `#22C55E` | Aksen, tombol hijau, ikon, indikator positif |
| Primary hover | `#16A34A` | Token hover hijau dalam konfigurasi |
| Primary light | `#DCFCE7` | Badge, ribbon, latar ikon |
| Primary tint | `#F0FDF4` | Panel metrik ringan |
| Primary border | `#BBF7D0` | Bingkai pada permukaan hijau muda |
| Secondary | `#10B981` | Aksen pendukung dari prompt; bukan token brand eksplisit di HTML |
| Canvas | `#FAFAFA` | Latar halaman aktual |
| Surface | `#FFFFFF` | Kartu, header, modal, footer |
| Surface muted | `#F4F7F5` | Mini panel dan wadah informasi |
| Forest | `#0D1F18` | CTA gelap, panel studi kasus, toast |
| Forest inner | `#071510` | Token tambahan dalam konfigurasi; opsional |
| Base text | `#0A0A0A` | Warna teks bawaan body |
| Heading | `#0F172A` | Judul dengan kelas slate-900 |
| Secondary text | `#64748B` | Metadata dan teks pendukung |
| Muted text | `#94A3B8` | Informasi berprioritas rendah |
| Border | `#E2E8F0` | Pemisah dan bingkai kartu |
| On dark | `#FFFFFF` | Judul dan CTA pada latar gelap |

HTML juga menggunakan emerald-600 `#059669` untuk kata yang ditekankan, emerald-700 `#047857` untuk tautan, dan slate-600 `#475569` untuk beberapa paragraf. Warna pink, biru, oranye, dan merah pada strip brand merupakan aksen ilustratif lokal.

Success, warning, dan error pada frontmatter adalah token semantik yang direkomendasikan dari panduan awal/prompt. Status warning dan error belum memiliki komponen lengkap pada halaman referensi.

Gunakan teks forest pada latar primary hijau. Jangan menganggap semua teks kecil atau warna muted telah memenuhi aksesibilitas; ukur kontras pada pasangan warna dan ukuran teks final.

## 4. Tipografi

| Elemen | Font | Ukuran | Bobot dan ritme |
| --- | --- | --- | --- |
| Hero | Plus Jakarta Sans | 36px mobile, 48px mulai sm, 60px mulai lg | Sangat tebal, line-height 1.1, tracking rapat |
| Judul bagian | Plus Jakarta Sans | 24–30px | 700–800 atau kelas font-black |
| Headline panel gelap | Plus Jakarta Sans | 30–36px | Sangat tebal, tracking rapat |
| Judul kartu layanan | Plus Jakarta Sans | 18px | 700, line-height snug |
| Judul profil/panel | Plus Jakarta Sans | 16px | 700 |
| Body hero | Inter | 14–16px | 400, line-height relaxed |
| Body kartu/UI ringkas | Inter | 12–14px | 400–600 |
| Label/badge | Inter | 10–11px | 700, uppercase dan tracking lebar bila diperlukan |
| Angka hasil | Plus Jakarta Sans | 30px | Sangat tebal, hijau pada latar gelap |

Font eksternal yang dimuat HTML menyediakan Plus Jakarta Sans 500–800 dan Inter 300–700. Kelas `font-black` meminta bobot 900 yang belum dimuat; untuk implementasi konsisten gunakan 800 atau sediakan bobot 900.

Gunakan `font-variant-numeric: tabular-nums` untuk angka dashboard yang berubah atau sejajar dalam kolom. Ini rekomendasi untuk pengembangan; referensi memakai monospace pada beberapa metrik, tanpa penerapan tabular figures global.

## 5. Layout dan ruang

Container utama dan isi header memiliki lebar maksimum 1280px, berada di tengah. Gutter 16px pada layar kecil dan 32px mulai breakpoint sm. Main memiliki padding vertikal 24px, meningkat menjadi 40px mulai sm.

Jarak antarbagian 48px pada mobile dan 64px mulai sm. Padding kartu umumnya 24px; panel hero/modal menggunakan 24–32px dan panel gelap 24–40px. Gap grid yang dominan adalah 20px, 24px, atau 32px sesuai bagian.

Gunakan kelipatan 4px sebagai skala dasar: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64px. Referensi juga memiliki penyesuaian lokal seperti 6, 10, dan 14px untuk detail ikon dan badge; jangan menyatakan semua ukuran mengikuti grid 8px secara ketat.

| Bagian | Susunan |
| --- | --- |
| Hero | Satu kolom; pada lg menjadi 12 kolom, teks 7 dan visual 5 |
| Strip brand | Flex dengan wrap dan jarak fleksibel |
| Layanan | 1 kolom, 2 mulai sm, 4 mulai lg; gap 20px |
| CTA gelap | 1 kolom; pada lg menjadi teks 8 dan ilustrasi 4 dari 12 kolom |
| Studi kasus | 1 kolom, 3 mulai md; gap 16px |
| TeamHub | 1 kolom; pada lg menjadi profil 5 dan kalender 7 dari 12 kolom |
| Ribbon CTA | Vertikal, menjadi horizontal mulai sm |

## 6. Bentuk, bingkai, dan kedalaman

- Kartu besar, hero visual, modal, dan ribbon: radius 24px.
- Mini panel, ikon layanan, dan toast: radius 16px.
- Input, tag dokumen, dan sel kalender: radius 12px.
- Tombol, badge, avatar kecil, dan orbit node: radius penuh.
- Bingkai utama: 1px solid `#E2E8F0`; gunakan bingkai hijau muda untuk permukaan beraksen.
- Panel anak pada latar gelap: putih dengan opacity 5%, bingkai putih opacity 10%.

Gunakan bayangan ringan pada kartu biasa. Hero visual, panel gelap, modal, dan toast boleh memiliki bayangan lebih kuat sesuai referensi. Glow emerald terbatas pada dekorasi hero dan panel gelap.

Preset bayangan ringan yang disarankan untuk implementasi stabil:

```css
box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
```

HTML memakai Tailwind CDN dan beberapa kelas seperti `shadow-xs`, `backdrop-blur-xs`, `animate-in`, serta ukuran nonstandar. Pastikan kelas tersedia pada versi/build yang digunakan atau definisikan CSS eksplisit; nama kelas saja tidak membuktikan efeknya aktif.

## 7. Komponen

### Header dan navigasi

Header sticky, permukaan putih opacity 95%, blur, bingkai bawah, dan z-index 40. Logo berupa ikon zap pada kotak hijau muda 36px dengan radius 12px, diikuti wordmark tebal. Navigasi desktop mulai md; tombol quote mulai sm; menu mobile membuka drawer kanan dengan overlay.

### Tombol

| Varian | Tampilan | Penggunaan |
| --- | --- | --- |
| Primary dark | Forest, teks putih, radius penuh, padding sekitar 20px × 12px | Konsultasi, quote, CTA ribbon |
| Primary green | Primary hijau, teks forest, radius penuh | Proposal pada panel gelap, submit form |
| Secondary | Putih, bingkai slate-200, teks slate-800 | Lihat layanan |
| Icon action | Lingkaran hijau dengan ikon forest | Detail layanan |
| Compact dark | Slate-900, teks putih, radius 12px | Lihat seluruh tim |

CTA hero memiliki trailing icon dalam lingkaran 24px. Ikon bergeser sedikit saat hover. Tombol memakai active scale 0.95 pada referensi; untuk UI operasional dapat diperkecil menjadi 0.99 agar gerakan lebih tenang.

### Kartu layanan

Kartu putih radius 24px, padding 24px, bingkai tipis. Wadah ikon 64px dengan radius 16px, gradient emerald muda, dan ikon 32px. Judul 18px, body 12px, footer berpemisah dengan tautan dan tombol panah 32px. Susunan flex menjaga footer tetap di bawah.

### Hero visual

Kartu semi-transparan dengan radius 24px dan glow di belakang. Megafon berada pada lapisan kotak berotasi dan ring lingkaran. Empat pill orbit (Share, Growth, Engage, Reach) mengelilinginya. Panel metrik hijau muda di bawah berubah saat node diklik.

### Panel gelap dan metrik

Forest menjadi latar CTA dan tiga kartu studi kasus. Angka hasil berukuran 30px berwarna primary. Kartu anak menggunakan permukaan transparan, radius 16px, dan ikon kecil. Pisahkan ajakan utama dan hasil dengan divider putih opacity 10%.

### Profil dan kalender TeamHub

Profil memuat avatar 56px, identitas, badge status, tiga mini panel metrik, tag dokumen, serta footer aksi. Kalender menggunakan grid tujuh kolom, header navigasi, tanggal beraksen untuk milestone, dan strip progres. Ring indikator pada referensi bersifat dekoratif; jangan memperlakukannya sebagai visualisasi proporsional yang akurat.

### Modal dan form

Overlay slate-900 opacity 50%, z-index 50. Modal konsultasi maksimum 512px, modal informasi maksimum 448px, radius 24px. Input berbingkai slate-200, radius 12px, padding 14px × 10px, label eksplisit. Focus input memakai bingkai hijau dan ring hijau transparan.

### Toast

Toast forest di kanan bawah, jarak 24px, radius 16px, ikon check dalam lingkaran hijau. Animasi opacity dan translate selama 300ms; otomatis hilang setelah sekitar 3,2 detik. Pada mobile batasi lebar sesuai viewport.

## 8. Urutan halaman referensi

1. Header sticky dengan logo, navigasi, dan quote.
2. Hero dua kolom dengan headline, deskripsi, CTA, social proof, dan visual megafon.
3. Strip brand dalam kartu putih.
4. Empat kartu layanan dengan judul bagian terpusat.
5. Panel CTA gelap dan tiga hasil studi kasus.
6. TeamHub dengan toggle, profil, metrik, dan kalender.
7. Ribbon hijau muda dengan CTA penutup.
8. Footer putih dengan logo, navigasi, dan ikon sosial.

Logo brand, statistik, avatar, tanggal, dan hasil merupakan konten contoh. Jangan menganggapnya sebagai bukti klien atau capaian aktual.

## 9. Interaksi dan motion

Interaksi yang tersedia di prototype meliputi drawer mobile, modal konsultasi, popup layanan/studi kasus, pembaruan metrik orbit, toggle TeamHub, dan toast. Submit konsultasi hanya menampilkan feedback dan mereset form; tidak membuktikan penyimpanan atau pemesanan ke backend.

Float hero bergerak vertikal 6px dalam siklus 5 detik, dengan varian delay 2,5 detik. Hover kartu berlangsung sekitar 300ms, toggle 200ms, dan ikon memakai transform ringan. Batasi animasi berulang pada dekorasi.

Untuk implementasi produk, tambahkan `prefers-reduced-motion`, hindari animasi dekoratif pada pemeriksaan medis, dan gunakan loading/disabled/error state yang jelas. Skeleton dan pending state merupakan pengembangan yang direkomendasikan, belum tersedia lengkap pada referensi.

## 10. Responsive

Gunakan breakpoint yang mengikuti kelas HTML: sm 640px, md 768px, lg 1024px. Lebar 1280px adalah batas container, bukan breakpoint awal semua grid desktop.

Di bawah md, navigasi desktop disembunyikan dan drawer mobile digunakan. Hero dan TeamHub bertumpuk sampai lg. Layanan menjadi dua kolom mulai sm dan empat mulai lg. Footer dan ribbon menyesuaikan menjadi vertikal di layar kecil.

Pertahankan urutan teks sebelum ilustrasi. Cegah pill orbit, label kalender, footer, dan metrik panjang menimbulkan overflow. Dashboard dengan sidebar tetap, KPI horizontal scroll, dan bottom action sheet dari prompt membutuhkan desain lanjutan tersendiri.

## 11. Aksesibilitas untuk implementasi

Persyaratan berikut adalah target pengembangan, bukan klaim kepatuhan prototype:

- Sediakan focus-visible yang jelas pada seluruh kontrol; rekomendasi ring hijau 2px dengan offset 2px.
- Gunakan area interaksi minimal 44 × 44px. Beberapa tombol referensi hanya 32px atau lebih kecil dan perlu diperluas tanpa harus memperbesar ikon.
- Modal memakai dialog semantics, nama yang jelas, focus trap, Escape untuk menutup, dan pengembalian fokus ke pemicu.
- Toggle harus memiliki accessible name, dukungan keyboard, dan status `aria-checked` yang sinkron.
- Beri accessible label pada tombol ikon; sembunyikan ikon dekoratif dari pembaca layar.
- Gunakan live region untuk toast dan perubahan status yang relevan.
- Bedakan status dengan teks/ikon, selain warna.
- Pastikan tanggal interaktif adalah kontrol keyboard, bukan sekadar div ber-cursor pointer.
- Ukur kontras teks dan kontrol pada keadaan normal, hover, focus, dan disabled.

## 12. Penerapan pada ClinicFlow

Gunakan visual ini sebagai inspirasi untuk ClinicFlow: kartu putih, permukaan forest untuk CTA tertentu, badge emerald, tipografi Jakarta Sans/Inter, radius 12–24px, dan layout yang lapang. Ganti konten agensi dengan informasi klinik yang relevan.

| Pola referensi | Adaptasi ClinicFlow |
| --- | --- |
| Hero dan CTA konsultasi | Informasi klinik dan CTA booking |
| Kartu layanan agensi | Layanan klinik dan harga |
| Profil strategist | Profil dokter dan jadwal |
| Kalender sprint | Jadwal dokter atau booking |
| Mini metrik | Pasien hari ini, booking, kunjungan selesai, antrean aktif, pendapatan |
| Badge aktivitas | Status booking, antrean, dan kunjungan |
| Modal discovery | Form registrasi/booking sesuai alur yang ditetapkan |

Untuk UI dokter dan resepsionis, prioritaskan keterbacaan, kepadatan informasi yang wajar, tabel, form, dan status yang jelas. Dekorasi hero dan orbit cukup digunakan pada halaman publik. Perluas body teks operasional menjadi 14–16px bila diperlukan.

Implementasi tetap Laravel, Blade, Livewire, Tailwind CSS, dan MySQL. Permintaan Next.js/React dalam prompt referensi tidak mengubah stack ClinicFlow. Sidebar dashboard, tabel pasien, SOAP, resep, dan transaksi membutuhkan spesifikasi komponen lanjutan; HTML ini belum mendefinisikannya. Persyaratan klinis/legal yang belum disepakati tetap TBD.

## 13. Aturan konsistensi

- Pertahankan pasangan warna, font, radius, dan hierarki CTA yang sama antarhalaman.
- Gunakan latar gelap secara selektif untuk penekanan.
- Hindari teks putih pada primary hijau tanpa verifikasi kontras.
- Bedakan aksi utama, aksi sekunder, dan aksi berbahaya.
- Jangan menyalin statistik/brand contoh sebagai data nyata.
- Jangan membawa navigasi dummy ke produk; beberapa anchor pada referensi belum memiliki section tujuan.
- Jangan menyatakan accessibility, realtime backend, atau penyimpanan form telah selesai hanya dari tampilan prototype.
