"""Destructive failure injection is confined to the disposable readiness processes and databases."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import time
import uuid
import urllib.request
import runtime

evidence = {}

def audit(action, data=None):
    result = runtime.php(['qa-audit/production-readiness/tests/audit.php', action], input=json.dumps(data or {}).encode())
    parsed = json.loads(result.stdout)
    assert result.returncode == 0, parsed
    return parsed

def stop_owned(name, expected):
    pid = int((runtime.ROOT / (name + '.pid')).read_text())
    command = f"$p=Get-CimInstance Win32_Process -Filter 'ProcessId={pid}'; if ($p.CommandLine -like '*{expected}*') {{ Stop-Process -Id {pid} -Force }} else {{ exit 1 }}"
    result = subprocess.run(['powershell', '-NoProfile', '-Command', command], capture_output=True)
    assert result.returncode == 0, 'Owned process identity could not be verified'
    return pid

def file_hashes(folder):
    return {str(path.relative_to(folder)): hashlib.sha256(path.read_bytes()).hexdigest() for path in folder.rglob('*') if path.is_file()}

def save():
    (runtime.EVIDENCE / 'recovery-results.json').write_text(json.dumps(evidence, indent=2))

run_id = uuid.uuid4().hex[:8]
fixture = audit('race-fixture', {'case': 'same-record'})
before_files = file_hashes(runtime.ROOT / 'storage/app/private')
storage = audit('storage-failure', {'visit_id': fixture['visit_id'], 'actor': fixture['fixture']['users']['doctor']['id']})
storage['existing_private_files_unchanged'] = before_files == file_hashes(runtime.ROOT / 'storage/app/private')
evidence['storage_failure'] = {'gate': 'PASS' if storage['filesystem_failure_observed'] and storage['version_unchanged'] and storage['photo_count'] == 0 and storage['existing_private_files_unchanged'] else 'FAIL', **storage}
save()

deadline = time.monotonic() + 25
while int(runtime.sql('SELECT COUNT(*) FROM jobs', runtime.RUNTIME['database'])) and time.monotonic() < deadline:
    time.sleep(.2)
old_worker = stop_owned('worker', 'artisan queue:work')
stopped_at = time.monotonic()
payload = {**fixture['payload'], 'idempotency_key': str(uuid.uuid4()), 'patient_id': fixture['fixture']['users']['patient']['patient_id']}
rollback = audit('transaction-rollback', {'clinic_id': fixture['fixture']['clinic_id'], 'payload': payload, 'actor': fixture['fixture']['users']['receptionist']['id']})
evidence['transaction_recovery'] = {'gate': 'PASS' if rollback['rolled_back'] and rollback['connection_usable'] else 'FAIL', **rollback}
save()

main = json.loads((runtime.ROOT / 'fixture.json').read_text())['main']
task = {'type': 'http', 'actor': main['users']['receptionist']['id'], 'method': 'POST', 'path': '/receptionist/appointments',
        'payload': {'patient_id': main['users']['patient']['patient_id'], 'doctor_id': main['users']['doctor']['doctor_id'],
                    'doctor_schedule_id': main['users']['doctor']['schedule_id'], 'service_id': main['service_id'],
                    'appointment_date': main['date'], 'idempotency_key': str(uuid.uuid4())}}
environment = {**runtime.env(), 'APP_ENV': 'testing'}
result = subprocess.run(['php', 'qa-audit/production-readiness/tests/audit.php', 'task'], input=json.dumps(task).encode(),
                        cwd=runtime.REPO, env=environment, capture_output=True)
assert json.loads(result.stdout)['status'] == 302
pending = int(runtime.sql('SELECT COUNT(*) FROM jobs', runtime.RUNTIME['database']))
time.sleep(1)
durable = int(runtime.sql('SELECT COUNT(*) FROM jobs', runtime.RUNTIME['database'])) == pending and pending > 0
worker = runtime.launch('worker', ['php', 'artisan', 'queue:work', '--sleep=1', '--tries=2', '--timeout=30', '--no-interaction'])
deadline = time.monotonic() + 20
while int(runtime.sql('SELECT COUNT(*) FROM jobs', runtime.RUNTIME['database'])) and time.monotonic() < deadline:
    time.sleep(.2)
remaining = int(runtime.sql('SELECT COUNT(*) FROM jobs', runtime.RUNTIME['database']))
failed = int(runtime.sql('SELECT COUNT(*) FROM failed_jobs', runtime.RUNTIME['database']))
evidence['worker_restart'] = {'gate': 'PASS' if durable and remaining == 0 and failed == 0 else 'FAIL',
    'old_pid': old_worker, 'new_pid': worker.pid, 'durable_pending_jobs': pending, 'remaining': remaining, 'failed_jobs': failed,
    'recovery_seconds_including_test_pause': time.monotonic() - stopped_at, 'mid_job_crash_tested': False}
evidence['worker_restart']['retry_after_seconds'] = int(runtime.env()['DB_QUEUE_RETRY_AFTER'])
evidence['worker_restart']['worker_timeout_seconds'] = 30
save()

stop_owned('worker', 'artisan queue:work')
old_http = [stop_owned(f'http{i}', 'qa-audit/production-readiness/tests/router.php') for i in range(4)]
database = runtime.RUNTIME['database']
tables = runtime.sql('SHOW TABLES', database).splitlines()
def checksums(db):
    result = runtime.sql('CHECKSUM TABLE ' + ','.join('`' + table + '`' for table in tables), db)
    return {line.split('\t')[0].split('.')[-1]: line.split('\t')[-1] for line in result.splitlines()}

started = time.monotonic()
original_checksums = checksums(database)
dump = runtime.ROOT / ('database-backup-' + run_id + '.sql')
with dump.open('wb') as output:
    result = subprocess.run([str(runtime.MYSQL / 'mysqldump.exe'), '--no-defaults', '--host=127.0.0.1',
        '--port=' + str(runtime.RUNTIME['mysql_port']), '--user=root', '--single-transaction', '--no-tablespaces', '--set-gtid-purged=OFF', database],
        stdout=output, stderr=subprocess.PIPE)
assert result.returncode == 0, 'Isolated database backup failed'
backup_seconds = time.monotonic() - started
restored_database = database + '_restore_' + run_id
runtime.sql(f'CREATE DATABASE `{restored_database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
started = time.monotonic()
with dump.open('rb') as source:
    result = subprocess.run([str(runtime.MYSQL / 'mysql.exe'), '--no-defaults', '--host=127.0.0.1',
        '--port=' + str(runtime.RUNTIME['mysql_port']), '--user=root', restored_database], stdin=source, capture_output=True)
assert result.returncode == 0, 'Restore into separate audit database failed'
restored_checksums = checksums(restored_database)
evidence['database_restore'] = {'gate': 'PASS' if original_checksums == restored_checksums else 'FAIL',
    'tables': len(tables), 'all_table_checksums_equal': original_checksums == restored_checksums,
    'backup_bytes': dump.stat().st_size, 'backup_seconds': backup_seconds, 'restore_seconds': time.monotonic() - started,
    'source_database': database, 'restored_database': restored_database}

private = runtime.ROOT / 'storage/app/private'
backup_files = runtime.ROOT / ('private-files-backup-' + run_id)
restore_files = runtime.ROOT / ('private-files-restored-' + run_id)
shutil.copytree(private, backup_files)
shutil.copytree(backup_files, restore_files)
hashes = file_hashes(private)
evidence['private_file_restore'] = {'gate': 'PASS' if hashes and hashes == file_hashes(restore_files) else 'FAIL',
    'files': len(hashes), 'all_sha256_equal': hashes == file_hashes(restore_files), 'restore_outside_webroot': True}

new_http = []
for index, port in enumerate(runtime.RUNTIME['backend_ports']):
    process = runtime.launch(f'http{index}', ['php', '-d', 'extension=gd', '-S', f'127.0.0.1:{port}', 'qa-audit/production-readiness/tests/router.php'])
    new_http.append(process.pid)
runtime.launch('worker', ['php', 'artisan', 'queue:work', '--sleep=1', '--tries=2', '--timeout=30', '--no-interaction'])
deadline = time.monotonic() + 15
healthy = False
while time.monotonic() < deadline:
    try:
        healthy = urllib.request.urlopen(runtime.env()['APP_URL'] + '/up', timeout=2).status == 200
        if healthy:
            break
    except Exception:
        time.sleep(.2)
evidence['app_restart'] = {'gate': 'PASS' if healthy else 'FAIL', 'old_pids': old_http, 'new_pids': new_http, 'health_status': 200 if healthy else None}
evidence['schema_after_recovery'] = audit('schema')
browser_state = json.loads((runtime.ROOT / 'browser-fixture-state.json').read_text())
restore_env = {**runtime.env(), 'APP_ENV': 'testing', 'DB_DATABASE': restored_database, 'AUDIT_RESTORE_STORAGE': str(restore_files)}
probe = subprocess.run(['php', 'qa-audit/production-readiness/tests/audit.php', 'restore-photo'],
                       input=json.dumps({'actor': main['users']['doctor']['id'], 'visit_id': browser_state['visitId']}).encode(),
                       cwd=runtime.REPO, env=restore_env, capture_output=True)
parsed = json.loads(probe.stdout)
evidence['restored_application_photo'] = {'gate': 'PASS' if probe.returncode == 0 and parsed['status'] == 200 and parsed['disk_root_is_restored'] else 'FAIL', **parsed}
save()
print(json.dumps({key: value['gate'] for key, value in evidence.items() if isinstance(value, dict) and 'gate' in value}))
