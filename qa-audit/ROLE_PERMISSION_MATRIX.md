# Role × feature × action

> **Status terbaru setelah P2, 11 November 2026:** Tidak menambah nama role/permission. Create staf sekarang memeriksa permission sesuai role target; self-delete melihat referensi aktual; perubahan sesi berlaku pada web guard semua role. Matrix policy dan HEAD boundary lima role PASS. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

Audit 11 November 2026. Lima role dan 40 permission diekspor dari database demo terisolasi setelah RoleAndPermissionSeeder. [roles-permissions.json](evidence/roles-permissions.json) adalah bukti grant aktual, bukan bukti setiap resource sudah diuji.

Owner bukan role keenam. Kolom Owner di bawah memakai role Clinic Admin ditambah tipe akun dan kepemilikan klinik. Semua akses medis tetap memerlukan Doctor yang ditugaskan. Pasien hanya mendapat tampilan resep final miliknya melalui dashboard, bukan editor/isi RME umum.

## Aksi dan batas resource

| Fitur/aksi | Guest | Patient | Receptionist | Doctor | Clinic Admin | Owner | Platform Admin |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Klinik publik/display antrean nonmedis | Baca | Baca | Baca | Baca | Baca | Baca | Baca |
| Buat booking online | Login dahulu | Klinik aktif, kuota | Ditolak | Ditolak | Ditolak | Ditolak | Ditolak |
| Status/cancel token legacy | Token sah | Token sah | Token sah | Token sah | Token sah | Token sah | Token sah |
| List/detail/cancel appointment pasien | Ditolak | Kepemilikan patient.user_id | Ditolak di portal pasien | Ditolak di portal pasien | Ditolak di portal pasien | Ditolak di portal pasien | Ditolak |
| Pasien administratif create/read/update/search | Ditolak | Ditolak | Tenant sendiri | Tidak ada route administratif | Tenant sendiri | Tenant sendiri | Ditolak |
| Booking staf create/read/cancel | Ditolak | Ditolak | Tenant sendiri | Ditolak di route staf | Tenant sendiri | Tenant sendiri | Ditolak |
| List antrean internal | Ditolak | Ditolak | Klinik sendiri | Assignment sendiri | Klinik sendiri | Klinik sendiri | Ditolak |
| Check-in/cancel/no-show | Ditolak | Ditolak | Tenant + queue.manage | Ditolak | Tenant + queue.manage | Sama admin | Ditolak |
| Call/skip/return | Ditolak | Ditolak | Tenant + queue.call | Dokter ditugaskan | Tenant + queue.call | Sama admin | Ditolak |
| Start visit, draft/autosave, finalize/correct | Ditolak | Ditolak | Ditolak | Assignment + permission + status | Ditolak | Ditolak | Ditolak |
| Baca RME/foto/odontogram klinis | Ditolak | Ditolak melalui route dokter | Ditolak | Assigned Doctor | Ditolak | Ditolak | Ditolak |
| Baca resep final dashboard pasien | Ditolak | Hanya resep sendiri | Ditolak | Tidak di portal pasien | Ditolak | Ditolak | Ditolak |
| Edit odontogram | Ditolak | Ditolak | Ditolak | Assignment + paid addon + workflow | Ditolak | Ditolak | Ditolak |
| Alergi pasien create/update | Ditolak | Ditolak | Ditolak | Visit aktif ditugaskan | Ditolak | Ditolak | Ditolak |
| Layanan/staf/jadwal create/update/nonaktif | Ditolak | Ditolak | Tidak ada route edit | Tidak ada route edit | Tenant + permission | Sama admin | Ditolak |
| Audit klinik read/filter | Ditolak | Ditolak | Ditolak | Ditolak | Klinik sendiri | Klinik sendiri | Melalui metadata/platform, bukan route admin |
| Notifikasi read/mark read | Ditolak | Milik sendiri | Milik sendiri | Milik sendiri | Milik sendiri | Milik sendiri | Milik sendiri |
| Subscription/add-on/invoice owner | Ditolak | Ditolak | Ditolak | Ditolak | Ditolak bila bukan owner | Klinik owner; QA-07 sebelum klinik | Melalui route platform |
| Katalog platform/grant/refund/reconcile | Ditolak | Ditolak | Ditolak | Ditolak | Ditolak | Ditolak | Permission per aksi |
| Ubah role/permission lewat UI | Tidak ada | Tidak ada | Tidak ada | Tidak ada | Tidak ada | Tidak ada | Tidak ada |
| Hapus akun sendiri | Tidak | Current password | Current password | Current password; FK bisa membatasi | Current password | FAIL QA-03 | Current password; lifecycle khusus belum diuji |

Token sah adalah capability terpisah dari role. Akses token tetap mengikuti subscription **resource target**, bukan klinik akun login. Read-only subscription tidak memberikan izin baca baru; policy tetap berlaku. Penyelesaian visit IN_PROGRESS dan deaktivasi seat oleh owner merupakan pengecualian tulis yang dipertahankan.

## Bukti enforcement

