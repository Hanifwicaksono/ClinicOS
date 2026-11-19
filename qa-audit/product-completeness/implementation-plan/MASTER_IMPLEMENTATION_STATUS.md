# ClinicOS — Master implementation status

Date: 18-19 November 2026, Asia/Bangkok. This report records this implementation session, separately from historical audits.

Local technical integration: PASS for the executed scenarios. Operational release: PARTIAL; production rollout/transport and human acceptance gates below have not been performed. No product decision within the six-stage implementation remains BLOCKED.

## Authority and decisions

- The user explicitly requested the six stages in the attached MASTER IMPLEMENTATION PROMPT.
- In this conversation the user approved D-06–D-20 and Appendix K. D-01–D-05 retain their approved business rules from DOCX v2.1.
- The user explicitly authorized the repository's PHPUnit patterns as the substitute for the unavailable testing-best-practices skill.
- The user selected case-specific Clinical Lead appointments approved by the doctor responsible for the Visit. This does not create clinic-wide medical access or an administrative medical bypass.
- Historical documents saying these decisions were unapproved are historical context, not the current decision register.
- The user explicitly approved K-11 parameters on 19 November: near-turn N=3, RETURNED resend cooldown=30 minutes, appointment H-1 at 18:00 clinic-local time, unbooked follow-up H-3 before the last allowed local control date. No external messaging channel or late-booking catch-up policy was added.
- Laravel/Blade/Tailwind, five existing roles, one clinic per owner, medical authorization and commercial/clinical separation remain invariants. No commit, push or deployment is authorized.

## Dependency map and checklist

1. Patient profile eligibility and allergy revisions → booking and clinical editor.
2. Booking/Queue/SubscriptionService → atomic appointment replacement; availability exceptions → all new booking entry points.
3. Availability and replacement → pause/interruption/continuation; clinical assignment, consent and entitlement → handoff access.
4. Final RME/prescription → immutable released documents; source Visit + replacement/continuation → follow-up tracking.
5. Actual services/snapshot prices → patient ledger, payment verification, adjustments, receipts and immutable closing → financial reports.
6. Stable appointment/queue/follow-up state + explicit delivery settings → deduplicated reminders and near-turn notifications.

For each stage: backend and schema → policies/request validation → UI and recovery → focused tests → MySQL races when relevant → browser tasks/responsive checks → regression → report. Do not label a stage PASS solely from unit/feature results.

## Baseline actually run

- Default PHPUnit suite before edits: 431 passed, 2,830 assertions, SQLite in-memory.
- Dependency versions checked with composer show --direct: Laravel 13.34.0, Livewire 3.8.10, Volt 1.11.2, PHPUnit 12.5.37, Pint 1.32.1.
- Git working tree was clean. The .ai/rules directory was absent.

## Stage 1 — PASS for the technical scenarios tested

Implemented:

- Allergy revisions use a linked lineage in the existing table, a unique predecessor, revision number, expected version, clinical reason, actor/time and an idempotent request fingerprint. A stale parent returns 409. Historical clinical values remain, superseded entries cannot be edited, and active critical warnings exclude superseded versions.
- Legacy entries remain independent; no identity/substance-based lineage inference or bulk backfill. Legacy critical entries display “Perlu verifikasi”.
- The editor retains input after conflict and offers the latest authorized view in another tab for comparison. Historical entries show revision metadata.
- Explicit and account-linked patient booking use the same active-profile predicate and fresh patient lock. Patient profile updates lock clinic then patient and audit activation changes atomically. Original successful booking retries remain idempotent; prior bookings and guest identity behavior remain intact.
- The patient booking screen explains the restriction and disables new submission when the linked profile is inactive.

Changed areas: Patient/PatientAllergy models; BookingService/VisitWorkflowService; receptionist patient update; doctor allergy request/controller; allergy editor/banner; RME request feedback; public booking form; feature and Chrome tests.

Migration: 2026_11_18_142708_add_revision_lineage_to_patient_allergies_table.php. Additive nullable self-references, revision metadata and uniqueness constraints. Executed only on disposable test databases, not the user's application database.

Verification completed so far:

