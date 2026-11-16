# Pemeriksaan ulang siklus staf - setelah P2

11 November 2026. Current source dan bukti terakhir: [P2_RETEST_REPORT.md](P2_RETEST_REPORT.md), [PHP](evidence/p2-retest/php-all.txt), [Chrome](evidence/p2-retest/p1-browser-steps.json). Hasil P1 sebelumnya dipakai sebagai baseline, bukan dianggap status terbaru.

| Tahap | Doctor | Receptionist | Bukti dan batas |
| --- | --- | --- | --- |
| Create tenant/role/password | PASS | PASS | UI dan HTTP; klinik dari actor, hash password, profil Doctor/pivot layanan. |
| Permission create sesuai target | FIXED QA-13 | FIXED QA-13 | Matrix Doctor-only/Receptionist-only dan tenant-null; browser 403 tanpa akun baru. Role/tenant/permission asing dari payload update tetap diabaikan. |
| Status awal dan password setup | PASS | PASS | Aktif tetapi email belum terverifikasi; password awal admin. Penggantian mandiri di profil dan login baru diuji. Bukan invitation token/forced first-login change. |
| Verifikasi awal/resend | PASS lokal | PASS lokal | Notification fake dan signed endpoint di Chrome, bukan update verified langsung. Inbox nyata BLOCKED. |
| Login/portal/reset token | PASS | PASS | Portal pasien menolak staf; token reset password PHP dan login lama/baru diuji. |
| Password diganti admin | FIXED kebijakan sesi | FIXED kebijakan sesi | Cookie lama dialihkan ke login; remember-only lama tidak mengembalikan akses; login password baru berhasil. Driver array diuji di PHPUnit dan database di Chrome; driver lain NOT TESTED. |
| Password update rollback | NOT TESTED fault khusus Doctor | PASS | Fault injection: data/hash lama utuh, tidak ada verifikasi terkirim dan sesi lama tetap bekerja. Kasus fault spesifik memakai Receptionist. |
| Email diganti admin | PASS | PASS | Verified direset, VerifyEmail setelah commit, tautan lama 403, alamat baru diverifikasi dan login berhasil. |
| Gangguan notifikasi | FIXED QA-15 pada create dan perbaikan update | Sama | Failure dilaporkan; akun/alamat yang telah commit tetap unverified dan UI menjelaskan resend. Fault injection bukan bukti SMTP nyata pernah gagal. |
| Permission/role dicabut | PASS pada request berikut | PASS | PHP dan cookie browser; fixture memakai Spatie/SQL karena UI permission editor tidak tersedia. Tidak diklaim socket aktif telah terputus. |
| Nonaktif | PASS | PASS | UI admin; sesi lama putus pada request berikut dan login baru ditolak; Doctor.is_active ikut false. Tidak menghapus layanan/rekam medis. |
| Reaktif dan kapasitas | PASS | PASS | UI/HTTP serta SubscriptionEnforcementTest; pembatasan seat/read-only dan pengecualian pelayanan aktif tetap lulus. |
| Penghapusan akses admin | PASS melalui deaktivasi | PASS melalui deaktivasi | Tidak menambahkan destroy staff; policy delete admin tetap false. |
| Self-delete tanpa referensi | PASS Chrome | PASS Chrome | User dihapus, dashboard meminta login, booking lain tetap ada. |
| Self-delete dengan histori | FIXED QA-14 | Guard referensi aktual berlaku | PHP/Chrome menjaga user, visit, record dan session; Doctor role dicabut pun tidak menghilangkan retensi. |

## Akar masalah dan kebijakan saat ini

QA-13 dahulu memakai canAny untuk penyimpanan tanpa role target. Sekarang form request memanggil gate dengan role target; policy menjadi satu acuan permission. Tidak membuat role baru atau membolehkan Platform Admin mengakses medis.

QA-14 dahulu menjalankan logout sebelum cascade user gagal pada FK klinis. Guard sekarang melihat relasi aktual, bukan hanya nama role; owner guard QA-03 tetap lebih dulu. Pesan mengarahkan deaktivasi, bukan penghapusan histori.

Reset password admin sekarang memutar hash dan remember token di transaksi sukses. AuthenticateSession membandingkan hash pada request berikutnya, sehingga cookie lama ditolak pada web guard. Browser menguji sesi penuh dan remember-only sungguhan. Request yang sudah berjalan dan socket yang sudah terhubung tidak diklaim telah dihentikan.

Perubahan email mereset verified timestamp dan mengirim notifikasi setelah commit. Verifikasi lama tidak cocok dengan email baru. Pengiriman gagal dilaporkan tanpa mengklaim data yang telah commit dibatalkan; petunjuk resend tetap ada. Kegagalan transaksi tidak mengirim verifikasi. Mekanisme self-edit profil yang mereset verified tetap melewati halaman verifikasi/resend; automatic notification baru ini khusus alur admin StaffAccountService.

QA-15 baru direproduksi dengan dispatcher gagal: sebelumnya respons create 500 setelah user/role tersimpan; sesudah fix respons mengakui akun sudah dibuat dan tetap memerlukan verifikasi. Tidak menambahkan outbox/worker baru atau menganggap inbox sudah menerima pesan.

## Batas pengujian

SMTP/inbox/provider nyata, seluruh kombinasi role custom, sesi HTTP in-flight, revokasi socket yang sudah aktif, serta perangkat fisik/screen reader belum diverifikasi. signed URL di Chrome dibuat melalui notification class pada fixture server dan tidak dicetak di laporan. Data demo dan storage terisolasi; tidak ada akun atau histori klinis pengguna yang dihapus.
