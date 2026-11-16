# Role, permission dan tanggung jawab

Fakta source 15 November 2026. Katalog evidence E merujuk [register](EXISTING_BUSINESS_RULES.md). Semua pemberian hak baru di bawah adalah PROPOSED/DECISION REQUIRED, bukan perubahan permission.

## Akses existing yang benar-benar tersusun

`auth → active account/session → verified → onboarding bila relevan → route role → permission/policy → tenant/resource → subscription write gate → domain state/version`. `GET` yang lolos subscription middleware tetap harus lolos policy. Seeder permission tidak memberi akses route dengan role lain. Owner adalah persona Clinic Admin dengan account_type dan hubungan ownership, bukan superuser medis.

| Aksi | Owner / Clinic Admin | Receptionist | Doctor | Patient | Platform Admin / token guest |
| --- | --- | --- | --- | --- | --- |
| Profil klinik/jam/layanan/weekly schedule | Clinic sendiri, permission terkait | Baca konteks operasional yang tersedia | Baca konteks operasional yang tersedia | Public context | Platform metadata; guest public context |
| Buat/edit Doctor/Receptionist | Clinic sendiri; seat/reactivation guard | Tidak | Tidak | Tidak | Bukan jalur platform provisioning staff |
| Kelola subscription/checkout | **Owner** clinic terkait; admin non-owner tidak otomatis | Tidak | Tidak | Tidak | Platform grant/katalog/refund melalui permission platform |
| Daftar/edit pasien | Clinic sendiri, patient permission | Clinic sendiri | Tidak ada route edit administrasi pasien di doctor group | Booking dapat update profil sendiri yang linked | Tidak ada hak platform/guest ke list pasien |
| Booking staf / walk-in | Ya | Ya | Tidak melalui route staf | Public booking sebagai akun Patient verified | Guest baru tidak mempunyai POST booking publik terbuka |
| Appointment list/detail | Clinic sendiri | Clinic sendiri | Jalur queue sendiri; route appointment staf menolak Doctor, walau AppointmentPolicy::view menganggap Doctor sebagai clinic staff | Milik user sendiri | Token guest hanya booking terkait; platform tidak memakai route ini |
| Cancel appointment | BookingService cutoff; pre-visit | Sama | Tidak sebagai operator generic cancel | Milik sendiri + cutoff | Token valid + cutoff; platform tidak |
| Check-in, cancel queue, no-show | queue.manage + tenant | Sama | Tidak diberi queue.manage default | Tidak | Tidak |
| Call, skip, return | queue.call + tenant | Sama | Hanya queue assigned | Tidak | Public display bukan kontrol |
| Start / save / complete visit | Tidak otomatis | Tidak | Assigned, role/permission/state/version | Tidak | Tidak |
| Baca isi RME/foto/revisi | Tidak otomatis | Tidak | Assigned author/current source policy; histori dokter sama | Tidak ada full RME portal | Platform/guest tidak |
| Koreksi final | Tidak | Tidak | Completed + assigned + reason/version; subscription write gate | Tidak | Tidak |
| Ubah alergi | Tidak | Tidak | Assigned visit IN_PROGRESS | Tidak | Tidak |
| Baca own notifications | User sendiri | User sendiri | User sendiri | User sendiri | Platform own notifications saja; guest tidak |
| Retry revokasi | Admin clinic terkait dengan doctor.update atau receptionist.update | Tidak lewat route admin | Tidak | Tidak | Bukan medical bypass; tidak memulihkan hak lama |

Bukti: E04 route groups/seeder; E05 `UserPolicy::create/update`, `AppointmentPolicy::viewOwn/cancelOwn`, `PatientPolicy::update`; E12 `QueuePolicy::call/startVisit/update`; E13 `VisitPolicy` dan `MedicalRecordPolicy`; [retry controller](../../app/Http/Controllers/Admin/RealtimeRevocationController.php) `retry`. Patient memiliki `queue.view` di katalog, tetapi route antrean staf tetap staff-only; metadata antrean pribadi tersedia melalui appointment milik sendiri.

## Batas yang tidak boleh berubah karena PG-01–03