- StageOneIntegrityTest: 8 passed, 53 assertions after the legacy-warning addition.
- Default regression suite after the initial implementation: 439 passed, 2,881 assertions. Later refinements are being rechecked.
- MySQL two-process tests: 3 passed, 20 assertions. Cases: two different revisions of one parent; identical concurrent retry; booking versus actual patient-update controller deactivation. Both workers rendezvous at a barrier before mutating.
- The normal MySQL port was unavailable. A separate disposable MySQL 8.0.30 instance was initialized in a temporary directory on port 33179; generated clinicos_stage1_test_* schemas were created and removed there. Application database not used.
- Initial Chrome workflow with added allergy-conflict recovery passed; JS tests also passed (11 combined test cases). Screens at 375/768/1024/1440 had no horizontal overflow in the checked clinical/booking/patient flows.
- An additional inactive-profile browser scenario initially failed because its test URL was incorrect. The test URL was corrected to the named route's /clinic/{slug}/booking path; rerun pending.
- npm run build passed. Pint fixed changed PHP; final formatting/diff checks pending.

Final refinements preserve the original revision's clinical active/inactive value; current applicability is determined by the successor link. The latest focused run of the allergy and partial-workflow tests passed (20 cases, 158 assertions). Chrome exercised deactivation, disabled self-booking, access to the existing appointment, and reactivation through the real forms. Desktop and mobile screenshots were inspected. MySQL races passed again on the disposable instance. The reusable race runner is now WorkflowMysqlConcurrencyTest; its first three cases cover Stage 1.

This PASS describes technical acceptance on synthetic fixtures. It is not clinical sign-off, a human usability study, or a claim that the application database has been migrated.

## Stage 2 — PASS for the technical scenarios tested

Implemented atomic old/new appointment replacement through BookingService, with a read-only preview and native POST confirmation. Old service/price/date/call history remains. New queue numbers are allocated at commit. Same-service prices are protected, service changes require consent and a current quote, CALLED withdrawal is atomic, and failed target creation restores the original call/booking. Confirmed same-day presence may retain check-in; cross-day replacements reset it. Old guest access does not grant access to the replacement. Inactive profiles require explicit staff review for replacement and are not reactivated.

Grandfathered SaaS quota uses the original entitlement timestamp through replacement chains. This does not add doctor-session capacity or create a new patient benefit. Clinic/schedule/queue lock ordering was coordinated with queue mutations and Visit start.

Doctor reports create immediate session blocks for any overlapping interval, including pending reports. Owner/Admin verification precedes the affected-patient communication workflow. Doctor requests to withdraw a report do not unblock it; authorized reopening leaves other overlapping reports effective and does not restore replaced bookings. Communication entries are append-only and idempotent. Public slot choices receive blocked dates, never private absence reasons, and BookingService rechecks eligibility on commit.

New files: AppointmentReplacement and DoctorAbsence models/factories; AppointmentReplacementController and DoctorAbsenceController; ReplaceAppointmentRequest and StoreDoctorAbsenceRequest; DoctorAvailabilityService; replacement/confirmation and absence views; SchedulingRecoveryTest. Existing BookingService, QueueWorkflowService, VisitWorkflowService, SubscriptionService, Appointment, route definitions, booking picker and entry-point views were integrated.

Migration: 2026_11_18_144539_create_appointment_replacements_and_doctor_absences_tables.php. Adds entitlement timestamp and replacement/absence/contact ledgers. Tested only on disposable SQLite/MySQL databases.

Actual checks:

- SchedulingRecoveryTest: 13 passed, 73 assertions, including capacity rollback, price changes, call withdrawal, stale state, inactive-profile replacement, protected quota, adjacent intervals, roles, tenant boundaries and native-form recovery.
- Scheduling/QueueManagement/VisitWorkflow subset: 41 passed, 295 assertions at that checkpoint.
- WorkflowMysqlConcurrencyTest: 7 passed, 49 assertions; includes four Stage 2 races: duplicate replacement, last target slot, replace versus start, absence versus booking.
- Full PHP run including qa-audit/tests: 481 cases, 474 passed, 7 explicitly skipped MySQL opt-in cases, 3,100 assertions. The seven MySQL cases were run separately and passed.
- Chrome performed reschedule, doctor report, public slot suppression/private-reason protection, admin verification, receptionist communication, another linked reschedule, doctor withdrawal request, admin reopening, and verified that the replacement remained on its new date. Responsive absence screens were checked at 375/768/1024/1440, with no JavaScript exceptions. A subsequent check of the final pending/error-feedback refinements is running.
- Build passed; Pint and diff checks passed at checkpoints and will be repeated for subsequent edits.