- PASS: lima role login, semua link menu (29 kunjungan), 20 akses dashboard role asing ditolak 403, logout dan pencabutan sesi akun nonaktif pada lima role; lihat interactive-browser.json.
- PASS: regression audit mengeksekusi 13 endpoint operasional × 5 role = 65 kombinasi allowed/denied. Ini mencakup route nyata, bukan hanya visibilitas menu.
- PASS: existing AppointmentAuthorizationTest, PublicCancellationTest, MedicalRecordAccessTest, PlatformAccessTest, SubscriptionEnforcementTest, dan OdontogramAddonTest, termasuk ownership, tenant, token, dan entitlement negatif.
- PASS: perubahan role Doctor menjadi Receptionist pada database fixture saat sesi masih aktif membuat request dokter berikutnya ditolak 403. Tidak ada UI perubahan role yang diklaim.
- PASS: request cross-site tanpa token dengan cookie sesi ditolak 419. Request same-origin dapat melewati pemeriksaan origin Laravel 13; itu bukan bypass CSRF lintas situs.
- NOT TESTED: seluruh Cartesian role × ID resource × status × HTTP method; pencabutan subscription/role pada koneksi WebSocket yang sudah tersambung; semua kombinasi multi-role; seluruh permission individual yang dicabut satu per satu.

## Permission tanpa aksi UI khusus

`doctor.delete` dan `service.delete` tidak mempunyai endpoint destroy; lifecycle memakai nonaktif. `appointment.update` tidak menyediakan reschedule. Beberapa grant baca dokter/resepsionis diperoleh melalui dashboard/publik, bukan route katalog admin. Ini tidak membenarkan membuka endpoint admin kepada role tersebut. Policy medis tetap mensyaratkan Doctor meskipun permission medis diberikan langsung kepada role lain.

## Grant aktual per permission

Tabel berikut berasal dari database seed. “Ya/Tidak” adalah grant role. Authorization akhir tetap memakai tenant, ownership, lifecycle, dan entitlement pada tabel di atas.


| Permission | Clinic Admin | Receptionist | Doctor | Patient | Platform Admin |
| --- | --- | --- | --- | --- | --- |
| appointment.cancel | Ya | Ya | Tidak | Ya | Tidak |
| appointment.create | Ya | Ya | Tidak | Ya | Tidak |
| appointment.update | Ya | Ya | Tidak | Tidak | Tidak |
| appointment.view | Ya | Ya | Ya | Ya | Tidak |
| audit_log.view | Ya | Tidak | Tidak | Tidak | Tidak |
| clinic.update | Ya | Tidak | Tidak | Tidak | Tidak |
| clinic.view | Ya | Tidak | Tidak | Tidak | Tidak |
| dashboard.view | Ya | Ya | Ya | Ya | Tidak |
| doctor.create | Ya | Tidak | Tidak | Tidak | Tidak |
| doctor.delete | Ya | Tidak | Tidak | Tidak | Tidak |
| doctor.update | Ya | Tidak | Tidak | Tidak | Tidak |
| doctor.view | Ya | Ya | Ya | Ya | Tidak |
| medical_record.create | Tidak | Tidak | Ya | Tidak | Tidak |
| medical_record.update | Tidak | Tidak | Ya | Tidak | Tidak |
| medical_record.view | Tidak | Tidak | Ya | Tidak | Tidak |
| patient.create | Ya | Ya | Tidak | Tidak | Tidak |
| patient.update | Ya | Ya | Tidak | Tidak | Tidak |
| patient.view | Ya | Ya | Ya | Tidak | Tidak |
| platform.dashboard.view | Tidak | Tidak | Tidak | Tidak | Ya |
| platform.payment.manage | Tidak | Tidak | Tidak | Tidak | Ya |
| platform.payment.view | Tidak | Tidak | Tidak | Tidak | Ya |
| platform.plan.manage | Tidak | Tidak | Tidak | Tidak | Ya |
| platform.subscription.grant | Tidak | Tidak | Tidak | Tidak | Ya |
| platform.tenant.view | Tidak | Tidak | Tidak | Tidak | Ya |
| queue.call | Ya | Ya | Ya | Tidak | Tidak |
| queue.manage | Ya | Ya | Tidak | Tidak | Tidak |
| queue.view | Ya | Ya | Ya | Ya | Tidak |
| receptionist.create | Ya | Tidak | Tidak | Tidak | Tidak |
| receptionist.update | Ya | Tidak | Tidak | Tidak | Tidak |
| receptionist.view | Ya | Tidak | Tidak | Tidak | Tidak |
| revenue.view | Ya | Tidak | Tidak | Tidak | Tidak |
| schedule.manage | Ya | Tidak | Tidak | Tidak | Tidak |
| schedule.view | Ya | Ya | Ya | Ya | Tidak |
| service.create | Ya | Tidak | Tidak | Tidak | Tidak |
| service.delete | Ya | Tidak | Tidak | Tidak | Tidak |
| service.update | Ya | Tidak | Tidak | Tidak | Tidak |
| service.view | Ya | Ya | Ya | Ya | Tidak |
| visit.complete | Tidak | Tidak | Ya | Tidak | Tidak |
| visit.start | Tidak | Tidak | Ya | Tidak | Tidak |
| visit.view | Ya | Ya | Ya | Tidak | Tidak |

