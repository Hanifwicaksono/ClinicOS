"""Exercise the scheduler/queue/SMTP pipeline and record external integration availability."""
import json
from pathlib import Path
import re
import subprocess
import time
import runtime

def audit(action, data=None):
    result = runtime.php(['qa-audit/production-readiness/tests/audit.php', action], input=json.dumps(data or {}).encode())
    parsed = json.loads(result.stdout)
    assert result.returncode == 0, parsed
    return parsed

fixture = audit('reminder-fixture')
outputs = []
for repeat in range(2):
    command = runtime.php(['artisan', 'schedule:test', '--name=subscriptions:queue-reminders', '--no-interaction'])
    assert command.returncode == 0
    outputs.append(command.stdout.decode(errors='replace'))
    deadline = time.monotonic() + 20
    while time.monotonic() < deadline:
        state = audit('reminder-state', fixture)
        if state['sent']:
            break
        time.sleep(.3)
    if repeat == 0:
        first_state = state
inbox = json.loads((runtime.ROOT / 'inbox.json').read_text())
mail_count = sum(fixture['owner_email'] in message['recipients'] for message in inbox)
values = {}
for line in (runtime.REPO / '.env').read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        key, value = line.split('=', 1)
        if key.startswith('DOKU_'):
            values[key] = value.strip().strip('"\'')
result = {
    'scheduler_queue_smtp': {'gate': 'PASS' if state['rows'] == state['sent'] == state['mailed'] == mail_count == 1 and state['failed'] == 0 else 'FAIL',
        'method': 'schedule:test executes the actual scheduled entry twice; due date arranged in disposable fixture',
        'first': first_state, 'second': state, 'smtp_inbox_messages_to_test_owner': mail_count, 'external_cron_daemon': 'NOT TESTED'},
    'doku_sandbox': {'gate': 'BLOCKED', 'credentials_present_in_local_configuration': bool(values.get('DOKU_CLIENT_ID') and values.get('DOKU_SECRET_KEY')),
        'sandbox_host_configured': values.get('DOKU_BASE_URL', 'https://api-sandbox.doku.com').startswith('https://api-sandbox.doku.com'),
        'isolated_runtime_doku_credentials_disabled': True,
        'reason': 'No dedicated sandbox credential set and publicly reachable sandbox notification endpoint supplied; no real payment or production credentials used.'},
    'external_smtp': {'gate': 'NOT TESTED', 'local_smtp_tcp_and_inbox': 'PASS', 'external_delivery_tls_dkim_dmarc': 'NOT TESTED'},
    'diagnostics': audit('diagnose'),
}
(runtime.EVIDENCE / 'integration-results.json').write_text(json.dumps(result, indent=2))
(runtime.EVIDENCE / 'scheduler.txt').write_text('\n'.join(outputs), encoding='utf-8')
print(json.dumps({name: details.get('gate') for name, details in result.items() if isinstance(details, dict) and 'gate' in details}))