Final native-form pending and validation recovery refinements passed the 13-case focused PHP suite and the expanded Chrome journey again. Latest Chrome fixture screenshots: C:/Users/User/AppData/Local/Temp/clinicos-browser-bi65GN. No application database migration was run.

## Stage 3 — PASS for the technical scenarios tested

Responsible Visit doctors approve case-specific Clinical Leads with purpose, clinician, service/case, evidence, approver, primary/alternate role, and expiry/revocation. Care Leads read this case and write independent coordination notes; allergy-only Leads cannot read SOAP/photos or other episodes. Administrative users see operational metadata. Revoked accounts/clinical profiles gain no write authority.

Implemented HOLD/RELEASE, ready ordering, cross-day same-Visit resume with real session capacity, monotonic queue counters, terminal INTERRUPTED, separate CARE/DOCUMENTATION tasks, author-only interruption documentation finalization/correction, minimum released handoff summary and critical-allergy snapshot, patient consent, receiver acceptance, protected continuation bookings, linked children only at CALLED start, and cancellation/rebooking/replacement link preservation. Same-service children consume no second SaaS visit quota; new services require current commercial rights/tariff/consent. Decline/cancellation/expiry removes pre-start recipient access. Changed source RME/allergy snapshots block acceptance/start until an updated request is released.

Pause reminders follow the explicit estimate, then the approved 15-minute escalation default; no automatic clinical transition. Database notifications and durable broadcast jobs recheck current access/status. Migration 2026_11_18_152110_add_care_continuity_workflows.php is additive and preflights duplicate active sessions. MySQL uses an indexed virtual queue key to preserve existing foreign keys; the stored-column limitation was verified against https://dev.mysql.com/doc/refman/8.0/en/create-table-generated-columns.html. Real MySQL checks exposed and corrected an overlong index name, generated-column/FK incompatibility and a repeatable-read stale snapshot in resume/call. Reads now follow the clinic lock; replay-event reads lock the row.

Actual checks:

- CareContinuityTest: 13 passed, 121 assertions. Covers HTTP transitions, slot modes/order, cross-day capacity, tenant/clinical scopes, separate tasks, author-only finalization/correction, handoff privacy/acceptance/consent, expired-subscription continuity, new-service price, stale snapshots, cancellation/rebooking/replacement and reminder deduplication.
- WorkflowMysqlConcurrencyTest: 11 passed, 74 assertions. Four Stage 3 two-process races cover pause retry, resume versus call, handoff-book retry, and handoff versus ordinary last-slot booking.
- Full PHP regression checkpoint: 498 cases, 487 passed, 11 explicit MySQL opt-in skips, 3,223 assertions. Subsequent focused refinements also passed the continuity suite.
- Chrome: draft autosave, pause/resume, attending Lead approval, Lead interruption, released summary, receiver acceptance, staff reservation/check-in, receiver call/start, linked child and original still INTERRUPTED. Staff summary privacy checked. Responsive screens 375/768/1024/1440 passed overflow checks; mobile care/allergy screenshot inspected. Latest artifacts: C:/Users/User/AppData/Local/Temp/clinicos-browser-JxUhFn. Receiver competence/session fixtures exist only in the disposable test database.
- Build/Pint passed; no diff whitespace errors. No application migration. Real Reverb/worker/scheduler delivery, human usability, clinical/SOP sign-off remain unverified.

## Stage 4 — PASS for the technical scenarios tested

Added immutable IssuedPrescription snapshots over the existing Prescription/RME source, with author-only final-record issuance, current allergy/version checks, idempotent requests, clinic-scoped unique document numbers, explicit replacement lineage/reason, recall and patient release. Reprint uses the selected snapshot and current validity status; model updates cannot silently change issued contents. Patient access follows verified ownership and explicit release. Receptionists can print/release-hand-over only documents already released by the author, with identity verification for handover; clinic/platform administrators receive no automatic prescription access. Print/access/handover audit records contain IDs/status, not prescription or bank secrets. Print uses authenticated Blade; no e-signature or legal approval is asserted.

