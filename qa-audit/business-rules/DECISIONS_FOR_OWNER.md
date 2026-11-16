# Keputusan untuk pemilik produk dan klinik

**Belum ada pilihan yang disetujui.** D-01–D-20 mempertahankan ID asli. Rekomendasi bukan jawaban Anda. Dokumen ini bisa dijawab bertahap; mulai D-01, D-02, D-03. D-06 dan D-13 perlu dibahas sebelum alur yang mengubah eligibility atau handoff multi-dokter dilepas.

Jawaban dapat berbentuk `D-01=A`, disertai pengecualian yang Anda inginkan. Pilihan A berarti **paket yang dijelaskan**, bukan persetujuan atas semua rekomendasi di seluruh laporan. Jika menyangkut klinis, tulis `perlu review klinis` sampai penanggung jawab klinis yang berwenang menyetujui; jangan dianggap sudah disahkan hanya karena owner memilih arah produk. Tidak ada coding dalam tahap ini.

## D-01 — Reschedule/reassignment sebelum pemeriksaan

**Pertanyaan:** Apakah perubahan booking memakai penggantian atomik tertaut atau mengedit booking lama?

**Aturan existing:** cancel dan book tersedia terpisah; tidak ada reschedule. Appointment cancel memiliki cutoff awal sesi; queue cancel staf berbeda. Visit existing tidak boleh dibatalkan. Quota grandfather milik booking lama belum diwariskan ke booking baru.

**Opsi A:** penggantian atomik oleh admin/rec, hanya BOOKED/WAITING/SKIPPED tanpa Visit; CALLED ditarik sah terlebih dahulu. Target Q BOOKED/nomor baru, harga target terkini dengan konfirmasi, old snapshot tetap. Satu eligibility grandfather lama boleh dibawa ke satu replacement aktif terverifikasi, tidak mengubah created_at lama/baru dan tidak menambah jatah baru. Patient self-service ditunda.

**Opsi B:** edit in-place dengan history assignment/price/session/version dan aturan notification/nomor yang lebih luas. **Opsi C:** tetap manual sementara; tidak disebut workflow reschedule selesai.

**Rekomendasi Codex:** A. **Alasan:** mempertahankan fakta lama, reuse services, mencegah old booking hilang saat target penuh.

**Dampak jika dipilih:** membutuhkan old/new links, UUID+intent, transaksi kapasitas dan eligibility. Pemilik harus menyetujui current-price serta carry-grandfather di paket A; bila tidak, tulis kebijakan alternatif. Tidak mengizinkan pengalihan IN_PROGRESS. **Persetujuan:** product/operasional + commercial untuk harga/kuota. **Status:** BELUM DIPUTUSKAN.

## D-02 — Dokter berhalangan dan kalender exception

**Pertanyaan:** Bagaimana klinik menyatakan cuti/libur/jam khusus dan menangani booking yang sudah ada?

**Aturan existing:** weekly session berhistori tidak dapat ganti doctor/day/time; current hours memblok booking baru di luar jam, tetapi tidak memindah histori. Tidak ada date exception/impact workflow; inactive weekly schedule masih dihitung overlap.

**Opsi A:** admin clinic mengesahkan exception clinic/doctor interval lokal `[start,end)`, whole-day direpresentasikan00:00→00:00 hari berikutnya; preview booking terdampak, tanpa mass mutation. Weekly replacement memakai effectivity yang disepakati; dokter tidak otomatis berwenang menyetujui cutinya sendiri. **Opsi B:** tolak perubahan jika ada booking. **Opsi C:** auto shift/cancel massal dengan scope terpisah.

Paket A awal: appointment masih berbasis tanggal+sesi, sehingga partial blackout memblok seluruh overlapping session untuk new bookings dan menampilkan semua booking sesi untuk review. Tidak menjanjikan exact patient times atau auto-split quota; jika Anda memilih granular split, sebutkan sebagai perubahan scope D-02.

**Rekomendasi Codex:** A. **Alasan:** menangani kejadian mendadak sambil menjaga histori dan memberi operator daftar penanganan.

