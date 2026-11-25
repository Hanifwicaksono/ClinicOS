# ClinicOS Voice — panduan teknis

Implementasi repository: 19 November 2026. Modul opsional untuk dokter penanggung jawab visit. Semua hasil AI merupakan draft. Persetujuan Voice menerapkan field terpilih ke **draft RME**, sedangkan finalisasi/koreksi tetap melalui workflow RME existing.

## Audit dan keputusan integrasi

- PHP 8.5.5, Laravel 13.34.0, Livewire 3.8.10, Volt 1.11.2, Tailwind 3.4.17 dan Vite 8.3.4 diperiksa pada checkout. Tidak ada dependency Composer/NPM baru.
- `Visit`, `MedicalRecord`, policy dokter, `record_version`/`lock_version`, autosave, alergi, diagnosis, resep, tindakan, billing dan audit existing dipakai sebagai acuan. Tidak membuat encounter/RME/billing paralel.
- `VisitWorkflowService::syncRecord()` menyinkronkan satu form lengkap; payload SOAP parsial dapat menghapus vital sign/resep. Karena itu `applyVoiceSoap()` di service yang sama hanya memperbarui field terpilih, memeriksa versi dan status, serta mempertahankan relasi lainnya.
- Panel berada pada `doctor/visits/show`, di luar form RME. Sebelum approval, JavaScript menunggu autosave dan memblokir approval bila foto masih menunggu submit manual. Server mengulang pemeriksaan versi/status di dalam transaksi.
- SOAP hanya diedit pada form pemeriksaan existing. Panel Voice menampilkan transkrip dan ringkasan baca-saja dalam disclosure. Dokter memilih kolom yang memiliki hasil, meninjau ringkasan, lalu menekan **Isi kolom SOAP**. Setelah konfirmasi penggantian bila diperlukan, halaman mengarah ke form RME untuk koreksi, penyimpanan draft dan finalisasi. Tidak ada textarea SOAP kedua atau tombol Simpan Draft Voice pada panel.
- `Permissions-Policy` sebelumnya melarang seluruh mikrofon. Pengecualian `microphone=(self)` hanya berlaku pada halaman visit yang dapat diedit dokter dan klinik yang diizinkan.
- Katalog ICD-10/farmasi, dispensing, stok dan drug interaction engine belum ditemukan. Kandidat diagnosis, obat dan tindakan ditampilkan dengan bukti untuk diverifikasi dan dimasukkan dokter lewat editor existing. Tidak ada mapping katalog buatan, auto-prescription, auto-billing, pengurangan stok, atau perubahan DOKU.
- `.ai/rules` dan skill `testing-best-practices` tidak ditemukan pada lokasi yang diperiksa. Test mengikuti pola PHPUnit/factory existing. Tidak mengubah Volt.

## Fase dan file

| Fase | Implementasi | Batas |
| --- | --- | --- |
| 0 Audit | Pemetaan di atas; baca kode, manifest, kebijakan tenant dan dokumen terkait | Analisis statis bukan audit legal/production |
| 1 Foundation | Migration `2026_11_19_144623_create_voice_scribe_tables.php`; lima model Voice/AI; `VoiceSessionFactory`; `config/voice.php`; `.env.example`; `VoiceSessionRequest`; provider binding dan rate limiter di `AppServiceProvider` | Migration dibuat; tidak dijalankan pada database aplikasi |
| 2 Recording | `resources/views/doctor/visits/voice-panel.blade.php`; `resources/js/voice-scribe.js`; include pada `show.blade.php`; import pada `app.js`; aturan hidden pada CSS; pengecualian mikrofon pada `SecurityHeaders` | Diuji di Chrome dengan perangkat audio simulasi; belum Safari/tablet fisik |
| 3 Transcription | `TranscriptionProvider`, `OpenAiScribeProvider`, `FakeScribeProvider`, `VoiceAudioStorage`, `ProcessVoiceSession`, koneksi Queue `voice` | Adapter HTTP nyata tersedia; belum dipanggil dengan kredensial layanan nyata |
| 4 AI SOAP | `ClinicalExtractionProvider`, `ClinicalDraftValidator`; ekstraksi terpisah dari STT dengan schema/evidence | Validasi struktur/sumber tidak menjamin kebenaran klinis |
| 5 Review/RME | `Doctor/VoiceSessionController`, `VoiceScribeService`, `VisitWorkflowService::applyVoiceSoap`, route `doctor.voice.*` dan pengecualian penyelesaian pelayanan pada `EnsureSubscriptionWritable` | Penerapan hanya SOAP; finalisasi tetap manual |
| 6 Clinical integration | Kandidat diagnosis, obat/dosis/rute/durasi dan tindakan planned/performed/uncertain, beserta kutipan | Belum ada integrasi katalog ICD/farmasi; input klinis terstruktur tetap melalui editor existing |
| 7 QA/release | Dua test PHPUnit, pengujian JavaScript, helper browser `tests/voice-scribe-browser.mjs` dengan runner existing; `PurgeVoiceData` dan jadwal purge | Pilot klinis, provider nyata, MySQL concurrency dan sign-off privasi belum dilakukan |

