---
name: ClinicOS
description: Design contract frontend ClinicOS berdasarkan S2-FE-02 dan Developer Contract ClinicOS v3.0.
source: 08_Vioni_FE_Prototype_Frontend_SemiFormal_BW.pdf
stack: Laravel, Blade, Livewire, Tailwind CSS, MySQL
colors:
  brand-primary: "#287F78"
  brand-primary-hover: "#206A64"
  brand-secondary: "#3D5F5C"
  brand-accent: "#D89B68"
  background: "#F7F8F5"
  surface: "#FFFFFF"
  text-primary: "#253331"
  border: "#DDE5E1"
typography:
  sans: "Plus Jakarta Sans, Inter, system-ui, sans-serif"
spacing:
  1: "4px"
  2: "8px"
  4: "16px"
radius:
  md: "8px"
controls:
  md-height: "40px"
---

# Panduan Desain ClinicOS

## 1. Acuan dan prinsip

Panduan ini mengikuti developer contract, Frontend Definition of Done, screen-to-component map, dan Figma Component Inventory - Production pada PDF sumber. Spesifikasi ClinicOS v3.0 tetap menjadi acuan utama bila terjadi perbedaan.

Gunakan permukaan hampir putih, kartu putih, border tipis, dan teal sebagai aksi utama. Prioritaskan keterbacaan, konteks pasien, status yang jelas, dan kepadatan informasi yang wajar. Jangan membawa elemen dekoratif landing page atau data contoh ke layar operasional.

## 2. Design tokens

| Token | Nilai | Penggunaan |
| --- | --- | --- |
| `color.brand.primary` | `#287F78` | Aksi utama, link aktif, fokus |
| `color.brand.primary-hover` | `#206A64` | Hover dan pressed |
| `color.brand.secondary` | `#3D5F5C` | Aksen dan teks pendukung |
| `color.brand.accent` | `#D89B68` | Aksen perhatian non-kritis |
| `color.background` | `#F7F8F5` | Latar aplikasi |
| `color.surface.default` | `#FFFFFF` | Kartu, panel, dialog, input |
| `color.text.primary` | `#253331` | Teks utama |
| `color.border.default` | `#DDE5E1` | Border dan divider |

Font: `Plus Jakarta Sans, Inter, system-ui, sans-serif`. Spacing dasar memakai 4px (`4`, `8`, `16`, lalu kelipatannya). Radius standar 8px dan tinggi kontrol medium 40px. Gunakan semantic token di screen; hindari hard-coded value.

## 3. State dan API komponen

```text
status: idle | loading | success | empty | error
mutation: idle | submitting | success | error
permission: hidden | readonly | editable
sync: synced | dirty | saving | unsynced | conflict
connectivity: online | degraded | offline
```

- Gunakan prop semantic seperti `variant="danger"`, bukan raw color.
- Button harus semantic dan mendukung loading, disabled, serta focus.
- Komponen generic tidak mengetahui domain; domain component boleh melakukan composition.
- Jika elemen muncul pada 3 screen atau lebih, evaluasi menjadi reusable component.
- Jika behavior/domain berbeda, jangan memaksa satu komponen monster.

## 4. Error, permission, dan data sensitif

| Kondisi | Perilaku UI |
| --- | --- |
| Validation | Field error terstruktur ditempel ke field |
| Business rule | Human-safe message berdasarkan error code |
| 403 | Permission state berbeda dari not found |
| 404 | Resource not found |
| 409 / version mismatch | Conflict dan recovery aman |
| 429 | Panduan retry bila relevan |
| 5xx | Pesan umum; detail hanya di log |

Request ID hanya ditampilkan bila membantu support tanpa leak data. Sensitive values tidak boleh masuk URL atau telemetry. Telemetry tidak boleh mengirim diagnosis, konten medis mentah, atau data pasien tanpa dasar yang tepat.

## 5. Screen-to-component map