Follow-up instructions/ranges are approved by the source doctor and released separately from SOAP. Tasks preserve booking history, allow one active booking, survive atomic replacement, reopen for review on cancellation/no-show, require receiver acceptance before changing responsibility, and require clinical risk review before cancelling a patient's refusal. Completion requires the linked completed control; interrupted-control continuations need explicit confirmation that the control goal was met. Inactive profiles cannot bypass new-booking rules. OVERDUE is an actionable indicator at the exclusive clinic-local cutoff. Staff communications are visible separately from private clinical decisions. Approved-session replacement options are constrained in both UI and backend.

Migration: 2026_11_18_162221_add_issued_prescriptions_and_follow_ups.php. Additive documents, follow-up tasks/events/booking links and uniqueness constraints. No application database migration.

Actual verification:

- ClinicalOutputsTest: 11 passed, 88 assertions. Latest combined clinical-output/scheduling subset: 24 passed, 161 assertions. Earlier combined output/continuity subset: 23 passed, 208 assertions before the additional immutable-model check.
- Full PHP checkpoint: 512 cases, 497 passed, 15 explicit MySQL opt-in skips, 3,310 assertions. Later focused refinements passed as above; final cumulative suite will be rerun after subsequent stages.
- MySQL run: 15 of 16 passed; the remaining case failed in fixture setup because it attempted a duplicate doctor schedule. A distinct valid target session fixed the fixture; focused rerun passed (1 case, 6 assertions). The passing cases include issuance retry, competing issuance, canonical integrity after MySQL JSON key normalization, follow-up duplicate booking, and the legacy-duplicate migration preflight. The corrected last-slot follow-up race also passed. The preflight query was corrected for ONLY_FULL_GROUP_BY after the first new-schema run exposed it.
- Chrome completed RME finalization, author issuance/release, selected-version print page, receptionist verified handover with durable confirmation, doctor follow-up instructions, staff booking, linked reschedule, cancellation, review reopening and preserved history. Print/follow-up screens passed 375/768/1024/1440 overflow checks; mobile screenshots inspected. Artifacts: C:/Users/User/AppData/Local/Temp/clinicos-browser-O7WGdi.
- Build/Pint passed. Actual paper printing, legal/SOP validation, clinical sign-off and external notification transport remain unverified.

## Stage 5 - PASS for the technical scenarios tested

Patient billing is separate from SaaS Payment/DOKU. One append-only bill follows validated replacement/protected-continuation chains; unrelated appointments do not share a wallet. Original prices remain immutable. Completed care finalizes the lawful clinical basis atomically. Interrupted care requires the responsible author to sign actual services; additional services require current pricing, competence, commercial rights and patient fee consent. Subsequent charge correction follows a newer medical amendment, with explicit write-off reversal when required. Staff and administrators cannot read clinical pricing evidence; doctors see relevant fees without cash/approval details.

Integer minor units separate billed amount B, allocated payment P, write-off W, outstanding U, excess allocation and unallocated verified funds. Manual CASH requires actual receipt confirmation; QRIS/transfer stay pending until verified. A late verified credit remains real money even if the bill was settled meanwhile; it is not silently allocated over write-off. Deposits require an explicitly eligible service. Discount, write-off, reversal and refund requests have distinct approval rules and immutable entries. Refund approval reserves the source but creates no money-out entry; actual verified completion does. Concurrent reservations cannot spend the same source twice. Self-approval follows user identity even for a multi-role account. Administrative collection/review tasks never block booking or clinical completion.

Issued receipts/statements preserve their own identity and snapshot. Statements/write-off are not receipts. Corrections retain the old document with a validity marker. Cash closing freezes a ledger high-water mark, pending items and actual counted amounts. Later verification/refund enters the next closing. Discrepancies or opening-balance changes require documented administrator review; approved snapshots cannot be edited.

Activity, queue, completed-service and financial reports have separate definitions. Exports are queued on the reports queue, contain purpose-specific aggregates, and publish a private CSV only after the complete snapshot/file is ready. Download rechecks requester, tenant, current permissions, explicit expiry, path and hash. Receptionists cannot export financial/service reports. Formula-like CSV text is escaped, and typed negative money stays numeric. No patient identities, clinical contents or bank references are exported. The UI shows the same stored metrics as the downloaded artifact.