`VoiceScribeService` mengelola consent, lifecycle, kuota, review dan presentasi data. `ProcessVoiceSession` mengorkestrasi dua provider terpisah serta checkpoint transkrip per segmen. Pemanggilan provider berjalan di luar transaksi database; publikasi hasil memeriksa ulang otorisasi dan status dalam transaksi singkat.

## Data, keamanan dan lifecycle

- `voice_sessions`: UUID; FK klinik, visit, pasien, dokter, pencatat consent; versi/waktu consent; provider/model snapshot; status; durasi; jumlah percobaan. `(clinic_id, idempotency_key)` unik.
- `voice_segments`: FK sesi; nomor urut unik per sesi; checksum; format; durasi aktual; referensi audio dan status purge.
- `voice_transcripts`: satu checkpoint terenkripsi per segmen; versi. Urutan ditentukan nomor segmen, bukan urutan penyelesaian request.
- `ai_clinical_drafts`: payload ekstraksi dan SOAP hasil edit terenkripsi; versi review; reviewer; versi RME hasil penerapan; waktu penerapan. Satu draft per sesi.
- `ai_review_logs`: aktor, tindakan dan perubahan terenkripsi. Nilai medis tidak masuk metadata audit administratif.
- Audit administratif Voice merujuk `Visit` dengan `entity_id` numerik; UUID sesi disimpan pada metadata `voice_session_id`. Kolom audit existing adalah unsigned bigint dan tidak menerima UUID secara langsung pada MySQL strict.
- Audio disimpan dengan Laravel Crypt pada disk `local`, dalam `storage/app/private/voice/{clinic}/{session}/...enc`. Tidak ada endpoint publik/audio URL atau kredensial dalam browser. Perlindungan transkrip/draft pada database memakai encrypted casts.
- Gunakan mekanisme backup/kunci aplikasi existing. Jangan mengganti `APP_KEY` untuk memasang modul; hilangnya kunci akan membuat data terenkripsi tidak terbaca. Enkripsi volume/database/backup dan pengelolaan kunci infrastruktur tetap tanggung jawab deployment.
- Semua endpoint memakai auth, verified, onboarding, role Doctor, validasi request dan authorization service. Klinik/pasien/dokter diturunkan dari visit server-side. Job memeriksa ulang akun aktif, role, permission, relasi tenant, consent dan visit sebelum API dan sebelum publikasi.
- Status browser: idle, awaiting_consent, recording, paused, uploading, processing, review_required, approved/completed, failed dan cancelled. Approval dan completion sesi disimpan atomik; `review_status=approved`, `approved_at` dan `completed_at` menjadi bukti kedua tahap. RME tetap Draft.
- Penerapan hanya pada visit `IN_PROGRESS` dan RME Draft. Kunjungan paused/interrupted/final harus mengikuti workflow existing; Voice tidak membuka kembali final atau melewati amendment.
- Perubahan RME yang sudah terisi memerlukan konfirmasi. Approval ganda tidak menerapkan ulang. Konflik versi mengembalikan HTTP 409 dan tidak mengubah RME.
- Consent dicatat berdasarkan attestasi dokter setelah penjelasan kepada pasien/wakil berwenang, bukan rekaman diam-diam atau izin perangkat semata. Klinik harus meninjau teks/prosedur persetujuan sebelum aktivasi.
- Cancel/withdraw menghentikan perekaman lokal dan menghapus materi sementara yang tersimpan: audio, transkrip, payload/edit draft dan nilai pada review log. Metadata tindakan dan RME dipertahankan. Kegagalan request pembatalan ditampilkan; scheduler mengulang pembersihan sesi cancelled/withdrawn.
- Pencabutan consent tidak dapat menarik kembali request yang sudah diterima penyedia AI. Hasil yang terlambat tidak dipublikasikan; kebijakan penghapusan pada penyedia harus ditinjau terpisah.
- Mulai sesi baru mengikuti pembatasan subscription existing. Sesi pelayanan yang sudah aktif dapat diunggah/diselesaikan/review melalui pengecualian pelayanan. Cancel/withdraw tetap tersedia saat subscription read-only; policy dokter tetap berlaku.

