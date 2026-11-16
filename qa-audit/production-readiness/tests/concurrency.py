"""Release independently booted PHP/MySQL workers through a filesystem barrier."""
import json
from pathlib import Path
import subprocess
import time
import uuid
import runtime

PHP = ['php', '-d', 'extension=gd', 'qa-audit/production-readiness/tests/audit.php']
ENV = {**runtime.env(), 'APP_ENV': 'testing'}

def audit(action, data):
    result = subprocess.run([*PHP, action], input=json.dumps(data).encode(), capture_output=True, cwd=runtime.REPO, env=ENV)
    parsed = json.loads(result.stdout)
    assert result.returncode == 0, parsed
    return parsed

def request(actor, path, method='GET', payload=None):
    return {'type': 'http', 'actor': actor, 'path': path, 'method': method, 'payload': payload or {}}

def race(name, tasks):
    folder = runtime.ROOT / ('race-' + name + '-' + uuid.uuid4().hex)
    folder.mkdir()
    processes = []
    for index, task in enumerate(tasks):
        environment = ENV
        if name == 'owner-checkout' and index == 0:
            values = {}
            for line in (runtime.REPO / '.env').read_text().splitlines():
                if '=' in line and not line.startswith('#'):
                    key, value = line.split('=', 1)
                    if key in ['DOKU_CLIENT_ID', 'DOKU_SECRET_KEY', 'DOKU_BASE_URL']:
                        values[key] = value.strip().strip('"\'')
            assert values.get('DOKU_BASE_URL', 'https://api-sandbox.doku.com').rstrip('/') == 'https://api-sandbox.doku.com'
            environment = {**ENV, **values, 'DOKU_BASE_URL': 'https://api-sandbox.doku.com', 'DOKU_NOTIFICATION_URL': ENV['APP_URL'] + '/payments/doku/notification'}
        process = subprocess.Popen([*PHP, 'worker'], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                   cwd=runtime.REPO, env=environment, creationflags=subprocess.CREATE_NO_WINDOW)
        process.stdin.write(json.dumps({'ready': str(folder / f'{index}.ready'), 'barrier': str(folder / 'go'),
                                        'hold_lock_ms': 400 if index == 0 and name in ['last-slot', 'duplicate-request'] else 0,
                                        'task': task}).encode())
        process.stdin.close()
        processes.append(process)
    deadline = time.monotonic() + 25
    while not all((folder / f'{i}.ready').exists() for i in range(len(tasks))):
        assert time.monotonic() < deadline, 'Independent worker startup timed out'
        time.sleep(.01)
    ready = [json.loads((folder / f'{i}.ready').read_text()) for i in range(len(tasks))]
    assert len(set(item['connection_id'] for item in ready)) == len(tasks)
    (folder / 'go').touch()
    lock_wait_seen = False
    for _ in range(5):
        time.sleep(.06)
        if int(runtime.sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits') or 0) > 0:
            lock_wait_seen = True
            break
    results = []
    for process in processes:
        process.wait(timeout=30)
        output = process.stdout.read()
        results.append(json.loads(output))
    overlap = all('started' in item for item in results) and max(item['started'] for item in results) < min(item['ended'] for item in results)
    return {'workers': ready, 'overlap': overlap, 'innodb_lock_wait_observed': lock_wait_seen, 'results': results}

results = []
for name in ['last-slot', 'patient-contention', 'duplicate-request', 'same-record', 'two-doctors', 'schedule-edit', 'staff-disabled', 'subscription-active', 'owner-checkout']:
    context = audit('race-fixture', {'case': name})
    fixture = context['fixture']; users = fixture['users']; payload = {**context['payload'], 'email': 'synthetic@clinicos.test', 'address': 'Synthetic clinic audit address'}
    booking = lambda actor, data: request(actor, '/receptionist/appointments', 'POST', data)
    second = {**payload, 'idempotency_key': str(uuid.uuid4()), 'phone': '081199999998'}
    if name in ['last-slot', 'duplicate-request']:
        tasks = [booking(users['receptionist']['id'], payload), booking(users['receptionist2']['id'], payload if name == 'duplicate-request' else second)]
    elif name == 'patient-contention':
        tasks = [request(users[role]['id'], f"/clinic/{fixture['slug']}/booking", 'POST', data)
                 for role, data in zip(['patient', 'patient2'], [payload, second])]
    elif name in ['same-record', 'two-doctors']:
        data = {**context['versions'], 'subjective': 'Synthetic concurrency input', 'assessment': 'Synthetic assessment', 'plan': 'Synthetic plan'}
        tasks = [request(users['doctor']['id'], f"/doctor/visits/{context['visit_id']}/draft", 'PUT', data),
                 request(users['doctor' if name == 'same-record' else 'doctor2']['id'], f"/doctor/visits/{context['visit_id']}/draft", 'PUT', data)]
    elif name == 'schedule-edit':
        data = {'doctor_id': users['doctor2']['doctor_id'], 'day_of_week': time.localtime().tm_wday + 1,
                'start_time': '00:00', 'end_time': '23:59', 'quota': 100, 'is_active': True}
        data['day_of_week'] %= 7
        tasks = [request(users['admin']['id'], f"/admin/doctor-schedules/{users['doctor']['schedule_id']}", 'PUT', data), booking(users['receptionist']['id'], payload)]
    elif name == 'staff-disabled':
        tasks = [{'type': 'deactivate', 'actor': users['owner']['id'], 'staff': users['receptionist']['id']}, booking(users['receptionist']['id'], payload)]
    elif name == 'subscription-active':
        data = {**context['versions'], 'subjective': 'Synthetic continuity', 'assessment': 'Synthetic assessment', 'plan': 'Synthetic plan'}
        tasks = [{'type': 'downgrade', 'clinic_id': fixture['clinic_id']}, request(users['doctor']['id'], f"/doctor/visits/{context['visit_id']}/draft", 'PUT', data)]
    else:
        data = {**context['versions'], 'subjective': 'Synthetic owner-checkout continuity', 'assessment': 'Synthetic assessment', 'plan': 'Synthetic plan'}
        tasks = [request(users['owner']['id'], f"/onboarding/plans/paid/{context['price_id']}", 'POST'),
                 request(users['doctor']['id'], f"/doctor/visits/{context['visit_id']}/draft", 'PUT', data)]
    observation = race(name, tasks)
    state = audit('state', {'clinic_id': fixture['clinic_id']})
    statuses = sorted(item['status'] for item in observation['results'])
    passed = {
        'last-slot': statuses == [302, 422] and state['appointments'] == 1,
        'patient-contention': statuses == [302, 422] and state['appointments'] == 1,
        'duplicate-request': statuses == [302, 302] and state['appointments'] == 1,
        'same-record': statuses == [200, 409] and state['visits_state'][0]['version'] == 1 if state['visits_state'] else False,
        'two-doctors': statuses == [200, 403] and state['visits_state'][0]['version'] == 1 if state['visits_state'] else False,
        'schedule-edit': statuses == [302, 422] and state['mismatched_doctor'] == 0,
        'staff-disabled': statuses in [[200, 302], [200, 401]],
        'subscription-active': statuses == [200, 200] and state['visits_state'][0]['version'] == 1 if state['visits_state'] else False,
        'owner-checkout': statuses == [200, 302] and state['pending_payments'] == 1 and state['plan'] == 'team' and state['visits_state'][0]['version'] == 1 if state['visits_state'] else False,
    }[name]
    if name == 'staff-disabled':
        observation['next_request'] = audit('task', request(users['receptionist']['id'], '/receptionist/dashboard'))
        passed &= observation['next_request']['status'] == 302 and observation['next_request']['redirect_path'] in ['/admin', '/login']
    if name == 'subscription-active':
        observation['new_booking'] = audit('task', booking(users['receptionist']['id'], second))
        passed &= observation['new_booking']['status'] == 403
    passed &= state['mismatched_doctor'] == 0 and state['appointments'] == state['queues']
    result = {'case': name, 'gate': 'PASS' if passed else 'FAIL', **observation, 'state': state}
    results.append(result)
    (runtime.EVIDENCE / 'mysql-concurrency.json').write_text(json.dumps(results, indent=2))
    print(json.dumps({'case': name, 'gate': result['gate'], 'statuses': statuses, 'overlap': observation['overlap'], 'lock_wait': observation['innodb_lock_wait_observed']}), flush=True)
