# Prioritas implementasi ClinicOS

Tanggal: 14 November 2026. Status: **RENCANA, belum implementasi**. Dasar: seluruh 11 laporan [Product Completeness Audit](../FINAL_PRODUCT_AUDIT.md), terutama [18 gap kanonis](../MISSING_FEATURES_REPORT.md), [keputusan](../PRODUCT_DECISIONS_REQUIRED.md), [backlog](../PRIORITIZED_BACKLOG.md), dan [roadmap](../DEVELOPMENT_ROADMAP.md). Tidak menjalankan audit/browser/suite aplikasi ulang atau membuka database pada tahap ini.

Target delivery yang diusulkan: setup dan ketersediaan → booking/perubahan booking → kedatangan/antrean → pelayanan atau penyelesaian interupsi yang jujur → hasil/instruksi → follow-up tertaut. Administrasi komersial dan revokasi akses harus mendukung alur tersebut tanpa memberi akses medis kepada owner/admin/platform. Kasir tetap di luar scope sampai D-05 disetujui.

## Arti kesiapan

- **SIAP**: cukup jelas untuk diimplementasikan pada instruksi implementasi berikutnya, tanpa keputusan aturan bisnis tambahan. Bukan izin mulai mengubah kode pada sesi perencanaan ini.
- **SIAP SPIKE**: siap untuk pembuktian teknis terbatas pada fixture terisolasi saat implementasi diizinkan; belum merupakan solusi yang terverifikasi.
- **MENUNGGU KEPUTUSAN**: pilihan/otoritas/AC bisnis harus disetujui lebih dulu. Draft rekomendasi tidak dianggap persetujuan.
- **DITAHAN SCOPE**: tidak dijadwalkan otomatis. Keberadaannya dalam audit bukan kewajiban delivery.

## Klasifikasi seluruh ID asli

Kategori utama saling eksklusif agar tetap **18 gap**, sementara kolom gate menunjukkan keputusan yang melintasi kategori. Fase/subtugas bukan ID temuan baru. Prioritas P1/P2 dari audit dipertahankan: **9 P1, 9 P2, tidak ada P0 baru**. PR-01 tetap risiko keamanan existing di luar hitungan 18.

| ID | Kebutuhan asli | Kelompok utama | Prioritas audit / ukuran relatif | Kesiapan dan gate | Fase |
| --- | --- | --- | --- | --- | --- |
| PG-01 | Perubahan booking terkonfirmasi sebelum pemeriksaan | Workflow inti belum lengkap | P1 / L | MENUNGGU D-01; D-06 berlaku untuk pasien nonaktif | F4 |
| PG-02 | Ketersediaan berdasarkan tanggal dan dampak perubahan jam | Workflow inti belum lengkap | P1 / M–L | Preview read-only dapat dipisahkan; mutasi exception menunggu D-02, penyelesaian booking memakai PG-01 | F2B, F3B, F4 |
| PG-03 | Penghentian dan serah terima pemeriksaan aktif | Workflow inti belum lengkap | P1 / L–XL | MENUNGGU D-03A/B; akses, state dan kuota continuation tidak boleh diasumsikan | F5A/B |
| PG-04 | Tindak lanjut klinis menjadi pekerjaan yang dapat diselesaikan | Fitur baru untuk operasional klinis | P2 / M | MENUNGGU D-04; tidak otomatis menyalin SOAP ke staf/pasien | F6B |
| PG-05 | Penyelesaian tagihan layanan pasien dan bukti bayar | Fitur opsional terhadap scope klinis saat ini | P1 bersyarat / XL | DITAHAN SCOPE D-05; tidak ada rancangan tabel/implementasi kasir aktif | O1 |
| PG-06 | Makna pasien nonaktif konsisten antar kanal | Workflow inti belum lengkap | P1 / S–M | MENUNGGU D-06; belum boleh disebut bug dengan aturan yang dikarang | F3A |
| PG-07 | Dokumen pasien yang disetujui: resep / ringkasan kunjungan | Fitur baru untuk operasional klinis | P1 bersyarat / M | MENUNGGU D-07; print resep minimum direkomendasikan, bukan full SOAP | F6A |
| PG-08 | Laporan operasional yang dapat direkonsiliasi dan dibawa keluar | Peningkatan UX/informasi | P2 / M | MENUNGGU D-08; service value bukan kas | F8A |
| PG-09 | Timeline histori komersial lintas periode | Peningkatan UX/informasi | P2 / M | Teknis siap dirancang tanpa schema baru; MENUNGGU D-09 field/role | F2A |
| PG-10 | Koreksi grant tertentu dan rekonsiliasi refund/akses | Keputusan produk untuk recovery komersial | P1 / L | MENUNGGU D-10 dan viewer PG-09 | F7B |
| PG-11 | Lifecycle tenant dan kepemilikan yang dapat dikelola | Fitur opsional/bersyarat lifecycle | P2 / L | DITAHAN D-11; aktivasi klinik existing tetap tersedia | O2 |
| PG-12 | Informasi mendekati giliran dan reminder janji | Fitur baru untuk operasional klinis | P2 / M | MENUNGGU D-12 threshold/kanal; tidak menambah WhatsApp/provider otomatis | F8B |
| PG-13 | Resolusi versi alergi dari dua editor | Workflow inti belum lengkap | P1 / M–L | MENUNGGU D-13; data lama tidak digabung otomatis | F5C |
| PG-14 | Diagnosis dan pemulihan data legacy tidak konsisten | Keputusan produk untuk pemulihan data | P1 bersyarat / M diagnosis, L repair | Detektor fixture terpisah; MENUNGGU D-14 sebelum pemeriksaan data nyata atau repair | F7A/C |
| PG-15 | Serah-terima akses staf dan delegasi admin | Fitur opsional/bersyarat onboarding | P2 / M | DITAHAN D-15; invitation dan delegasi diputuskan terpisah | O3 |
| PG-16 | Kontrak paket sesuai pembatasan yang benar-benar berlaku | Keputusan produk komersial | P2 / S copy, M enforcement | MENUNGGU D-16; tidak mematikan fitur Free secara otomatis | O4 |
| PG-17 | Kandidat pasien lebih dari delapan mempunyai jalur lanjutan | Peningkatan UX | P2 / S–M | SIAP: hasil lanjutan dengan matcher existing, tanpa merge/account linking | F1B |
| PG-18 | Feedback submit pada form grant Platform Admin | Bug existing harus diperbaiki | P2 / S | SIAP: perbaikan binding dan feedback saja, UUID/dedup tetap | F1A |