## Rekaman, batas penggunaan dan kegagalan

- Format MVP: WebM/Opus atau MP4 dari browser; WAV juga diterima backend. MIME, signature container, stream audio-only, ukuran dan durasi diperiksa ulang dengan `ffprobe`. Durasi deklarasi browser tidak dipercaya.
- Browser menyelesaikan klip mandiri tiap 30 detik, lalu memulai klip berikutnya pada stream yang sama. Setiap file dapat diverifikasi/transkripsi mandiri. Pergantian klip dapat menimbulkan jeda singkat; belum merupakan rekaman kontinu tanpa kehilangan audio. Hal ini perlu diukur pada perangkat pilot.
- Batas segmen 10 MiB/65 detik. Default sesi 1.800 detik, kuota klinik 18.000 detik per bulan kalender aplikasi. Kuota dihitung dari segmen yang diterima pada bulan berjalan, dibulatkan ke atas per segmen, dengan lock klinik; metadata penggunaan tetap ada setelah purge.
- Upload berurutan dengan checksum/idempotency. Stop hanya diterima ketika semua nomor segmen lengkap. Request yang kehilangan respons dapat dikirim ulang tanpa menyimpan/menghitung segmen dua kali.
- Jika jaringan gagal, blob yang belum diakui server tetap di memori halaman untuk tombol Coba Lagi. Perekaman dihentikan jika backlog mencapai empat segmen. Tidak menyimpan audio/transkrip di localStorage atau IndexedDB. Reload/tutup halaman dapat menghilangkan segmen belum terkirim; tersedia peringatan sebelum meninggalkan halaman.
- Setelah reload, sesi yang masih recording/paused dapat dibuka kembali dan dilanjutkan secara eksplisit, dengan nomor sesudah segmen server terakhir. Mikrofon tidak dimulai otomatis.
- Pemrosesan maksimal tiga percobaan per sesi, termasuk percobaan ulang manual dan worker. Backoff otomatis 30/120 detik. Transkrip segmen yang berhasil digunakan kembali. Timeout job 1.200 detik; `retry_after` koneksi 1.260 detik.
- Idempotency aplikasi tidak menjamin penyedia tidak menagih request yang timeout setelah diterima. Biaya retry harus diukur pada pilot. Gunakan batas biaya akun provider selain kuota aplikasi.
- Provider/model snapshot disimpan per sesi. Pergantian jenis provider saat job tertunda menyebabkan penolakan sampai konfigurasi sesuai; jangan mengganti provider di tengah sesi aktif.
- JSON rusak, refusal, output terpotong, schema asing, quote yang tidak ada, angka tanpa dukungan dan kandidat nama/detail yang tidak muncul dalam bukti ditolak. Field SOAP yang tidak disebutkan tetap null. Prompt memperlakukan transkrip sebagai data tidak tepercaya. Pemeriksaan ini belum dapat membuktikan semantik atau identitas pembicara; review dokter tetap wajib.
- Tidak ada diarization, confidence klinis buatan, waveform palsu atau transkripsi streaming pada MVP.
- Endpoint mengembalikan pesan aman; exception provider diganti kode teknis tanpa transcript/audio/response body. UI menampilkan status gagal dan pilihan retry/manual.