Migration: 2026_11_19_034354_add_patient_billing_and_clinic_reports.php. Adds billing/transaction/approval/entry/task/document/closing/export tables and the explicit per-service deposit flag. No application migration. Billing/report controllers, policies, services, jobs, factories, Blade forms and dashboard/appointment/RME links are integrated. Security responses use private/no-store. Collection of existing obligations and report requests remain available through expired SaaS entitlement; extra service billing still checks current rights.

Actual verification:

- PatientBillingTest: 14 passed, 102 assertions (including immutable issued identities/content). ClinicReportsTest: 6 passed, 37 assertions. Latest combined billing/report/reminder subset: 32 passed, 196 assertions.
- Six two-process MySQL billing races passed: duplicate cash receipt, competing full payments, competing refund reservations, duplicate actual refund, competing write-offs and verification versus write-off. The final cumulative 24-case MySQL run passed, 149 assertions.
- Chrome completed partial CASH plus pending transfer, matching closing before verification, late transfer verification, separate discount approval, refund approval with unchanged cash, actual refund completion, immutable previous closing, financial print, real report worker, authenticated CSV download and source-total/privacy assertions. Billing/report screens passed 375/768/1024/1440 overflow checks in the journey. Final screenshots were inspected; the cumulative journey and reminder segment passed as recorded below.
- The initial browser CSV failure exposed a harness environment difference: PHP CLI worker honored LARAVEL_STORAGE_PATH while the built-in web server did not populate environment variables into its PHP arrays. Setting variables_order=EGPCS for the isolated test processes gave both the same temporary storage. No production filesystem setting was changed.

Required Stage 4 integration correction: the patient dashboard previously exposed mutable RME prescription rows without an explicit official release. It now displays only explicitly released issued snapshots belonging to that patient, and invalidated versions show their status without stale medication contents. PatientPrescriptionDashboardTest was updated to the approved release contract: 3 passed, 28 assertions.

## Stage 6 - PASS for the technical scenarios tested

K-11 configuration is explicit in config/patient-reminders.php: N=3, RETURNED cooldown=30 minutes, H-1 at 18:00 in each clinic timezone, and unbooked control H-3 before the last allowed local control date (the exclusive database cutoff is converted back to that displayed date). Appointment, follow-up and near-turn preferences/dedup categories are separate. Verified patient accounts receive persistent in-app notifications plus private realtime updates; guests receive no fictional inbox. Their authorized tracker calculates current near-turn state, and unbooked guest follow-up creates an operational staff contact reminder. Overdue controls notify authorized clinic staff once per clinical generation.

Near-turn requires checked-in WAITING, no started Visit, a valid assigned doctor/session and no absence block. The count includes active calls/examinations, HOLD and ready resume priority, within the same clinic/doctor/session/date. Queue/ETA fluctuations do not create a new generation. A real SKIPPED -> WAITING event can open one new generation after cooldown. Replacements have new queue identities. Clinical follow-up revision advances its own generation; reopening the same unbooked control does not spam the patient. Booked controls suppress unbooked reminders and use the linked appointment's category.

A unique durable delivery row plus clinic/row locks deduplicate independent dispatchers/workers. Jobs recheck current status, ownership, role, verification, preferences, tenant and session availability. Inbox insertion and sent marker commit together. Failed broadcasts retain the inbox and retry with the same notification UUID; delivery after an external acceptance followed by timeout cannot be proven exactly once, so clients deduplicate that UUID. The existing realtime client already does this within its connection lifecycle. Unsent eligible deliveries can be requeued after a technical recovery interval if job retries were exhausted. Cancelled/rescheduled/called/terminal or newly unavailable targets are suppressed. Inbox history and read redirects re-evaluate reminder relevance; stale messages link to current lists instead of representing an active call.

Appointment H-1 does not send a misleading late reminder for bookings created after the approved H-1 window, or on visit day. No unapproved late-send lead time, SMS, WhatsApp, external push or provider was introduced. Messages omit service names, diagnoses, SOAP, identity numbers and guest tokens.

