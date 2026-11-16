# PR-01 — Laporan akhir S1 server-side WebSocket revocation

15 November 2026. **S1 selesai sebagai pembuktian teknis. PR-01 tetap OPEN; S2 tidak dimulai.**

Revokasi server-side **dapat dilakukan pada stack terpasang tanpa vendor patch atau dependency baru**, dengan app-owned signed channel identity/version serta guard pada subscribe dan sebelum private delivery. Native SDK terminate terbukti menutup dua old socket jika user_id benar-benar ada pada signed subscription data. Terminasi saja tidak cukup: authorization baru/reconnect dan signature lama tetap harus ditolak server.

Ada batas penting yang terbukti: guard read-then-send tidak menjamin nol frame dikirim setelah database commit bila frame telah diotorisasi sebelum commit. Percobaan deterministik menunjukkan frame itu keluar sebelum local server acknowledgement; event baru setelah acknowledgement terblokir pada target. Kontrak PR-01/T harus memilih batas efektif/ack atau memerlukan serialization lebih kuat. **Tidak mengklaim seluruh syarat keamanan produksi telah dipenuhi.**

## Hasil aktual

| Pemeriksaan | Hasil terbaru |
| --- | --- |
| Browser/Reverb/database-worker integration | **36 skenario unik, 36 PASS / 0 FAIL assertion pembuktian** |
| Backend assert-based checks pada clone SQLite | **15 PASS / 0 FAIL**; bukan full PHPUnit suite |
| Seluruh browser attempts termasuk eksperimen awal/retest | **51 PASS / 3 FAIL**; tiga kegagalan awal dan koreksinya tetap diarsipkan |
| Startup harness setelah formatter | Satu ERROR sebelum assertion; diperbaiki dan backend/browser terfokus diulang; incident terpisah |
| Format PHP eksperimen | Pint --dirty --format agent: PASS setelah koreksi imports |
| Queue pada akhir pengujian | 0 pending / 0 failed job |
| Integritas source existing | **1.094 file dibanding SHA-256, 0 berubah** |

**Angka PASS di atas bukan jaminan keamanan produksi.** Assertion baseline dan pendekatan yang lemah sengaja PASS ketika berhasil membuktikan private revocation **FAIL**. Hasil per pendekatan dan per skenario ada di [TECHNICAL_EXPERIMENTS](TECHNICAL_EXPERIMENTS.md) dan [APPROACH_COMPARISON](APPROACH_COMPARISON.md); rekonsiliasi mesin di [summary.json](evidence/summary.json).

## Validasi sepuluh sasaran keamanan

| Sasaran | Bukti / status S1 |
| --- | --- |
| Subscription baru setelah hak relevan dicabut ditolak | PASS kandidat guard: HTTP clinic403 atau session401; stale signed subscribe dan roleless own-user reconnect ditolak Reverb4009 |
| Old socket tidak menerima private event baru setelah revokasi efektif | PASS untuk fresh checks/event sesudah local server fence pada satu node; **FAIL bila commit saja dianggap batas nol-send** untuk frame pre-authorized; keputusan kontrak masih diperlukan |
| Pengguna sah tetap menerima | PASS Receptionist kontrol, Patient notification, authorized reconnect dan recovery setelah authority read pulih |
| Isolasi tenant | PASS foreign auth403, nol cross-private, foreign clinic sendiri tetap menerima |
| Multi-tab | PASS dua browser/context dan dua tab satu session |
| Reconnect aman | PASS guard menolak revoked subscribe, fresh authorized reconnect menerima; mode terminate-only justru membuktikan celah own-user reconnect |
| Tidak bergantung JavaScript logout | PASS guard/invalidation server, termasuk ketika terminate401/network fail; browser tidak diminta logout untuk menghentikan delivery |
| Tidak membocorkan informasi medis | PASS pada event check-in/cancel yang diuji: public/private queue enam key nonmedis, QueueStatus sembilan dan AppointmentStatus delapan key existing; nilai booking code/token tidak disalin ke evidence; tidak mengklaim audit semua payload masa depan |
| Tetap aman ketika revokasi/control gagal | PASS 401 control, transport failure, actual authority query exception dan backlog; unknown private delivery ditolak. Node partition/multi-node ack belum diuji |
| Autosave/filter/session existing | PASS autosave sebelum revokasi, 401 sesudah password reset dengan teks/foto pending tetap, filter/caret/fokus tetap saat realtime refetch, kontrol sah tidak ikut logout |

## Rekomendasi dan kebutuhan produksi

Pilih **signed identity + monotonic durable epoch + subscribe/pre-send fresh authorization**, kemudian server invalidation/termination untuk cleanup dan acknowledgement sesuai kontrak. Producer recipient checks memperkuat eligibility users notification, tetapi shared clinic tetap memerlukan transport guard.

S2 perlu additive user authorization version, mutation/version atomic pada seluruh jalur user dan Spatie, driver/auth-response dan ChannelManager milik aplikasi, fresh policy serta failure handling sebelum send, durable cleanup/ack/retry, dan UI recovery tanpa menghapus editor. Endpoint terminate native user-wide perlu ditangani agar stale retry tidak mengganggu login baru. Tidak perlu mengubah SubscriptionService, F1, histori komersial/medis atau menambah permission editor. [IMPLEMENTATION_PROPOSAL](IMPLEMENTATION_PROPOSAL.md) memuat caller, risiko dan seluruh test wajib S2.

Kebijakan acknowledgement, timeout yang diterima, cakupan logout/password reset dan eligibility notifikasi yang masih sah memerlukan evaluasi; usulan D-01–D-20 tidak dianggap disetujui. Tidak memulai S2 berdasarkan keberhasilan eksperimen.

## Artefak dan batas

- [ARCHITECTURE_ANALYSIS](ARCHITECTURE_ANALYSIS.md): seluruh private families, versi dan extension point source terpasang.
- [REVOCATION_REPRODUCTION](REVOCATION_REPRODUCTION.md): langkah dan hasil baseline, frame sebelum/sesudah commit/ack.
- [TECHNICAL_EXPERIMENTS](TECHNICAL_EXPERIMENTS.md): 36 skenario, raw attempts, fault dan cara reproduksi.
- [APPROACH_COMPARISON](APPROACH_COMPARISON.md): pendekatan yang benar-benar dicoba dan alasan pilihan.
- [IMPLEMENTATION_PROPOSAL](IMPLEMENTATION_PROPOSAL.md): perubahan produksi yang baru diusulkan dan gate S2.
- [evidence](evidence/): hasil JSON, metadata wire, screenshot fixture, hashes dan lifecycle proses.

Source produksi, vendor yang diperiksa, dependency, build existing, `.env` dan laporan lama tetap. Eksperimen memakai clone database/storage dummy terisolasi; tidak mengakses database pengguna. PG-17/PG-18 tidak diuji ulang atau diubah. Tidak commit/push/deploy dan tidak ada load/stress/production-readiness testing. Proses HTTP/Reverb/worker eksperimen dihentikan sesudah pengujian; runtime dummy dipertahankan untuk penelusuran.

**NOT TESTED pada S1:** MySQL serialization/concurrency, Redis/multi-node revocation acknowledgement, TLS/proxy, restart di tengah control/ack, full application suite baru, email password-broker/profil end-to-end, semantics private-cache/presence yang tidak digunakan aplikasi, serta SLA operasi lima detik. Ini wajib dibatasi atau diuji sesuai desain S2; hasil historis bukan bukti baru.