## Setup lokal

Prasyarat: dependency proyek existing, ekstensi PHP yang diperlukan aplikasi, `ffprobe` pada PATH (atau path eksplisit), database aplikasi yang telah di-backup sesuai prosedur, browser kompatibel, dan HTTPS. Browser localhost dapat digunakan untuk pengembangan. Modul tidak menambahkan paket Composer/NPM.

1. Tinjau migration additive dan jalankan melalui prosedur migration proyek: `php artisan migrate --no-interaction`. Pekerjaan implementasi ini tidak menjalankannya pada database pengguna.
2. Gunakan contoh variabel `.env.example`. Untuk development dengan **data sintetis saja**, atur `VOICE_ENABLED=true`, `VOICE_PROVIDER=fake`, dan `VOICE_CLINIC_IDS` ke daftar ID klinik development, dipisahkan koma. Provider fake menghasilkan contoh tetap bertanda SIMULASI, **bukan** hasil pengenalan audio.
3. Untuk provider OpenAI, gunakan `VOICE_PROVIDER=openai`, isi `VOICE_OPENAI_API_KEY` di backend, dan pilih `VOICE_TRANSCRIPTION_MODEL` serta `VOICE_EXTRACTION_MODEL` yang tersedia pada akun dan mendukung kontrak API di bawah. Tidak ada model default yang diasumsikan.
4. Bangun aset: `npm run build` atau jalankan Vite development sesuai workflow proyek.
5. Jalankan worker khusus: `php artisan queue:work voice --queue=voice --sleep=1 --tries=3 --timeout=1200 --no-interaction`.
6. Jalankan scheduler proyek, atau uji purge dengan `php artisan voice:purge --no-interaction`. Purge terjadwal setiap jam; konfigurasi file saja tidak membuktikan scheduler/worker berjalan.

Jangan menyalin kredensial ke Blade, screenshot atau laporan. Fitur nonaktif secara default, dan daftar klinik default kosong. Di luar environment local/testing, aktivasi juga memerlukan `VOICE_PRIVACY_APPROVED=true`; fake provider selalu ditolak di production.

| Variabel | Default / fungsi |
| --- | --- |
| `VOICE_ENABLED` | false; sakelar modul |
| `VOICE_CLINIC_IDS` | kosong; allowlist klinik pilot |
| `VOICE_PRIVACY_APPROVED` | false; attestasi operator setelah review privasi sebelum environment nondevelopment |
| `VOICE_CONSENT_VERSION` | voice-v1; versi penjelasan/persetujuan klinik |
| `VOICE_PROVIDER` | openai; fake hanya local/testing |
| `VOICE_OPENAI_API_KEY` | kosong; secret backend khusus modul |
| `VOICE_TRANSCRIPTION_MODEL` | kosong; wajib untuk provider nyata |
| `VOICE_EXTRACTION_MODEL` | kosong; wajib, mendukung Structured Outputs |
| `VOICE_FFPROBE_BINARY` | ffprobe; dapat berupa path binary lokal |
| `VOICE_MAX_SESSION_SECONDS` | 1800 |
| `VOICE_MONTHLY_SECONDS` | 18000 |
| `VOICE_AUDIO_RETENTION_HOURS` | 24 |
| `VOICE_TRANSCRIPT_RETENTION_DAYS` | 7 |
| `VOICE_DRAFT_RETENTION_DAYS` | 30; termasuk nilai medis review log |

Retensi di atas merupakan default teknis, bukan kebijakan klinis/legal yang sudah disahkan. Kutipan evidence di dalam draft mengikuti retensi draft, sehingga penghapusan field transkrip tujuh hari tidak berarti seluruh kutipan pada draft ikut dihapus saat itu. Purge mempertahankan metadata penggunaan dan audit, membersihkan file orphan yang melewati retensi, serta tidak menghapus RME. Sesi rekaman yang melewati retensi dibatalkan; proses yang tidak berubah selama dua jam ditandai gagal.

