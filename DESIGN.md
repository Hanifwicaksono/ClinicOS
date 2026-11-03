---
name: ClinicOS
description: Sistem visual operasional klinik yang tenang, terhubung, dan mudah dipindai.
colors:
  primary: "#287F78"
  primary-hover: "#206A64"
  primary-subtle: "#EAF2F2"
  primary-soft: "#D4E5E4"
  secondary: "#3D5F5C"
  accent: "#D89B68"
  background: "#F7F8F5"
  surface: "#FFFFFF"
  text: "#253331"
  border: "#DDE5E1"
  overlay-strong: "color-mix(in srgb, #253331 88%, transparent)"
  overlay-soft: "color-mix(in srgb, #253331 58%, transparent)"
typography:
  display:
    fontFamily: "Plus Jakarta Sans, Inter, ui-sans-serif, sans-serif"
    fontSize: "clamp(2.625rem, 4.6vw, 4.1875rem)"
    fontWeight: 800
    lineHeight: 1.18
    letterSpacing: "-0.055em"
  headline:
    fontFamily: "Plus Jakarta Sans, Inter, ui-sans-serif, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 800
    lineHeight: 1.25
    letterSpacing: "-0.035em"
  title:
    fontFamily: "Plus Jakarta Sans, Inter, ui-sans-serif, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 800
    lineHeight: 1.4
  body:
    fontFamily: "Inter, ui-sans-serif, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.6
  marketing-body:
    fontFamily: "DM Sans, Arial, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Inter, ui-sans-serif, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.4
    letterSpacing: "0.08em"
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  pill: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.secondary}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 20px"
    height: "44px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
    height: "44px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.xl}"
    padding: "24px"
  status-chip:
    backgroundColor: "{colors.primary-soft}"
    textColor: "{colors.secondary}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "4px 10px"
---

# Design System: ClinicOS

## Overview

**Creative North Star: "Ruang Kendali Klinik yang Tenang"**

ClinicOS terasa seperti ruang kendali yang tetap jernih saat klinik sibuk: informasi penting mudah dipindai, tindakan utama tegas, dan status bergerak tanpa membuat layar terasa gaduh. Sistem ini tenang, tepercaya, teratur, dan manusiawi; ekspresinya menghindari kesan steril dari perangkat lunak rumah sakit lama maupun permainan visual berlebihan ala aplikasi startup.

Antarmuka operasional memakai kepadatan yang terkendali, permukaan putih, garis tipis, dan warna teal untuk orientasi serta tindakan. Landing page membawa identitas yang sama dengan skala tipografi dan komposisi yang lebih persuasif, tetapi tidak membentuk palet atau bahasa visual kedua.

**Key Characteristics:**

- Hierarki tegas dengan Plus Jakarta Sans untuk judul dan angka penting.
- Palet teal ClinicOS yang sudah ada, didukung netral hijau-keabu-abuan dan aksen hangat yang hemat.
- Permukaan flat-terkendali dengan border sebagai struktur utama dan bayangan ambient sebagai pengecualian.
- Sudut lembut, target sentuh lapang, dan status yang selalu terbaca melalui teks selain warna.
- Gerak singkat untuk orientasi dan umpan balik, dengan penghormatan penuh pada reduced motion.

## Colors

Palet incumbent ClinicOS memadukan teal yang stabil, netral hangat kehijauan, permukaan putih, dan aksen tembaga yang jarang.

### Primary

- **Primary:** tindakan utama, tautan operasional, fokus, dan angka antrean yang perlu segera dikenali.
- **Primary Hover:** respons hover atau penekanan satu tingkat lebih dalam; bukan warna permukaan luas.

### Secondary

- **Secondary:** label, teks pendukung kuat, navigasi, dan elemen yang perlu otoritas tanpa bersaing dengan tindakan utama.

### Tertiary

- **Accent:** penanda hangat, badge khusus, dan sorotan sekunder dalam jumlah kecil; tidak menggantikan primary untuk CTA.

### Neutral

- **Background:** kanvas aplikasi agar kartu putih mudah terbaca tanpa kontras keras.
- **Surface:** kartu, field, dropdown, dialog, dan area kerja utama.
- **Text:** teks utama dan heading gelap.
- **Border:** pemisah, outline field, dan struktur kartu dalam sistem flat-terkendali.

