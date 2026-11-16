# Security dan data isolation audit

> **Status terbaru setelah P2, 11 November 2026:** QA-13/QA-14 FIXED, sesi/remember lama ditolak setelah reset, email baru wajib verifikasi dan tautan lama ditolak. Composer/npm audit 0 setelah lima patch 7.4; seluruh PHP/Node/Chrome dan HEAD boundary PASS. Provider nyata, concurrency, socket aktif dan pentest menyeluruh belum dibuktikan. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Hasil keamanan pengujian ulang

- Foto histori tetap private, memakai policy visit dan RME; dokter lain/tenant lain dan empat role nonmedis ditolak. Revision harus milik record tersebut. File yang dihapus sebelum fix tidak dipulihkan tanpa backup.
- Self-delete owner ditolak berdasarkan relasi kepemilikan aktual, termasuk akun legacy; owner tetap login setelah penolakan.
- Perubahan permission/role pada fixture ditolak pada request staf berikut; penonaktifan lewat UI memutus sesi dan menolak login baru.
- QA-13 masih OPEN: permission create tidak dibedakan menurut role target. QA-14 masih OPEN: self-delete dokter berhistori menimbulkan error FK. Kedua regression tetap merah.
- Reset password admin belum mencabut sesi aktif; browser kedua role masih mengembalikan 200 dengan cookie lama. Ini risiko/kebijakan sesi terpisah yang belum ditutup.
- Advisory dependency pada baseline belum diselesaikan atau diukur ulang dengan upgrade. Tidak ada perubahan dependency dalam pekerjaan P1.