Production memerlukan worker dengan penghentian timeout yang benar, supervisor/process manager, cache terpusat untuk unique jobs antarworker, pengamanan storage dan backup, monitoring antrean/failed jobs, scheduler dan HTTPS yang diverifikasi. PHP Windows tanpa pcntl tidak membuktikan hard timeout worker Linux/production.

## Route dan kontrak browser

Semua route berada pada `/doctor/visits/{visit}/voice`, dalam middleware dokter existing dan throttle per klinik.

| Method | Suffix | Fungsi |
| --- | --- | --- |
| GET | `/` | Sesi terakhir dokter untuk visit |
| POST | `/` | Mulai; consent, patient_confirmed, consent_version, UUID idempotency_key |
| GET | `/{voiceSession}` | Status/transkrip/draft yang diotorisasi |
| POST | `/{voiceSession}/segments` | File audio dan segment_no |
| POST | `/{voiceSession}/{action}` | pause, resume, stop, cancel, withdraw, retry, review, approve |

Kontrak review API tetap menerima seluruh lima field SOAP dan `draft_version` untuk kompatibilitas. Panel utama menggunakan approval untuk memasukkan hasil Voice terpilih; koreksi selanjutnya memakai editor RME. Approval menambahkan `fields` yang dipilih, `record_version`, attestasi `review_confirmed` dan `confirm_overwrite` bila diperlukan. Hanya field dalam allowlist SOAP yang dapat diterapkan. Kandidat AI tidak menentukan FK, clinic_id, harga atau charge.

## Pengujian otomatis dan manual

PHPUnit memakai fake/mock provider dan fixture WAV sintetis. Tidak mengirim data pasien nyata. Eksekusi aman SQLite pada proses PowerShell terpisah:

```powershell
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = ''
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit tests/Feature/VoiceScribeTest.php tests/Feature/VoiceScribeProviderTest.php --no-progress --do-not-cache-result
```

Regresi mencakup VisitWorkflow, PartialWorkflowCompletion, MedicalRecordCorrection, ClinicalOutputs, PatientBilling, SecurityHeaders, SubscriptionEnforcement dan MedicalRecordAccess. Perintah Node: `node --test tests/voice-scribe.test.mjs tests/partial-workflows.test.mjs`.

Hasil run awal pada 19 November 2026:

- PHPUnit modul dan regresi terkait: **98 test lulus, 796 assertion**, SQLite in-memory. Ini bukan hasil seluruh suite proyek dan tidak membuktikan concurrency MySQL.
- JavaScript modul/booking/autosave: **16 test lulus**.
- Chrome end-to-end dengan perangkat simulasi/worker nyata: **1 skenario lulus**, termasuk consent, pause/resume/stop, dua segmen audio, transkrip, edit/simpan draft, approval field terpilih, preservation RME lain dan withdrawal. Responsivitas diperiksa pada 375/768/1024/1440 px.
- `npm run build`, `php vendor/bin/pint --dirty --format agent` dan `git diff --check` lulus.

Verifikasi perbaikan audit MySQL pada 19 November 2026: 29 test modul/provider SQLite (220 assertion), 10 test regresi akses/subscription/header (68 assertion), satu test lifecycle pada MySQL strict (31 assertion), 17 test JavaScript, dan satu skenario Chrome dengan nama pasien 240 karakter lulus. Test MySQL membuat lalu menghapus schema sementara bernama acak; tidak memakai database aplikasi. Ini menguji kompatibilitas tipe dan workflow MySQL, bukan concurrency multi-proses. Respons error server Voice tetap generik saat debug aktif; log database Voice hanya memuat kode teknis dan nama route.

Untuk mengulang test MySQL terisolasi, gunakan kredensial server lokal yang memiliki hak membuat schema sementara:

```powershell
$env:CLINICOS_VOICE_MYSQL_TEST = '1'
php -d extension=gd vendor/bin/phpunit tests/Feature/VoiceScribeMysqlTest.php --no-progress --do-not-cache-result
```

Uji browser menggunakan database SQLite dan storage sementara yang dibuat runner, seeder demo sintetis pada database terisolasi, Chrome headless/perangkat audio simulasi, dan worker Queue database nyata:

