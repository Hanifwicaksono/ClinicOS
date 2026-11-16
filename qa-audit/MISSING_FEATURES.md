# Missing dan partial features

> **Status terbaru setelah P2, 11 November 2026:** Bugfix tidak menutup backlog fitur: kasir/billing pasien, discovery, multi-cabang, invitation/permission editor dan Resilient Edge Mode belum diimplementasikan. Bedakan feature gap dari 15 bug terkonfirmasi yang sudah FIXED. Lihat [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md) dan [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md).

## Riwayat laporan sebelum perbaikan P2

Bagian di bawah mempertahankan checkpoint P1 dan baseline audit; status/angka historis tidak menggantikan hasil terbaru di atas.

> **Pembaruan setelah perbaikan P1, 11 November 2026:** QA-01 - QA-04 sudah FIXED pada pengujian ulang; P2 belum diperbaiki. Hasil terbaru: [P1_FIX_REPORT.md](P1_FIX_REPORT.md), [STAFF_LIFECYCLE_REPORT.md](STAFF_LIFECYCLE_REPORT.md), [FINAL_QA_REPORT.md](FINAL_QA_REPORT.md). Dua bug staf baru QA-13/QA-14 dan risiko sesi setelah reset admin tetap terbuka.

## Baseline audit sebelum perbaikan

Bagian berikut mempertahankan hasil/source yang diamati saat audit awal. Angka, status FAIL P1 dan batas pada baseline ini bukan status terbaru; bukti lama tidak ditimpa.

Audit 11 November 2026. Ketiadaan fitur dibedakan dari bug pada fitur yang tersedia. Requirement dari PRD/Plan lama, Development-Plan terbaru, dan design contract belum sepenuhnya sinkron; temuan berikut bukan instruksi memperluas scope atau mengganti stack.