Ringkasan kategori utama: **1 bug, 5 workflow inti, 3 fitur klinis, 3 UX/informasi, 3 opsional/bersyarat, 3 keputusan recovery/komersial**. Banyak item di semua kelompok tetap mempunyai gate keputusan.

Kiosk, multi-cabang, permission editor, discovery/Edge dan domain lain pada D-17/18/19 bukan PG tambahan dan tidak masuk implementasi otomatis. PG-05 tetap dicatat agar tidak menghilang dari 18 gap, tetapi scope kasir tidak disetujui oleh permintaan ini.

## Urutan rekomendasi

| Urut | Pekerjaan | Alasan dampak/dependensi | Hasil yang dapat diuji sendiri |
| --- | --- | --- | --- |
| 1 | F1A PG-18; F1B PG-17 | Bug pasti dan recovery UX kecil tanpa aturan klinis baru | Tombol pending benar; kandidat ke-9/10 dapat dicapai tanpa input hilang |
| 2 | S1/S2 PR-01; pembahasan D-01/02/03/06/07/10/13 | Revokasi keamanan tidak menunggu fitur permission editor; keputusan klinis membuka pekerjaan besar | Spike protokol lalu bukti socket lama terputus/ditolak server, tanpa kehilangan editor |
| 3 | F2A PG-09 dan F2B preview dampak PG-02 | Read-only membantu keputusan sebelum mutasi; tidak harus menunggu reschedule | Timeline benar; daftar booking terdampak tanpa perubahan appointment |
| 4 | F3A PG-06; F3B kalender PG-02 | Menutup kebijakan kanal dan mencegah booking baru saat dokter/klinik berhalangan | Predicate eligibility tunggal; exception menghalangi booking baru saja |
| 5 | F4 PG-01 + penyelesaian kasus dokter absen PG-02 | Handoff operasional paling sering perlu tindakan manual; bergantung jadwal/identitas yang konsisten | Cancel-and-rebook terpandu atomik dengan tautan histori; kegagalan mempertahankan booking lama |
| 6 | F5A/B PG-03; F5C PG-13 | Recovery abnormal dan interpretasi data klinis, risiko perubahan akses/versi tinggi | Pause/resume/interrupted dan handover terpilih; konflik alergi tidak ditimpa |
| 7 | F6A PG-07; F6B PG-04 | Hasil pelayanan dapat dipakai dan kontrol tidak terputus | Dokumen final terbit; kontrol berakhir pada visit terkait atau pembatalan eksplisit |
| 8 | F7A/C PG-14; F7B PG-10 setelah PG-09 | Diagnosis boleh lebih awal; mutasi komersial menunggu viewer dan kebijakan | Dry-run tanpa writes; revoke satu grant tanpa mengubah periode sah lainnya |
| 9 | F8A PG-08; F8B PG-12 | Pengelolaan dan komunikasi lebih efisien setelah state utama stabil | Laporan nonkas cocok sumber; reminder tidak basi/duplikat |
| 10 | O1–O4 hanya jika dipilih | Billing/lifecycle/invitation/packaging dapat mengubah scope/kontrak | AC khusus dan regression sebelum setiap perluasan |

Urutan bukan keharusan mengerjakan semua fase secara serial. F2A, F5C dan F7A fixture dapat maju sendiri setelah gate masing-masing, tanpa menunggu seluruh jalur booking. Tidak membenarkan memulai mutasi yang masih menunggu keputusan.

## Perlindungan baseline

Semua perbaikan QA, DFU-01–12 dan CCL-01–08 yang VERIFIED tetap acceptance invariant. Bukti historis CCL: PHP402, JS15, Playwright28 dan Chrome2 workflow lulus; **bukan run tahap perencanaan**. PR-01/PG-18 tetap OPEN sampai implementasi dan bukti run baru tersedia. Tidak menaikkan severity historis PR-01 P2/Medium menjadi P0 tanpa bukti baru; urutan awalnya ditentukan boundary keamanan.

Setiap paket harus punya test backend/browser yang relevan, fixture eksplisit per klinik, dampak enum/policy/notifikasi ditelusuri, dan laporan hasil tersendiri. Tidak menambah dependency, React/SPA, production-readiness, load/stress, commit atau deploy sebagai bagian rencana ini.

Navigasi: [spesifikasi](FEATURE_SPECIFICATIONS.md), [UI dan state](WORKFLOW_DESIGN.md), [database](DATABASE_IMPACT.md), [dependensi](DEPENDENCY_MAP.md), [fase mandiri](IMPLEMENTATION_PHASES.md), [keputusan](DECISIONS_REQUIRED.md).