**Dampak jika dipilih:** PG-02 dapat memblok availability; pasien existing diselesaikan lewat D-01/03, bukan auto no-show. Pilih siapa boleh revoke exception; rekomendasi admin clinic yang sama dengan audit. Perubahan timezone perlu preview tersendiri. **Persetujuan:** owner/operasional/product. **Status:** BELUM DIPUTUSKAN.

Subkeputusan readiness: source mengizinkan booking dokter yang model/account aktif meski belum verified atau role Doctor hilang. Rekomendasi blok booking baru bila role Doctor dicabut; booking future sebelum verification/invitation selesai harus dipilih eksplisit bersama D-15, bukan disamakan dengan boleh mulai pemeriksaan.

## D-03 — Pelayanan terinterupsi dan pergantian dokter

**Pertanyaan:** Bagaimana pause, penghentian dan handoff dicatat tanpa memalsukan pelayanan selesai?

**Aturan existing:** V hanya IN_PROGRESS/COMPLETED, R DRAFT/FINAL, satu Visit per Appointment. Dokter assigned mengelola RME; admin bukan otoritas klinis. Subscription exception hanya mengenali visit IN_PROGRESS dan route tertentu.

**Opsi A:** (A1) pause/resume same episode dengan slot tetap dipegang; interrupted terminal berbeda dari completed, draft tetap tersimpan. (A2) handoff setelah receiver accepts menggunakan old interrupted episode + appointment/visit lanjutan tertaut, authorship lama tetap, read scope handoff terpilih. Continuity entitlement satu episode sah diusulkan agar recovery tidak double-count quota; bukan blanket bypass expiry/dental.

**Opsi B:** satu visit dengan assignment history, author vs responsible doctor dipisahkan di semua policies/versions. **Opsi C:** hanya pause sementara; tidak menutup kebutuhan terminal/handoff. Forced complete/cancel untuk menutup kasus tidak direkomendasikan.

**Rekomendasi Codex:** A1 lalu A2 sebagai paket terpisah. **Alasan:** status operasional jujur, jejak penulis utuh, perubahan policy lebih terarah daripada overwrite doctor_id.

**Dampak jika dipilih:** wajib mengesahkan mapping A/Q/V/R, klinisi yang boleh terminate ketika dokter asal tidak tersedia, isi handoff, penerima yang boleh membaca, komunikasi/persetujuan pasien sesuai SOP, slot pause, quota dan expiry continuity. A1 tidak otomatis menyetujui A2. **Persetujuan:** penanggung jawab klinis + product, commercial untuk continuity; owner operasional tidak otomatis bertindak sebagai klinisi. **Status:** BELUM DIPUTUSKAN.

## D-04 — Follow-up pasien

**Pertanyaan:** Cukup instruksi dan tautan booking, atau perlu task yang dapat dilacak sampai pelayanan kontrol selesai?

**Aturan existing:** SOAP plan dan booking ada, tidak ada linked task/due/status follow-up.

**Opsi A:** task ringan oleh dokter: due window, responsible clinician, instruksi pasien allowlist; OPEN→BOOKED→COMPLETED berdasarkan visit selesai; cancelled/no-show kembali OPEN/review. **Opsi B:** instruksi+tautan saja. **Opsi C:** case management/escalation penuh.

**Rekomendasi Codex:** A. **Alasan:** dapat menemukan kontrol yang belum terlaksana tanpa scope modul besar.

**Dampak jika dipilih:** rec melihat metadata operasional, bukan SOAP; task tidak reserve slot/kuota. Tentukan apakah task boleh cancelled oleh dokter saja atau operator dengan alasan; rekomendasi pembatalan clinical plan oleh dokter, operator mengajukan. **Persetujuan:** klinis + operasional/product. **Status:** BELUM DIPUTUSKAN.

## D-05 — Scope kasir/billing pasien

**Pertanyaan:** Apakah billing pasien harus masuk scope pengembangan ini?

**Aturan existing:** Payment adalah SaaS; visit service value bukan uang diterima. Tidak ada ledger/receipt pembayaran pasien.

**Opsi A:** tetap kasir eksternal dan label service value yang benar. **Opsi B:** proyek billing pasien minimum dengan ledger/partial/discount/refund/receipt dan role terpisah. **Opsi C:** finance/insurance integration lebih luas.