| Gap | Backend/database/UI yang ditemukan | Hasil pemeriksaan | Dampak dan sumber |
| --- | --- | --- | --- |
| Booking online dapat diselesaikan manusia | Backend transaksi, route, form dan helper ada; bootstrap interaksi publik tidak ada | FAIL QA-01 | Patient tidak bisa memilih dokter; ini bug integrasi, bukan backend booking belum dibuat |
| Foto histori final dapat dibaca kembali | Revision menyimpan path; endpoint hanya foto terkini; file removed dihapus | FAIL QA-02 | Histori berkas tidak utuh meskipun snapshot ada |
| Penutupan/transfer/recovery owner | Generic self-delete ada; owner nullable; tidak ada route transfer/closure/recovery | FAIL QA-03 | Tenant kehilangan pengelola komersial; bukan alasan menghapus histori |
| Reassignment/reschedule sesi terpakai yang aman | Form edit dokter/jam/sesi ada; appointment/queue tidak disinkronkan | FAIL QA-04 untuk penggantian dokter | Perlu aturan blokir/pemindahan; seluruh perubahan jam belum diuji |
| Kontrak versi wajib di seluruh write RME | Counter dan pengecekan 409 sudah ada; versi opsional di sebagian request | FAIL QA-05 | Request tanpa field bisa melewati perlindungan konflik |
| Billing/kasir pembayaran layanan pasien | Visit menyimpan nilai layanan; Payment/PaymentItem untuk subscription SaaS | FAIL terhadap cakupan MF-5; domain tidak ditemukan | Tidak ada pembayaran pasien, receipt, piutang, atau rekonsiliasi kas; sumber requirement PRD/RPP historis |
| Export laporan/receipt/cetak resep | Ringkasan dashboard dan audit tersedia; tidak ditemukan export CSV/Excel/PDF/print workflow lengkap | FAIL terhadap cakupan laporan/design | Tidak boleh menyamakan tabel dashboard dengan laporan siap ekspor |
| Discovery klinik dari portal pasien | Subdomain klinik dan tautan booking per klinik ada; dashboard pasien hanya own booking/profil/resep | FAIL terhadap cakupan discovery | Pasien perlu mengetahui tautan klinik; tidak ada pencarian/directory lintas klinik |
| Multi-cabang/multi-klinik per owner | Satu owner satu clinic, satu subscription; tidak ada organization/branch/context switch | FAIL terhadap cakupan multi-cabang historis | Batas produk saat ini, bukan regresi yang harus otomatis diimplementasikan |
| Resilient Edge Mode | Browser reconnect/polling dan autosave retry ada; tidak ada Local API/DB/sync agent/outbox perangkat | FAIL terhadap cakupan Edge historis | Retry browser tidak membuat aplikasi dapat melayani seluruh alur saat WAN putus |
| Entitlement premium realtime/management_dashboard | Flag ada di SubscriptionPlanSeeder; jalur dashboard/channel belum menggunakan gate fitur itu | FAIL untuk keterhubungan flag ke enforcement | Kapasitas dan add-on mempunyai guard; keputusan gate fitur lain perlu diselaraskan sebelum menyebut paket berbeda fitur |
| Suspension/unsuspension dan pemulihan tenant lewat platform | Status Suspended dipahami service; dashboard/grant/downgrade ada; aksi suspend/recovery owner tidak ditemukan | FAIL untuk alur pengelolaan lengkap | Metadata status tanpa tindakan UI operasional |
| Edit role/permission umum | Role/permission seeded, policy tersedia; form staf membatasi Doctor/Receptionist | NOT TESTED sebagai UI karena UI tidak tersedia | Permission matrix bukan halaman editor akses. Jangan menambah bypass medis untuk mengisi gap ini |
| Appointment reschedule | Permission appointment.update ada; route UI hanya create/list/detail/cancel | NOT TESTED sebagai fitur karena tidak ditemukan action | Perubahan appointment bukan CRUD lengkap; perlu keputusan requirement sebelum menambah |
| Katalog obat/interaksi obat | Resep free text dan acknowledgement alergi tersedia | NOT TESTED sebagai engine karena tidak ada domain | Tidak ada klaim sistem sudah memvalidasi keselamatan kombinasi obat |
| Reminder kunjungan/near-turn pasien | Status booking/queue notifikasi ada; scheduler hanya reminder subscription | FAIL terhadap requirement NOTIF-3 | Belum ditemukan reminder kunjungan terjadwal/near-turn yang terpisah dari panggilan |
| Ranking layanan terpopuler | Dashboard mengagregasi nilai/kunjungan; tidak ada ranking layanan | FAIL terhadap PRD DASH-7 | Metric agregat bukan ranking |
| Rekonsiliasi pembayaran terjadwal | Tombol owner/platform dan service tersedia; scheduler hanya subscription reminder | NOT TESTED sebagai scheduled workflow karena tidak ada | Pemulihan pembayaran bergantung notification atau tindakan manual |
| Prasyarat add-on sebelum tenant | Route owner tersedia tanpa onboarding guard | FAIL QA-07 | Bookmark/URL langsung menghasilkan 500 |
| Delivery/infrastruktur nyata | Config Reverb, job, scheduler, SMTP, DOKU, Google dan deploy tersedia | BLOCKED untuk pembuktian operasional | Config dan fake bukan bukti proses/service berjalan |

## Kesenjangan historis yang sudah ditutup

PASS pada scope test saat ini: persistence odontogram, domain alergi, acknowledgement, autosave jaringan dengan retry, record version untuk stale request yang mengirimkannya, editor diagnosis/tindakan terstruktur, ETA service/queue tracker, katalog harga platform, dan dashboard resep final pasien. Laporan 24/25 Oktober dan README fitur 22 Oktober menggambarkan kondisi lama; tidak dipakai untuk menyatakan fitur tersebut masih belum ada.

Autopersist foto tetap melalui submit manual, sesuai shortcut yang tercatat pada rme-autosave.js. Ini keputusan implementasi yang jelas, bukan bug autosave, selama UI menyatakan foto belum disimpan dan alur manual berfungsi. Retensi foto dan rollback filesystem adalah bug terpisah.