Overlay foto hero memakai `overlay-strong` (88%) di sisi teks dan `overlay-soft` (58%) di sisi dashboard. Keduanya berasal dari warna `text` yang sama, tanpa hue tambahan.

### Named Rules

**The Existing Palette Rule.** Gunakan hanya palet ClinicOS yang sudah ada untuk elemen merek; jangan memperkenalkan hue merek baru tanpa keputusan eksplisit.

**The One Primary Rule.** Dalam satu kelompok tindakan, hanya satu kontrol yang memakai primary solid; tindakan lain memakai secondary, ghost, atau warna status yang sesuai.

**The Medical Privacy Rule.** Warna tidak pernah menjadi satu-satunya pembeda status, risiko, atau informasi klinis; sertakan teks atau ikon yang jelas.

## Typography

**Display Font:** Plus Jakarta Sans (dengan Inter dan sans-serif sebagai fallback)

**Body Font:** Inter untuk aplikasi; DM Sans untuk copy landing page

**Character:** Plus Jakarta Sans memberi judul dan angka penting rasa percaya diri yang ramah, sementara Inter menjaga UI operasional padat tetap terbaca. DM Sans dipertahankan pada marketing surface agar copy panjang terasa lebih ringan tanpa mengubah suara produk.

### Hierarchy

- **Display:** hero landing dan pernyataan pemasaran utama; berat ekstra dan tracking rapat hanya pada ruang yang cukup.
- **Headline:** judul halaman, heading section utama, dan momen orientasi besar.
- **Title:** judul kartu, panel, dialog, dan kelompok form.
- **Body:** copy operasional, isi field, bantuan, serta data tabel; utamakan keterbacaan daripada ekspresi.
- **Label:** label status, eyebrow, metadata, dan header tabel; uppercase hanya untuk label singkat, bukan kalimat.

### Named Rules

**The Two-Family Rule.** Plus Jakarta Sans memimpin hierarki; Inter memikul pekerjaan operasional. DM Sans hanya hidup pada landing page dan tidak masuk ke layar aplikasi.

**The Number Leads Rule.** Nomor antrean, metrik, dan harga boleh memakai ukuran lebih besar dan tracking lebih rapat karena berfungsi sebagai jangkar pemindaian.

## Layout

Layar aplikasi memakai shell dengan sidebar desktop selebar 18rem, header sticky, dan area konten maksimum 1440px. Padding konten bergerak dari 16px pada mobile ke 24px pada tablet dan 40px pada desktop; grid dua kolom pada mobile dapat berkembang menjadi empat atau lima kolom pada layar lebar.

Landing page memakai container cair dengan batas maksimum 1600px, komposisi hero dua kolom, dan section rhythm yang longgar. Pada 960px navigasi berpindah ke menu mobile dan hero menjadi satu kolom; pada 700px grid fitur serta harga menjadi satu kolom dan ruang vertikal dipadatkan. Konten operasional memprioritaskan scanning, sedangkan landing page memberi lebih banyak ruang untuk persuasi.

**The Task Density Rule.** Kepadatan mengikuti pekerjaan: antrean, tabel, dan dashboard boleh rapat tetapi tidak mengurangi target sentuh minimum 44px; form dan RME memakai ruang antarkelompok yang lebih besar.

## Elevation & Depth

Sistem memakai pendekatan flat-terkendali. Border dan tonal surface membentuk sebagian besar hierarki; bayangan ambient muncul hanya ketika sebuah elemen benar-benar berada di atas lapisan lain atau ketika ilustrasi produk membutuhkan fokus.

### Shadow Vocabulary

- **Card:** bayangan sangat tipis untuk kartu putih di atas background lembut.
- **Elevated:** bayangan lebar untuk modal dan dropdown yang harus terbaca sebagai lapisan terpisah.
- **Marketing Ambient:** bayangan hijau transparan pada ilustrasi dashboard dan CTA landing untuk menambah kedalaman tanpa membuatnya glossy.

### Named Rules

**The Flat-by-Default Rule.** Permukaan operasional tetap rata saat diam; gunakan border atau tonal layer lebih dahulu dan naikkan bayangan hanya untuk overlay, hover penting, atau ilustrasi utama.

