# Load and reliability report

Gate: **FAIL terhadap asumsi realtime p95**. Bukan sertifikasi kapasitas produksi. Semua angka di bawah berasal dari run, bukan target yang diklaim sudah disepakati bisnis.

## Asumsi SLO

Pengguna tidak mempunyai target operasional dan meminta nilai standar. Tidak ada satu angka universal untuk semua klinik. Asumsi awal: request interaktif p95 <= 2 s, p99 <= 5 s; 5xx/network error <= 0,1%; event antrean p95 <= 2 s; nol pelanggaran integritas/akses. Target availability bulanan, pengguna unik produksi, booking/jam dan RPO/RTO belum ditetapkan. Pendekatan SLI/SLO mengikuti [Google SRE](https://sre.google/sre-book/service-level-objectives/); **angka target adalah asumsi audit ini**, bukan angka baku dari sumber tersebut.

## Workload dan lingkungan

MySQL nyata, empat backend PHP independen di belakang proxy loopback, satu database worker, Reverb nyata, database session/cache, 2.003 pasien sintetis pada klinik load. Lima akun berbeda dengan **20 sesi login independen**: receptionist, doctor, patient, clinic admin, owner persona. Owner tetap role Clinic Admin; Platform Admin tidak menjadi bagian mix load ini dan diuji pada UAT.

Closed loop tanpa think time, tahap 1/5/10/20 request aktif sekitar 15 detik per tahap. Ini stress singkat, bukan simulasi 20 orang unik, arrival-rate tetap, uji 8 jam, atau beban produksi lewat jaringan internet. Authentication setup dilakukan sebelum pengukuran tahap baca.

Endpoint baca: dashboard, pasien, appointments, antrean, medical-record list, staff, jadwal dan histori pembayaran owner. Tidak mengambil SQL, bindings, nama pasien, isi medis atau URL bertoken ke telemetry. Durasi SQL diukur dengan DB listener sebagai waktu query yang diamati client, bukan profiler internal server.

## Hasil aktual

| Sesi aktif | Request | Throughput req/s | p50 ms | p95 ms | p99 ms | HTTP 200 | HTTP 429 | 5xx/network |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | 172 | 11,44 | 73,5 | 124,4 | 340,4 | 172 | 0 | 0 |
| 5 | 778 | 51,50 | 83,3 | 155,8 | 333,0 | 725 | 53 | 0 |
| 10 | 845 | 55,05 | 166,5 | 337,1 | 507,8 | 757 | 88 | 0 |
| 20 | 872 | 55,60 | 281,7 | 721,5 | 802,9 | 777 | 95 | 0 |

Total **2.667 read request**, 2.431 HTTP 200, **236 HTTP 429**, nol 5xx/network error. Halaman subscription memakai limiter `payment-status` per akun; empat sesi owner melakukan pembacaan berulang tanpa think time dan terkena limiter. Angka 429 tetap disertakan. Ini pembatasan yang disengaja, bukan alasan menghapus limiter. Skenario yang mengharapkan seluruh read 200 **FAIL**; SLO 5xx numerik **PASS**. Percentile agregat mencakup respons 429 yang cepat sehingga tidak boleh dipakai sebagai jaminan latency semua transaksi sukses atau kapasitas klinik produksi.

| Metrik database | 1 sesi | 5 sesi | 10 sesi | 20 sesi |
| --- | --- | --- | --- | --- |
| Query p95 ms | 1,87 | 1,63 | 1,65 | 1,72 |
| Query p99 ms | 24,12 | 20,56 | 21,15 | 21,23 |
| Total SQL/request p95 ms | 39,43 | 36,64 | 37,69 | 37,32 |
| Rata-rata query/request | 21,0 | 21,1 | 20,5 | 20,5 |

Burst tulis tambahan: **4 sesi resepsionis, 40 booking dan 40 check-in**. Seluruh booking 302 sesuai kontrak redirect, seluruh check-in 200, tepat 40 appointment dan 40 queue ditambahkan, tidak ada nomor antrean ganda/mismatch dokter. Booking p50 **656,8 ms**, p95 **1.617,5 ms**, p99 **1.646,3 ms**. Waktu keseluruhan burst mencakup pembacaan fixture untuk observasi, sehingga bukan benchmark throughput booking murni.

Seluruh 40 event WebSocket diterima: p50 **1.270 ms**, p95 **2.283 ms**, p99 **2.590 ms**, nol event hilang. **FAIL** terhadap asumsi p95 <= 2.000 ms. Pengukuran dari sebelum request check-in sampai handler browser menerima event, termasuk antrean dan scheduling browser; bukan waktu server broadcast saja.

## CPU, memori dan konsistensi

89 sampel selama 88,91 detik untuk delapan proses service milik audit. Rata-rata konsumsi CPU agregat **1,709 core ekuivalen**, sekitar **10,68%** dari 16 logical processor. Peak jumlah working set proses service **361,7 MiB**. Tidak mencakup Chrome, load driver, PHP CLI observer, cache OS, atau seluruh memori mesin; working set bersama dapat terhitung lebih dari sekali. Tidak dibuat target CPU/memori kelulusan yang tidak disepakati.

Setelah beban: pasangan appointment/queue konsisten, nomor antrean unik, kuota tidak terlampaui, versi RME final tetap ada. Worker kemudian terbukti drain tanpa failed job; schema/recovery sesudah beban menemukan nol orphan dan tenant mismatch.

Bukti: [load-results.json](evidence/load-results.json), [resource-samples.json](evidence/resource-samples.json), [recovery](evidence/recovery-results.json), [harness](tests/load.mjs).

## Batas dan rekomendasi

**NOT TESTED:** spike ratusan pengguna unik, soak harian/mingguan, cold-start deployment, disk penuh pada server sesungguhnya, network partition, horizontal scaling, error budget bulanan, serta latency p95 khusus respons 200 terpisah dari semua respons. Run awal yang mengakses route owner tidak ada disimpan sebagai `load-harness-attempt1.json`; tidak dipakai sebagai hasil final.

Tetapkan volume operasional dan think time yang realistis, pisahkan profil clinical read dari payment-status polling, ukur respons sukses tersendiri, dan retest pada stack/server staging yang didukung. Profilkan worker polling/refetch/fanout sebelum menetapkan tuning. Tidak mengubah source atau limiter untuk memperoleh hasil hijau.

```powershell
python qa-audit/production-readiness/tests/runtime.py node qa-audit/production-readiness/tests/load.mjs
```