| Screen | Komponen utama |
| --- | --- |
| Dashboard | `AppShell`, `PageHeader`, `MetricCard`, `ChartCard`, `QueueMiniList`, `AttentionList` |
| Patient List | `SearchInput`, `FilterBar`, `DataTable`, `StatusBadge`, `Pagination`, `EmptyState` |
| Patient Detail | `PatientIdentityHeader`, `CriticalAlert`, `Tabs`, `InfoList`, `VisitTimeline` |
| Registration | `Stepper`, `PatientContextCard`, `Autocomplete`, `DateTimePicker`, `ReviewSummary` |
| Queue | `ConnectionStatus`, `CurrentCallCard`, `QueueTable`, `QueueActionButton` |
| Medical Record | `PatientContext`, `AllergyAlert`, `VisitHistory`, `MedicalEditor`, `SaveStatus`, `FinalizeDialog` |
| Billing | `LineItemTable`, `MoneySummary`, `PaymentMethodSelect`, `PaymentButton`, `ReceiptAction` |
| Reports | `ReportFilter`, `MetricCard`, `ChartCard`, `RankingTable`, `ExportButton` |
| Settings | `SettingsNav`, `RoleTable`, `PermissionMatrix`, `ConfirmDialog` |
| Mobile | `MobileAppBar`, `AppointmentCard`, `QueueCard`, `DoctorCard`, `SlotPicker`, `BottomNav` |

## 6. Responsive dan aksesibilitas

- `AppShell` mendukung desktop, collapsed sidebar, dan mobile.
- Mobile memakai `MobileAppBar`, `BottomNav`, kontrol keyboard-friendly, dan safe area.
- Jangan mengandalkan hover untuk fungsi penting; uji desktop kecil, mobile keyboard, overflow, dan orientasi bila relevan.
- Filter aman boleh disimpan di URL untuk share/back behavior; nilai sensitif tidak boleh.
- Semua kontrol keyboard-accessible dan memiliki focus-visible.
- Icon-only control memiliki accessible label; status dibedakan dengan teks/ikon selain warna.
- Dialog/drawer mengelola focus, Escape, dan focus return.
- Toggle memiliki accessible name dan status sinkron; toast memakai live region.
- Targetkan area interaksi minimal 44 x 44px pada mobile.

## 7. Frontend Definition of Done

- Komponen reusable dan token dipakai.
- Keyboard/focus, ARIA label, permission state, loading/error/empty/disabled terverifikasi.
- Double-submit diblokir dan network failure memiliki recovery.
- Back navigation mempertahankan state bila diperlukan.
- Responsive small desktop dan mobile diuji.
- Data sensitif tidak masuk URL/log.
- Conflict, autosave, dan sync state ditampilkan bila screen mendukungnya.

## 8. Inventory dan aturan konsistensi

Komponen memiliki owner dan status `Planned`, `Building`, `Ready`, atau `Deprecated`.

Core/navigation: `Color Variables`, `Typography Styles`, `AppShell`, `Sidebar`, `Topbar`, `Breadcrumb`, `Tabs`, `BottomNav`, `Button`, `IconButton`, dan `Menu`.

Forms/data/feedback: `Input`, `Textarea`, `Select`, `Autocomplete`, `DatePicker`, `TimePicker`, `SlotPicker`, `DataTable`, `Pagination`, `FilterBar`, `Toast`, `Alert`, `Dialog`, `Drawer`, `Skeleton`, `EmptyState`, dan `ErrorState`.

Domain: `PatientIdentityHeader`, `DuplicatePatientReview`, `AppointmentCard`, `CalendarBlock`, `QueueItem`, `CurrentCall`, `AllergyAlert`, `MedicalEditor`, `SaveStatus`, `PrescriptionEntry`, `BillingSummary`, `PaymentCTA`, `PermissionMatrix`, dan `AuditEventRow`.

Output templates: `Invoice`, `Receipt`, `QueueTicket`. Mobile: `ClinicCard`, `ClinicSearchFilter`, `DoctorCard`, `AppointmentCard`, `QueueTracker`.

Gunakan nama prop yang konsisten antara Figma dan code. Jangan menyatakan komponen `Ready` tanpa owner dan coverage state. Jangan menganggap prototype sebagai bukti backend realtime, penyimpanan form, keamanan, atau kepatuhan klinis.