## Shapes

Bahasa bentuk memakai sudut lembut dan konsisten: field serta action button umumnya 12px, panel landing sekitar 16px, kartu aplikasi dan modal utama 24px, sementara status chip dan indikator ringkas berbentuk pill. Lingkaran dipakai untuk avatar, dot status, dan dekorasi orbit—bukan sebagai bentuk dasar semua komponen.

**The Radius Hierarchy Rule.** Radius bertambah bersama skala dan kedalaman: kontrol lebih kecil daripada kartu, dan kartu tidak boleh lebih bulat daripada modal yang menaunginya.

## Components

### Buttons

Tindakan harus langsung terbaca dan nyaman disentuh.

- **Shape:** gunakan action button 12px untuk alur operasional; pola pill yang sudah ada dapat dipertahankan pada form lama, tetapi jangan mencampur dua siluet dalam satu kelompok aksi.
- **Primary:** teal solid, teks putih, minimum tinggi 44px, dan font bold.
- **Hover / Focus:** primary menggelap saat hover; semua varian memakai focus ring teal dengan offset yang jelas dan active state yang sangat ringan.
- **Secondary / Danger:** secondary memakai permukaan putih dan border; danger memakai merah hanya untuk tindakan destruktif.

### Chips

- **Style:** pill kecil dengan tonal background, teks bold, dan padding rapat.
- **State:** setiap chip menulis nama status; dot atau perubahan warna hanya memperkuat, tidak menggantikan label.

### Cards / Containers

- **Corner Style:** kartu aplikasi menggunakan radius besar 24px; subpanel di dalamnya turun ke 16px atau 12px.
- **Background:** surface putih di atas background lembut; tonal teal muda dipakai untuk selected, success, atau emphasis.
- **Shadow Strategy:** flat secara default; modal, dropdown, dan kartu marketing tertentu memakai elevation.
- **Border:** satu garis tipis berwarna border atau neutral-200.
- **Internal Padding:** 20–24px untuk kartu operasional dan 24–32px untuk section/form besar.

### Inputs / Fields

- **Style:** surface putih, border tipis, radius 12px, minimum tinggi 44px, dan placeholder netral.
- **Focus:** border dan ring berpindah ke primary tanpa mengubah ukuran field.
- **Error / Disabled:** error memakai border serta ring merah dengan pesan teks; disabled menurunkan kontras dan menampilkan cursor yang sesuai.

### Navigation

Sidebar desktop memakai dark teal untuk jangkar ruang kerja, sedangkan header konten tetap putih translusen. Active state memakai tonal primary dan bobot teks lebih kuat; mobile mengganti sidebar dengan app bar serta navigasi yang tetap mempunyai label jelas.

### Queue Display

Nomor antrean adalah signature component: angka Plus Jakarta Sans berukuran besar, status pill berlabel, dan informasi waktu atau confidence ditempatkan sebagai metadata sekunder. Tampilan publik memakai dark teal dan tidak menampilkan identitas atau data medis pasien.

## Do's and Don'ts

### Do:

- **Do** gunakan token warna, tipografi, radius, dan spacing yang sudah ada sebelum menulis nilai satu kali.
- **Do** prioritaskan satu tindakan utama per kelompok dan berikan target sentuh minimum 44px.
- **Do** gunakan border serta tonal surface sebagai struktur default sebelum menambahkan shadow.
- **Do** pertahankan teks status, focus-visible, dan reduced-motion pada setiap komponen interaktif.
- **Do** bedakan mode Persuade pada landing page dari mode Operate pada aplikasi tanpa memecah identitas merek.

### Don't:

- **Don't** memperkenalkan warna merek baru, gradient dekoratif baru, atau font baru hanya untuk membuat layar terasa berbeda.
- **Don't** memenuhi layar operasional dengan kartu mengambang dan bayangan berlapis.
- **Don't** mengandalkan warna, hover, atau animasi sebagai satu-satunya cara memahami status dan tindakan.
- **Don't** mencampur radius pill dan rounded action button dalam kelompok tindakan yang sama.
- **Don't** mengubah landing page menjadi standar visual bagi kepadatan dashboard, RME, atau layar antrean.