**Rekomendasi Codex:** A. **Alasan:** sesuai scope clinical administration+SaaS satu lokasi; menghindari salah memakai payment subscription.

**Dampak jika dipilih:** PG-05 ditahan scope, clinical completion tidak tergantung pembayaran yang belum dimodelkan. Memilih B/C perlu requirement baru sebelum schema. **Persetujuan:** product/owner/finance untuk perluasan. **Status:** BELUM DIPUTUSKAN; default batas existing tetap dipertahankan.

## D-06 — Makna pasien nonaktif

**Pertanyaan:** Nonaktif memblok booking baru di semua kanal terverifikasi, atau hanya mengarsipkan daftar staf?

**Aturan existing:** staf explicit patient harus active; linked self-book dapat memakai profile inactive. CCL-04 sudah melarang fallback diam-diam ke orang baru.

**Opsi A:** blok new booking profile inactive terverifikasi, admin/rec reaktivasi dengan alasan/review; appointment/visit existing tidak diubah otomatis. **Opsi B:** inactive = arsip operasional; self-book tetap boleh, UI membedakan statusnya. **Opsi C:** pisahkan archive dan booking-block bila dua makna dibutuhkan.

**Rekomendasi Codex:** A untuk arti sederhana dan konsisten. **Alasan:** mengurangi bypass antar kanal, tetapi perlu jalur pemulihan agar tidak salah menolak pasien.

**Dampak jika dipilih:** self-service guard berubah; no auto-link phone/NIK, no auto-reactivate account. Alasan sensitif tidak diumbar kepada token holder/guest. Inkonsistensi aturan unique NIK lintas intake harus diputuskan, bukan dipakai untuk merge otomatis. **Persetujuan:** operasional/product, klinis bila pembatasan terkait pelayanan. **Status:** BELUM DIPUTUSKAN.

## D-07 — Dokumen hasil pelayanan

**Pertanyaan:** Dokumen apa yang diterbitkan, oleh siapa, dan kepada siapa?

**Aturan existing:** editor/preview resep dan final record ada; issued-document version/release/reprint workflow belum ada. Patient tidak otomatis bisa membuka RME penuh.

**Opsi A:** resep final issued/printable dahulu; summary pasien allowlist disetujui terpisah, issuer+record version+waktu dan superseded/reprint jelas. **Opsi B:** beberapa dokumen klinis sejak awal. **Opsi C:** full medical export/e-sign provider sebagai proyek lebih luas.

**Rekomendasi Codex:** A. **Alasan:** keluaran berguna dengan privacy/version scope yang dapat diuji.

**Dampak jika dipilih:** clinical lead menetapkan field, nomor, penerbit, otorisasi, reprint lama, koreksi/recall, penerima dan SOP distribusi. Rekomendasi baca histori tidak dipaywall seperti edit dental, tetapi kontrak harus dikonfirmasi. **Persetujuan:** klinis + product/penanggung jawab data; evaluasi ketentuan dokumen yang berlaku. **Status:** BELUM DIPUTUSKAN.

## D-08 — Laporan operasional

**Pertanyaan:** Apakah satu CSV nonmedis per periode cukup untuk kebutuhan operasional awal?

**Aturan existing:** dashboard dan filter ada; service value bukan cash, tidak ada export lengkap.

**Opsi A:** CSV operasional allowlist, timezone klinik, metrik terdefinisi. **Opsi B:** PDF/report builder. **Opsi C:** finance cash report setelah billing disepakati.

**Rekomendasi Codex:** A. **Alasan:** kecil, dapat direkonsiliasi dengan query/metrik existing.

**Dampak jika dipilih:** pilih audience owner/admin, fields aggregate vs detail, maksimum rentang/rows dan penyimpanan/expiry file; rekomendasi aggregate tanpa nama/RM/SOAP bila tujuan tidak memerlukannya. Tidak menetapkan batas arbitrer lewat laporan ini. **Persetujuan:** owner/product. **Status:** BELUM DIPUTUSKAN.

## D-09 — Histori komersial

**Pertanyaan:** Siapa perlu melihat seluruh main/add-on subscription periods dan sumber perubahannya?

**Aturan existing:** current subscription/invoice dan owner add-on history tersedia; Platform main-period timeline belum lengkap.