Rincian scope dan bukti: [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [P1_FIX_REPORT.md](P1_FIX_REPORT.md).

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

11 November 2026. Pengujian terarah lokal/testing, dengan data sintetis. Tidak ada pembayaran nyata, pesan eksternal, pembacaan .env, akses data pasien production, atau perubahan source produksi. Hasil ini tidak merupakan pentest menyeluruh atau sertifikasi kepatuhan.

## Kontrol yang diuji

| Kontrol | Status | Bukti dan batas |
| --- | --- | --- |
| Pemisahan portal Patient/pengelola dan role/permission backend | PASS pada kasus yang dijalankan | AuthenticationTest, DashboardAccessTest, 65 kombinasi endpoint audit, 20 dashboard denial browser |
| Login rate limit, inactive/remember me | PASS feature dan inactive browser | AccountSecurityTest; rate limit lintas worker/storage nyata NOT TESTED |
| Role berubah saat sesi berjalan | PASS pada request berikutnya | Browser Doctor→Receptionist menghasilkan 403; socket aktif BLOCKED |
| Ownership appointment pasien, termasuk Patient ber-clinic_id/multi-role terpilih | PASS | AppointmentAuthorizationTest; perbaikan AUD-01 historis tetap lulus |
| Subscription target pada cancel publik | PASS | PublicCancellationTest guest/lintas akun/expired/suspended/over-seat; perbaikan AUD-02 historis tetap lulus |
| Medis hanya dokter ditugaskan; owner/platform tidak memperoleh editor | PASS kasus terpilih | MedicalRecordAccessTest, RmeGeneralDentalTest, OdontogramAddonTest; route binding dilanjut policy |
| Isi resep pasien | PASS pembatasan khusus | PatientPrescriptionDashboardTest: final milik sendiri, bukan SOAP/record umum |
| CSRF/origin | PASS probe cross-site | Cookie sesi tanpa token dan Sec-Fetch-Site cross-site ditolak 419. Same-origin 422 adalah validasi bisnis setelah origin check Laravel 13 |
| Password reset/verification/Google account linking | PASS fake/backend | Test valid/invalid token dan verified Google email; provider/inbox nyata BLOCKED |
| Mass assignment/input tenant | PASS pada boundary yang diuji | Form Request whitelists, server clinic context, foreign-resource tests; bukan audit semua payload kombinatorial |
| Private medical photos/public logo | PASS policy/storage normal; FAIL integrity | VisitWorkflowTest foto private; QA-02 menghapus histori, QA-08 orphan rollback |
| Public queue dan broadcast tidak memuat identitas/medis | PASS payload/HTML yang diuji | QueueRealtimeTest/PublicQueueComponentsTest/ClinicNotificationDeliveryTest; payload publik nomor/status/doctor/date/update |
| DOKU signature, amount, invoice, request, tenant, dedupe, redirect | PASS dengan fake | DokuPaymentTest/OdontogramAddonTest; tidak ada aktivasi dari redirect browser |
| Sensitive metadata/audit | PASS kasus yang diuji | SubscriptionHardeningTest, AuthenticationAuditTest; trace audit sendiri tidak menyimpan URL/token/body |
| Output escaping/security headers | PASS contoh yang diuji | DashboardAccessTest nama HTML, SecurityHeadersTest; bukan seluruh template fuzz |
| Optimistic concurrency server RME | FAIL | QA-05: correction tanpa versi diterima; versi yang dikirim tetap dibandingkan |
| Validasi nullable vs service | FAIL | QA-06: request valid menurut rules dapat menghasilkan 500; tidak dibuktikan sebagai kebocoran isi medis |
| Account lifecycle owner | FAIL | QA-03: klinik tanpa owner; recovery/transfer tidak tersedia |
| Dependency scan | FAIL Composer, PASS npm | 8 advisory/5 paket Composer; npm 0 advisory pada waktu audit |

ClinicScope bukan isolasi universal: hanya Patient dan bergantung pada auth clinic context. Query tenant, relasi, policy, dan service perlu tetap diperiksa per jalur. QA-04 membuktikan konsistensi relasi dapat rusak walaupun semua ID ada dan berada dalam klinik sama. Tidak ada klaim semua endpoint bocor atau semua IDOR sudah dieliminasi.

## Advisory terverifikasi (QA-11)

Feed Composer dan halaman upstream resmi diperiksa pada sesi ini. Satu advisory bukan bukti jalur eksploit aktif di Laravel. Tabel menunjukkan nama masalah secara ringkas; rincian versi/rentang lengkap tersimpan pada composer-audit.json.

| Paket | Advisory | Relevansi dan batas |
| --- | --- | --- |
| symfony/http-foundation | [CVE-2026-118514](https://symfony.com/cve-2026-118514) | Matcher HEAD/GET; 7.4.20 masuk rentang. ClinicOS memakai middleware Laravel/Spatie; bypass route aplikasi tidak dibuktikan |
| symfony/http-foundation | [GHSA-3h27-hjv7-fqjx](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx) | Perbedaan path depan PHP; konfigurasi reverse proxy/production belum diperiksa |
| symfony/http-foundation | [GHSA-xg2q-4rrq-657q](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q) | Shortcut private_ranges; konfigurasi aplikasi memakai loopback eksplisit, sehingga shortcut terdampak tidak ditemukan dipakai |
| symfony/http-foundation | [GHSA-5mx8-mwrp-5693](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693) | Host pada URL generation; trusted-host/proxy deployment dan jalur eksploit spesifik belum diuji |
| symfony/http-kernel | [CVE-2026-123339 / GHSA-wg5q-m2ww-f3c9](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9) | Inline fragment/forwarded headers; pemakaian fragment yang terdampak belum ditemukan pada alur yang ditelusuri |
| symfony/mime | [CVE-2026-45070](https://symfony.com/cve-2026-45070) | Nama parameter MIME; 7.4.19 masuk rentang, tetapi kontrol nama parameter oleh input pengguna tidak dibuktikan |
| symfony/routing | [CVE-2026-118516](https://symfony.com/cve-2026-118516) | Host placeholder pada compiled matcher; aplikasi mempunyai subdomain route, namun bypass policy Laravel tidak dibuktikan |
| symfony/yaml | [CVE-2026-123792 / GHSA-hwfg-qwvh-2wfx](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx) | Beban parser; endpoint yang menerima YAML tidak ditemukan |

Upstream menyebut perbaikan 7.4.23 untuk advisory Foundation/Routing November dan 7.4.22 untuk MIME tersebut. Ini acuan review dependency kompatibel, bukan instruksi update tanpa persetujuan. Paket/lock tetap sama pada audit.

## Yang belum dibuktikan

BLOCKED: concurrency/lock MySQL, Reverb nyata dan pencabutan koneksi, DOKU sandbox nyata, Google nyata, delivery SMTP, staging/production dan backup restore. NOT TESTED: semua permission dicabut satu per satu pada seluruh resource/status, upload polyglot/fuzz, seluruh vector XSS/CSRF/host/proxy, traffic/load/rate limit multi-worker, file retention dan log redaction pada deployment, serta audit storage append-only/retensi.

Tidak ditemukan bukti kebocoran medis lintas tenant baru pada skenario yang dieksekusi. Itu bukan pernyataan seluruh sistem bebas kebocoran. Prioritas integritas adalah QA-02/03/04/05/08, disusul perbaikan boundary input dan dependency review.
