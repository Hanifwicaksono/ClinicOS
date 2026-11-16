# PR-01 S1 — Reproduksi revokasi

**PR-01 tetap OPEN.** Temuan existing direproduksi; tidak ada source produksi yang diperbaiki.

## Lingkungan dan aktor

Runtime [runtime.json](evidence/runtime.json) berisi lokasi clone database sintetis dan alamat loopback. Environment berisi secret hanya di temporary directory, tidak disalin ke laporan. HTTP, Reverb dan database worker berjalan sebagai proses terpisah; PID di [processes.json](evidence/processes.json). Tidak membuka koneksi database pengguna.

| Aktor fixture | Peran pengujian |
| --- | --- |
| User 14, Doctor klinik 6, browser A dan B dengan cookie store berbeda | Target; masing-masing subscribe private queues + own users notification |
| User 13, Receptionist klinik 6 | Kontrol sah; melakukan check-in melalui browser authenticated fetch |
| User 15, Patient terkait appointment | Kontrol sah untuk users notification |
| User 45, Receptionist klinik 14 | Foreign tenant; authorization channel klinik 6/user 14 ditolak; delivery klinik sendiri diuji terpisah |
| Tab tambahan pada context browser A | Multi-tab yang benar-benar berbagi session HTTP, bukan hanya dua akun/browser berbeda |

Fixture awal menambahkan 80 pasangan appointment/queue BOOKED dengan relasi tenant eksplisit dan satu pasangan foreign untuk kontrol. Dua job pending dari clone dummy dibuang **sebelum** pengujian agar tidak menghasilkan frame lama; tidak ada histori klinis/pembayaran dihapus. Event dikirim berurutan untuk skenario fungsional, bukan burst/load/stress.

## Langkah reproduksi baseline

1. Tunggu acknowledgement subscription kedua browser target dan kontrol.
2. Check-in queue unik sebelum perubahan. Pastikan private queue dan notification sampai ke target dan kontrol.
3. Cabut role lewat API Spatie; atau permission efektif; atau gunakan StaffAccountService untuk inactive/reset password. Mutasi pada fixture dilakukan CLI aplikasi, bukan endpoint produksi tambahan. Tidak ada UI permission editor dibuat.
4. Catat waktu nyata setelah transaksi mutasi selesai. Minta authorization baru dari browser target.
5. Kontrol check-in **queue berbeda yang belum pernah dipublikasikan** setelah perubahan selesai. Tunggu worker, event kontrol queue, dan notification pasien. Cocokkan queue_id dan waktu server/browser; bukan menghitung buffered frame sebelum perubahan.

## Hasil baseline aktual

| Skenario | Queue sebelum / sesudah | Authorization clinic baru | Socket lama A / B sesudah perubahan | Hasil keamanan |
| --- | --- | --- | --- | --- |
| Role Doctor dicabut | 111 / 112 | 403 | Masing-masing menerima private queue + users notification baru | FAIL |
| queue.view dicabut dari role Doctor | 113 / 114 | 403 | Masing-masing menerima kedua event private | FAIL |
| Akun inactive melalui StaffAccountService | 115 / 116 | 401 | Masing-masing menerima kedua event private | FAIL |
| Password direset melalui StaffAccountService | 117 / 118 | 401 | Masing-masing menerima kedua event private | FAIL |

Semua assertion **reproduksi** PASS; properti revokasi baseline **FAIL**. Data mentah: S1-B-* di [results-main.json](evidence/results-main.json), [wire.jsonl](evidence/wire.jsonl). Tidak ada klaim kebocoran isi medis: event yang diamati membawa payload nonmedis.

SDK terminate tanpa signed user identity menerima request tetapi kedua browser tetap menerima queue 119. Dengan identitas bertanda tangan, kedua socket lama benar-benar ditutup dan socket ID berubah. Akan tetapi pada mode signed-only, auto-reconnect masih dapat subscribe users milik sendiri melalui callback ownership existing, lalu menerima notification queue 120. Memutus socket lama tidak sama dengan menolak seluruh jalur subscribe baru.

## Hasil kandidat gabungan

Pada guard + signed epoch, role, permission role, direct-only permission, inactive dan password semuanya menghentikan private delivery ke kedua browser. Percobaan terminate dengan secret dummy salah menghasilkan **401**; guard tetap menolak pengiriman. Percobaan control endpoint tidak terjangkau menghasilkan SDK ApiErrorException dengan cause ConnectException; guard tetap menolak. Kontrol queue/notification/public tetap menerima.

S1-11 menahan authorization valid sebelum revokasi, unsubscribe lalu mengirim signature lama pada socket yang sama setelah revokasi: Reverb menolak 4009. S1-12 mengubah user_id dalam signed data tanpa mengubah signature: 4009. S1-13 membuktikan reconnect target yang dicabut gagal memperoleh binding private; S1-14 membuktikan reconnect pengguna sah berfungsi. S1-24 menutup kasus dua tab berbagi session. S1-25 membuktikan foreign tenant tetap menerima event kliniknya sendiri.

## Batas waktu yang benar

`mutation.barrier` dalam JSON kontrol adalah **waktu setelah commit CLI**, bukan bukti revokasi transport telah efektif. `published_after` pada artifact browser awal adalah waktu observasi selesai, bukan timestamp publisher. Gunakan queue_id unik, urutan aksi, dan trace server untuk menentukan delivery.

Race S1-20 sengaja menghentikan server setelah pemeriksaan izin berhasil tetapi sebelum send:

| Peristiwa, queue 167 | Waktu epoch nyata |
| --- | --- |
| Pemeriksaan izin selesai / pause | 1794731440.746356 |
| Mutasi role sudah committed | 1794731441.146325 |
| Server mengirim frame yang telah diotorisasi sebelumnya | 1794731441.158984 |
| Browser menerima frame tersebut | 1794731441.161 |
| Reverb memproses local fence dan mengakui epoch 217 | 1794731441.271106 |

Frame itu **dikirim setelah commit tetapi sebelum acknowledgement server**. Ini bukan frame baru yang diotorisasi setelah fence. Queue 168 dipublikasikan setelah acknowledgement: tidak ada private frame untuk target A/B, kontrol tetap menerima. Bukti ada di S1-20 [results-final-guard.json](evidence/results-final-guard.json) dan wire trace. Browser receive time saja tidak membuktikan send order lintas koneksi; MessageSent berada sesudah server send, sedangkan pause membatasi titik authorization.

Kontrak “nol send sesudah HTTP commit” belum dipenuhi oleh pemeriksaan read-then-send sederhana. Rekomendasi memerlukan batas efektif berupa acknowledgement server setelah in-flight send sebelumnya selesai, atau serialization/fencing lebih kuat bila product memilih commit sebagai batas. Tidak menganggap keputusan itu sudah disetujui.