**Opsi A:** read-only Platform timeline + owner subset per clinic, invoice link tetap permission payment.view. **Opsi B:** support-only viewer. **Opsi C:** gabungkan viewer dan edit/recovery periode sekaligus.

**Rekomendasi Codex:** A. **Alasan:** mengurangi blind support tanpa mengubah commercial truth.

**Dampak jika dipilih:** setujui allowlist source/date/period/amount/revoked/actor/reason; field klinis tetap tidak ada. Viewer bukan izin revoke. **Persetujuan:** product/commercial untuk fields/audience. **Status:** BELUM DIPUTUSKAN.

## D-10 — Koreksi grant/refund

**Pertanyaan:** Bagaimana membatalkan satu grant salah tanpa mengganggu periode sah atau menyamakan refund record dengan transfer uang?

**Aturan existing:** grant idempotent/histori sudah benar; global downgrade dapat revoke current/future; refund record mencabut add-on tetapi main period tidak otomatis dicabut.

**Opsi A:** selective reversal manual grant dengan audit; periode lain tetap, refund dana terpisah. **Opsi B:** adjustment/credit/reschedule khusus dengan kontrak komersial. **Opsi C:** global Free/overwrite/delete sebagai undo—tidak direkomendasikan.

**Rekomendasi Codex:** A. **Alasan:** pemulihan paling sempit dan dapat diaudit.

**Dampak jika dipilih:** pilih current/future grant mana boleh, fallback bila kosong (read-only/review atau Free yang disetujui), efek add-on, grace, satu/dua approval dan komunikasi owner. Rekomendasi jangan mereshift future paid period otomatis; preserve ongoing care exception. **Persetujuan:** commercial/product; klinis bila continuity terdampak. **Status:** BELUM DIPUTUSKAN.

## D-11 — Lifecycle tenant/owner

**Pertanyaan:** Apakah transfer owner/suspend/closure perlu UI sekarang atau cukup prosedur support terkendali?

**Aturan existing:** owner explicit, clinic active flag dan subscription suspended domain ada; lifecycle lengkap dan transfer/retention workflow belum ada.

**Opsi A:** prosedur support terverifikasi, definisi visibility/suspend/security closure dahulu. **Opsi B:** UI lifecycle terbatas setelah use case dipilih. **Opsi C:** impersonation/hard-delete—tidak direkomendasikan.

**Rekomendasi Codex:** A untuk scope awal. **Alasan:** tidak menghambat klinis PG-01–03 dengan scope platform tambahan.

**Dampak jika dipilih:** status inactive tidak dipasarkan sebagai suspend menyeluruh; retention/akses medis/pending paid obligations harus diputuskan sebelum closure automation. **Persetujuan:** product/owner/platform dan penanggung jawab data/klinis sesuai efek. **Status:** BELUM DIPUTUSKAN.

## D-12 — Near-turn/reminder

**Pertanyaan:** Trigger dan kanal komunikasi apa yang diperlukan lebih dahulu?

**Aturan existing:** called/status notifications dan ETA ada; subscription reminders bukan appointment reminder.

**Opsi A:** in-app near-turn dengan threshold/cadence yang disepakati; scheduled appointment reminder dipisah. **Opsi B:** appointment reminder dahulu. **Opsi C:** external WhatsApp/SMS dengan scope/consent/biaya tersendiri.

**Rekomendasi Codex:** A. **Alasan:** reuse kanal dan security guards yang sudah terbukti.

**Dampak jika dipilih:** owner harus memilih threshold jumlah pasien atau menit, lead time dan kirim ulang sesudah skip/return/rebook; tidak ada angka default dianggap disetujui. Recipient/status dicek ulang; dedup wajib. **Persetujuan:** operasional/product, clinical review bila pesan/instruksi klinis. **Status:** BELUM DIPUTUSKAN.

## D-13 — Konflik alergi

**Pertanyaan:** Apakah revisi alergi memakai satu current per lineage atau beberapa assertions aktif yang perlu adjudication?

**Aturan existing:** historical versions disimpan; dua edit old record dapat menghasilkan dua successor aktif. RME allergy hash tidak memilih salah satunya sebagai current.