1. Reschedule/reassignment **sebelum visit** adalah administrasi pasien/slot, bukan izin membuka RME. Admin/rec dapat melihat ringkasan nonmedis dan dampak kapasitas tanpa SOAP.
2. Mengganti doctor_id setelah start mengubah siapa yang lolos policy saat ini, sekaligus menghilangkan akses penulis lama. D-03 harus memilih authorship/responsibility/access model; jangan sekadar menambah role dokter penerima lalu overwrite FK.
3. Akun dokter nonaktif/password reset/role dicabut tetap kehilangan HTTP+private WS. Pengecualian subscription untuk menyelesaikan pelayanan **bukan** pengecualian autentikasi/active account.
4. Ownership clinic, akses operasional admin, tanggung jawab klinis dan akses commercial adalah empat dimensi berbeda. Owner tidak otomatis menjadi dokter atau orang yang boleh mengesahkan catatan klinis.
5. Current policy beberapa read medis memakai doctor ownership tanpa explicit clinic comparison di method yang sama; controller query dan relational consistency tetap penting. Guard tenant eksplisit pada fitur baru aman sebagai penguatan teknis, bukan izin memperluas akses.
6. Multi-role bukan union semua kemampuan tanpa batas: cabang Doctor/Patient pada policy dapat mempersempit akses. Fixed-role UI existing tidak sama dengan custom role editor.
7. Current commercial guard memakai owner account flag + clinic_id; tidak semua method memeriksa owner FK. Transfer/repair harus membuktikan keduanya konsisten dan tidak memberikan ownership hanya dengan mengubah flag. Ini risiko statis, bukan bukti ada akun pengguna yang salah.

## Otoritas PROPOSED yang perlu persetujuan

| Keputusan | Usulan pelaku | Batas | Persetujuan sebelum implementasi |
| --- | --- | --- | --- |
| D-01 | Admin/rec melakukan atomic replacement pre-visit; dokter tidak diberi administrative override otomatis | BOOKED/WAITING/SKIPPED tanpa visit; CALLED ditarik lewat aksi sah terlebih dahulu; alasan/preview | Product + operasional; self-service pasien dipilih terpisah |
| D-02 | Admin clinic mengesahkan exception; dokter dapat melaporkan ketidakhadiran bila diminta scope | Tidak memutus visit aktif atau menghapus booking lama | Owner/operasional + product |
| D-03A | Dokter assigned pause/resume; penghentian klinis oleh dokter berwenang menurut SOP | Admin/rec boleh mengajukan alasan administrasi tanpa isi RME, bukan finalize palsu | Penanggung jawab klinis + product |
| D-03B | Dokter pengirim/penerima dan reviewer klinis yang eksplisit menerima handoff | Tidak memberi akses seluruh medical history; read set ditetapkan; authorship lama tetap | Penanggung jawab klinis + product/commercial |
| D-04 | Dokter menulis instruksi; admin/rec menangani due/booking nonmedis; pasien menerima instruksi terpilih | Clinical notes tidak ikut task board operasional | Klinis + operasional |
| D-06 | Admin/rec dengan patient.update reaktivasi setelah verifikasi administrasi | Tidak membuka account user inactive otomatis; tidak link phone/NIK | Operasional + product; clinical review bila alasan klinis |
| D-07/13 | Dokter berwenang menerbitkan dokumen/resolusi alergi | Pasien/penerima dokumen dan supervisor bukan otomatis semua admin | Penanggung jawab klinis + product |
| D-09/10/11 | Platform permission yang sesuai; owner hanya subset clinic sendiri | Histori medis terpisah; undo grant bukan refund uang | Commercial/product; klinis untuk dampak pelayanan/retensi |
| D-14 | Operator diagnosis read-only terotorisasi; repair actor per kategori | Read-only diagnosis pun tidak membuka seluruh RME ke platform | Pemilik data + klinis/komersial sesuai kategori |

Tidak diperlukan role keenam, superadmin medis, impersonation atau permission editor untuk menyelesaikan fixed workflows. Jika supervisor klinis dibutuhkan, putuskan capability spesifik dan scope episode—bukan otomatis mengubah Clinic Admin menjadi otoritas klinis.

## Acceptance criteria akses untuk seluruh fitur baru

- Uji positif pelaku yang dipilih, negatif role lain, tenant lain, object ID forged dan account inactive; jangan hanya test menu tersembunyi.
- Periksa ulang assignment/tenant/epoch **saat commit**, termasuk dokter penerima yang dinonaktifkan setelah preview.
- Handoff request ditolak bila penerima belum menerima, bukan Doctor aktif pada clinic yang benar, atau authority berubah; old author/history tidak hilang.
- Tidak ada raw SQL/pivot ACL yang melewati S2 mutation APIs. Pemulihan workflow tidak menghidupkan stale signed subscriptions atau session logout.
- Failure policy/version menolak tanpa mutasi A/Q/V/RME dan tanpa menghapus input; error alasan/cara pemulihan jelas, tidak membuka PII foreign resource.

Belum ada permission baru diimplementasikan atau disetujui pada analisis ini.