```powershell
$env:CLINICOS_BROWSER_TEST = '1'
$env:CLINICOS_VOICE_BROWSER_TEST = '1'
node --test tests/browser-partial-workflows.test.mjs
```

Runner tidak memakai database aplikasi. Konfirmasi browser diterima otomatis pada pengujian; navigasi tab/fokus juga diuji dengan event keyboard DOM. Screenshot tersimpan di direktori sementara yang dicetak runner. Jangan menyamakan perangkat simulasi dengan pengujian mikrofon, akustik klinik, Safari atau tablet fisik.

Checklist manual pada environment pilot terisolasi:

1. Tanpa consent, tombol rekam tidak aktif dan mikrofon tidak diminta. Pastikan penolakan tetap memungkinkan RME manual.
2. Tolak izin mikrofon; cabut perangkat; uji browser/format tidak didukung. Pesan harus dapat ditindaklanjuti.
3. Mulai, jeda, lanjut dan selesai; periksa indikator mikrofon serta urutan/kelengkapan segmen. Rekam melewati batas rotasi 30 detik dan ukur kehilangan audio pada pergantian klip.
4. Putuskan koneksi saat upload, pulihkan lalu Coba Lagi. Jangan tutup halaman sampai upload selesai. Uji peringatan meninggalkan halaman.
5. Matikan worker, hentikan proses worker, simulasikan provider timeout/rate limit/refusal/JSON rusak; pastikan status gagal/retry terlihat dan RME tidak berubah.
6. Edit SOAP/manual di dua tab; approval stale harus 409. Simpan foto manual sebelum approval. Hanya field dicentang yang berubah dan overwrite memerlukan konfirmasi.
7. Tutup/finalisasi/jeda visit sebelum hasil datang; cabut permission dokter; uji ID sesi milik tenant/dokter lain. Jangan ada publikasi/penerapan yang melewati policy.
8. Tinjau ringkasan Voice, pilih kolom, lalu Isi kolom SOAP. Koreksi dan simpan draft pada editor RME existing; finalisasi tetap di editor tersebut. Approval dua kali tidak boleh menerapkan ulang. Pastikan resep, diagnosis, tindakan, vital sign, foto dan billing tidak berubah oleh approval SOAP.
9. Cabut consent sebelum/saat/setelah pemrosesan dan setelah finalisasi RME. Materi sementara hilang, rekam medis tetap ada.
10. Uji desktop/tablet dan viewport 375/768/1024/1440, navigasi keyboard, fokus, empty/loading/error state. Uji retensi dan restore backup terenkripsi di lingkungan terisolasi.

## Referensi provider dan prasyarat rollout

Adapter memakai endpoint resmi OpenAI `/v1/audio/transcriptions` (multipart, bahasa id, response JSON) dan `/v1/chat/completions` dengan `response_format=json_schema`, strict schema serta `store=false`. Kontrak diperiksa melalui [Speech-to-text](https://developers.openai.com/api/docs/guides/speech-to-text) dan [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=chat). Model harus dipilih/dikonfirmasi operator terhadap dokumentasi dan akun yang dipakai. Mengganti provider memerlukan implementasi kedua interface, binding dan tes kontrak; controller tidak perlu dirombak.

Review privasi harus memeriksa lokasi pemrosesan, retensi dan penggunaan data penyedia, perjanjian pemrosesan, penghapusan, dasar pemrosesan, prosedur consent dan akses. `store=false` bukan jaminan zero retention seluruh endpoint/akun. Acuan peninjauan: [Permenkes 24/2022](https://peraturan.bpk.go.id/Details/245544/p) dan [UU PDP 27/2022](https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022-10). Flag konfigurasi bukan sertifikasi kepatuhan.

Sebelum production: uji provider nyata dengan data sintetis yang disetujui, uji konkurensi MySQL/worker multi-proses, verifikasi kegagalan serta penghapusan storage, perangkat/mikrofon nyata, dan pilot klinis ber-consent. Tetapkan ambang waktu dokumentasi setelah koreksi, kesalahan klinis penting, tingkat keberhasilan rekaman, latency dan biaya. Belum ada klaim akurasi klinis, penghematan waktu atau kesiapan production dari implementasi ini.