**Opsi A:** immutable lineage + expected-parent, satu current; stale edit409 dan review klinis. **Opsi B:** multi-assertion dengan UI interpretasi dan resolution. **Opsi C:** last-write-wins—tidak direkomendasikan.

**Rekomendasi Codex:** A. **Alasan:** mempertahankan histori tanpa ambiguity revisi yang diam-diam.

**Dampak jika dipilih:** clinical lead menetapkan kapan assertion sama/berbeda, siapa menonaktifkan, penanganan critical warning dan legacy tanpa lineage. Tidak auto-dedup substance atau rewrite RME final. **Persetujuan:** wajib penanggung jawab klinis. **Status:** BELUM DIPUTUSKAN.

## D-14 — Diagnosis/repair legacy

**Pertanyaan:** Apakah memulai detector read-only dan repair terpisah per kategori?

**Aturan existing:** guards/constraints mencegah sejumlah inconsistency baru; tidak ada bukti jumlah record pengguna yang rusak atau workflow repair terpandu.

**Opsi A:** fixture detector→preview/fingerprint→approval spesifik→idempotent repair kategori. **Opsi B:** manual review satu per satu tanpa automation repair. **Opsi C:** bulk auto-repair/seed ulang—tidak direkomendasikan.

**Rekomendasi Codex:** A. **Alasan:** bisa diuji tanpa menebak clinical/commercial truth.

**Dampak jika dipilih:** tetap tidak memberi izin scan/repair database pengguna sekarang. Pilih category, reviewer, snapshot/audit dan batas read access; repair clinical vs commercial berbeda penanggung jawab. **Persetujuan:** pemilik data dan klinis/commercial sesuai kategori, izin real-data terpisah. **Status:** BELUM DIPUTUSKAN.

## D-15 — Invitation dan delegasi staf

**Pertanyaan:** Perlu invitation set-password, dan apakah delegasi Clinic Admin sungguh dibutuhkan?

**Aturan existing:** admin provision Doctor/Receptionist, verification/reset tersedia, invitation/delegated-admin workflow belum lengkap.

**Opsi A:** invitation terbatas untuk dua role existing bila dipilih; tanpa delegasi admin baru. **Opsi B:** tetap provisioning/reset existing. **Opsi C:** delegated admin/custom editor sebagai scope terpisah.

**Rekomendasi Codex:** A hanya bila friction onboarding terbukti; B tetap sah sementara. **Alasan:** invitation tidak harus membuka privilege baru.

**Dampak jika dipilih:** tentukan lifetime, revoke/reinvite, kapan seat reserve/active, token single-use dan no plain-text password. Pilihan invitation tidak menyetujui admin delegation. **Persetujuan:** owner/product. **Status:** BELUM DIPUTUSKAN.

## D-16 — Packaging subscription

**Pertanyaan:** Penawaran akan mengikuti hak akses existing atau benar-benar memperkenalkan paywall realtime/dashboard?

**Aturan existing:** premium flags di katalog tidak menjadi gate actual realtime/dashboard; odontogram mempunyai gate paid+add-on yang berbeda. Trial/cutoff memakai kalender lokal, bukan selalu jam absolut.

**Opsi A:** selaraskan copy/metadata penawaran dengan rights enforced sekarang. **Opsi B:** enforce paywall dengan transisi, grandfathering dan continuity approved. **Opsi C:** pertahankan klaim tidak tepat atau matikan akses diam-diam—tidak direkomendasikan.

**Rekomendasi Codex:** A. **Alasan:** menjaga akses existing dan scope tanpa mengarang strategi harga.

**Dampak jika dipilih:** commercial owner mengonfirmasi wording dan entitlement nyata; invoice/period lama tetap. Tidak otomatis mengubah Free, grant dental eligibility, trial duration atau IN_PROGRESS exceptions. **Persetujuan:** product/commercial. **Status:** BELUM DIPUTUSKAN.

## D-17 — Custom role/permission

**Pertanyaan:** Apakah lima role tetap cukup untuk target awal?

**Aturan existing:** fixed role/policy dan security epoch S2 sudah tersedia; PR-01 tidak bergantung permission editor.

**Opsi A:** role tetap, workflow capabilities spesifik hanya jika disetujui. **Opsi B:** delegasi tindakan terbatas dengan scope. **Opsi C:** generic editor sebagai proyek security tersendiri.