Migration: 2026_11_19_043528_add_patient_reminder_deliveries.php. Additive durable delivery ledger, patient preference JSON, queue generation/RETURNED marker and follow-up generation. Command patients:queue-reminders is scheduled every minute. Uses the existing business-database queue transaction boundary and worker, with no real scheduler/worker deployment performed.

Actual verification:

- PatientRemindersTest: 12 passed, 59 assertions. Includes threshold changes before delivery, actual RETURNED/cooldown, cancelled stale history/redirect, clinic-local 18:00 boundary, reschedule/late booking, H-3 booking suppression/revision, guest/staff channels, preference/account/tenant/doctor changes, absence/call suppression, resumed-care priority/HOLD, database failure rollback, broadcast failure and duplicate retry.
- Two real MySQL races for competing dispatchers and delivery workers passed in the cumulative 24-case run. A later delivery-versus-cancellation race also passed (1 case, 8 assertions). After the HOLD/ready count correction, all three reminder races were rerun together and passed (19 assertions). Across the 24-case checkpoint plus the added cancellation case, all 25 distinct MySQL scenarios have passing evidence. The disposable server was stopped after verifying its private temporary datadir; no application database was used.
- Final Chrome journey passed all six stages, including preferences off/on, the real reminder command and database worker, one persistent near-turn inbox entry, authenticated tracker state, application confirmation modal, Escape handler/focus restoration without cancellation, actual allowed self-cancellation and stale reminder history. Billing, report, reminder and cancellation screens passed overflow checks at 375/768/1024/1440 px. Final mobile/desktop cancellation and mobile inbox screenshots were inspected. Artifacts: C:/Users/User/AppData/Local/Temp/clinicos-browser-ifFpXt. No JavaScript exceptions. Keyboard handlers were exercised using DOM keyboard events in the browser; physical keyboard and screen-reader validation remain manual.

## Final-stage file map