**Rekomendasi Codex:** A. **Alasan:** cukup untuk clinical admin+SaaS, surface lebih kecil.

**Dampak jika dipilih:** tidak menambah role/privilege dan tidak menahan PG-01–03. Clinical reviewer tetap harus ditetapkan pada D-03, bukan otomatis semua admin. **Persetujuan:** expansion memerlukan product/security. **Status:** BELUM DIPUTUSKAN; baseline tidak diperluas.

## D-18 — Kiosk/display

**Pertanyaan:** Apakah public display yang ada cukup atau perlu kontrol perangkat/kiosk baru?

**Aturan existing:** public nonmedical queue display tersedia; bukan terminal untuk aksi staf.

**Opsi A:** gunakan display existing. **Opsi B:** managed kiosk/device/filter setelah kebutuhan dipilih. **Opsi C:** public operational controls—tidak direkomendasikan.

**Rekomendasi Codex:** A. **Alasan:** memenuhi signage tanpa menambah device management/security policy.

**Dampak jika dipilih:** tidak ada aksi queue publik, PII pasien atau modul perangkat baru. Pengujian perangkat nyata hanya diklaim saat dilakukan. **Persetujuan:** operasional/product untuk expansion. **Status:** BELUM DIPUTUSKAN; baseline dipertahankan.

## D-19 — Domain di luar scope

**Pertanyaan:** Apakah directory, branch, Edge, inventory/claims atau drug catalogue perlu dimulai sekarang?

**Aturan existing:** satu lokasi per owner; tidak ada izin menerapkan domain RPP yang lebih luas secara otomatis.

**Opsi A:** tunda seluruh domain tambahan. **Opsi B:** pilih satu use case/proyek terpisah. **Opsi C:** perluas semuanya sebagai requirement sekarang.

**Rekomendasi Codex:** A. **Alasan:** menjaga fokus workflow klinis dan integritas yang belum selesai.

**Dampak jika dipilih:** PG-01–16 tidak diblok kebutuhan branch/ledger/Edge/drug engine. Native/React/SPA bukan perubahan stack yang disetujui. **Persetujuan:** product/klinik target untuk expansion; klinis untuk domain terapi/obat. **Status:** BELUM DIPUTUSKAN; tidak diperluas otomatis.

## D-20 — First-use/UX dan bukti manusia

**Pertanyaan:** Apakah acceptance teknis per role cukup dahulu dan studi manusia direncanakan terpisah?

**Aturan existing:** DFU/CCL sudah memperbaiki errors/labels/mobile/filter; agent/browser tests tidak mengukur pemahaman manusia.

**Opsi A:** pertahankan acceptance teknis yang akurat, task-based usability study terpisah jika peserta disetujui. **Opsi B:** agent review saja dengan klaim terbatas. **Opsi C:** redesign/studi besar dengan scope baru.

**Rekomendasi Codex:** A. **Alasan:** menjaga kualitas implementasi dan kejujuran bukti.

**Dampak jika dipilih:** label/copy/error preservation yang mengikuti behavior existing tidak perlu aturan bisnis baru, tetapi tetap menunggu instruksi coding berikutnya. Study memerlukan tugas, peserta, consent dan outcome criteria; tidak dibuat hasil peserta fiktif. **Persetujuan:** product/UX untuk studi/behavior baru. **Status:** BELUM DIPUTUSKAN.

## Bentuk jawaban singkat

```text
D-01: A / B / C / perlu revisi (sebut harga, carry quota, state atau actor yang berbeda)
D-02: A / B / C / perlu revisi (otoritas exception dan aturan weekly replacement)
D-03: A1 dan/atau A2 / B / perlu review klinis (penanggung jawab, slot, handoff, quota/expiry)
D-04, D-06, D-07, D-13: pilih opsi atau tunda dengan alasan
D-05, D-17, D-18, D-19: pertahankan batas scope / usulkan scope terpisah
D-08 sampai D-16 lainnya, D-20: pilih opsi atau tunda per ID
```

Jawaban parsial tidak menyetujui ID lain. Approval dicatat dengan pemilik/tanggal dan field/state/AC yang disepakati pada instruksi berikutnya; kolom status di dokumen ini tidak diisi otomatis.