Stage 5 domain/entry points: app/Services/PatientBillingService.php, app/Services/ClinicReportService.php, app/Http/Controllers/PatientBillingController.php, app/Http/Controllers/ClinicReportController.php, app/Policies/PatientBillPolicy.php, app/Jobs/BuildClinicReport.php and app/Notifications/ClinicReportReadyNotification.php. Models/factories: PatientBill, PatientBillClinicalReview, PatientBillEntry, PatientBillRequest, PatientBillTask, PatientTransaction, PatientBillingDocument, PatientCashClosing and ClinicReportExport. Views: resources/views/patient-billing/*, resources/views/clinic-reports/*, resources/views/components/billing/money.blade.php, service deposit form and dashboard/appointment/clinical entry links. Integration touches BookingService, VisitWorkflowService, Service, StoreServiceRequest, UpdateServiceRequest, EnsureSubscriptionWritable, SecurityHeaders and routes/web.php. Regression coverage: PatientBillingTest, ClinicReportsTest, PatientPrescriptionDashboardTest, WorkflowMysqlConcurrencyTest and browser-partial-workflows.test.mjs.

Stage 6 domain/entry points: app/Services/PatientReminderService.php, app/Jobs/DeliverPatientReminder.php, app/Notifications/PatientReminderNotification.php, app/Models/PatientReminderDelivery.php and its factory, app/Console/Commands/QueuePatientReminders.php, config/patient-reminders.php and the additive reminder migration. Integration touches User casts, QueueWorkflowService RETURNED metadata, FollowUpService clinical generation, NotificationController current relevance/read redirect/preferences, routes/console.php, routes/web.php, resources/views/notifications/index.blade.php, resources/views/components/appointment-queue-status.blade.php and resources/views/patient/appointments/show.blade.php. Regression coverage: PatientRemindersTest, WorkflowMysqlConcurrencyTest and the expanded browser journey.

The patient's cancellation uses the existing x-modal component with explicit confirm/back, pending state, retained reason and focus restoration. Chrome's native confirm returned false without opening a dialog on the tested authenticated page, so a page-native application modal replaces that fragile UI step without changing cancellation authority or business rules. The latest affected booking/authorization/reminder subset passed: 39 cases, 213 assertions. Browser navigation now waits for the actual new document to finish loading; the isolated web process and worker share the same temporary storage.

## Cumulative verification and operational limits

- Final full PHPUnit run after all six stages: 533 cases, 508 passed, 25 explicit opt-in MySQL skips, 3,373 assertions. All 25 distinct MySQL scenarios separately have passing evidence: the 24-case cumulative run passed (149 assertions), the added cancellation race passed (8 assertions), and all three reminder races were then rerun after the HOLD/ready count correction (19 assertions). MySQL coverage includes the legacy-active-session migration preflight as well as actual independent-process races. The focused reminder suite also passed (12 cases, 59 assertions).
- JavaScript tests: 26 passed, 2 explicit browser opt-in skips, 0 failed. Browser journeys are run separately in Chrome against a disposable SQLite/database/storage fixture.
- Vite production build, final Pint formatting and git diff whitespace checks passed. No dependency or framework-version changes.
- No migration or seed on the application database, no real payment, no production change, no commit/push/deployment. New migrations still need a reviewed operational rollout, backup and application of the normal migration command by the operator.
- Production scheduler, a database worker including default and reports queues, and Reverb must be configured/observed separately. Real external transport, paper printing, legal/SOP/clinical/finance sign-off and human usability remain unverified. Automated technical passes do not establish these release gates.

## Cross-stage integration and remaining release gates

| Integration | Implemented protection and evidence |
| --- | --- |
| Allergy/profile to booking/RME/prescription | Inactive profiles block new booking; old valid care is retained. Versioned allergy review and final-source checks prevent stale authoring/issuance. Covered by integrity, clinical-output, dashboard and continuity suites. |
| Absence/replacement to queue/follow-up/reminder | Availability is checked again on commit. Replacement is atomic, preserves lawful source price and linked control, withdraws the old call and invalidates its reminders. Source entitlement does not create target session capacity. |
| Pause/interruption/handoff to quota/medical access | READY reservations consume target capacity and have priority; HOLD plus the same READY patient counts once. Source RME remains author-controlled, child starts only at CALLED, and protected continuation does not consume a second SaaS visit quota. |
| Completed/interrupted care to patient billing | Care completion has no payment gate. Only lawful provided services establish billing; interrupted care needs clinical review. Cash, payment allocation, refunds and write-off remain separate. |
| Billing/closing to reports | Only verified ledger money contributes cash. Refund approval is pending until actual return. Closing high-water and immutable export snapshots preserve the reported period, independent of later verification or corrections. |
| Current access/state to delivery/history | Database uniqueness plus tenant locks fence concurrent dispatch/delivery and cancellation. Jobs recheck current ownership, role and state. Old messages are history with current relevance checks, never active calls merely because they were delivered earlier. |

No product decision inside the approved six-stage scope remains blocked. External messaging providers, late-booking catch-up timing, larger aggregate/detail export policy and unrelated product roadmap features were not introduced. These are scope exclusions, not unfinished substitutes for implemented workflows.

Technical evidence is separate from release authorization: production migrations/rollout, real scheduler/worker/Reverb monitoring, actual cash handling/printing, clinical and financial SOP validation, legal review and studies with real users have not been performed. The repository changes are uncommitted and reviewable. The disposable MySQL instance is stopped; temporary synthetic browser artifacts remain available for review.

The browser's attempted self-cancellation after a started session was correctly refused by the existing BookingService cutoff and displayed its validation message. The successful self-cancellation fixture uses a session starting 20 minutes later; no cancellation business rule was weakened. The long browser journey requires more than 20 minutes before clinic-local midnight. A separate transient 500 during an earlier run came from rebuilding Vite while the test server read its manifest; the final successful run used stable built assets.

For reproduction, use the repository's process-only SQLite overrides (DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL empty) with php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/bin/phpunit --no-progress --do-not-cache-result. The MySQL suite is opt-in through CLINICOS_MYSQL_CONCURRENCY=1 and must point to a separately provisioned disposable server; it creates/drops only its guarded unique test schemas. Set CLINICOS_BROWSER_TEST=1 and run node --test tests/browser-partial-workflows.test.mjs after npm run build has finished. Do not rebuild assets during that browser run. The browser harness creates its own SQLite, synthetic fixtures, storage and configuration-cache paths. node --test tests/*.test.mjs runs the 26 non-browser JavaScript checks with the two explicit browser opt-in skips.
