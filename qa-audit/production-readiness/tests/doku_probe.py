"""Probe only the configured sandbox using process-local credentials; never print provider URLs or headers."""
import json
import subprocess
from urllib.parse import urlsplit
import runtime

values = {}
for line in (runtime.REPO / '.env').read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        key, value = line.split('=', 1)
        if key in ['DOKU_CLIENT_ID', 'DOKU_SECRET_KEY', 'DOKU_BASE_URL']:
            values[key] = value.strip().strip('"\'')
host = urlsplit(values.get('DOKU_BASE_URL', 'https://api-sandbox.doku.com'))
assert host.scheme == 'https' and host.hostname == 'api-sandbox.doku.com' and host.path in ['', '/']
assert values.get('DOKU_CLIENT_ID') and values.get('DOKU_SECRET_KEY'), 'Sandbox credentials unavailable'
environment = {**runtime.env(), **values, 'DOKU_BASE_URL': 'https://api-sandbox.doku.com',
               'DOKU_NOTIFICATION_URL': runtime.env()['APP_URL'] + '/payments/doku/notification'}
result = subprocess.run(['php', 'qa-audit/production-readiness/tests/audit.php', 'doku-sandbox'], cwd=runtime.REPO,
                        env=environment, input=b'{}', capture_output=True, timeout=45)
parsed = json.loads(result.stdout)
assert result.returncode == 0, 'Sandbox probe helper failed'
(runtime.EVIDENCE / 'doku-sandbox-probe.json').write_text(json.dumps(parsed, indent=2))
integration = json.loads((runtime.EVIDENCE / 'integration-results.json').read_text())
integration['doku_sandbox']['actual_probe'] = parsed
integration['doku_sandbox']['reason'] = 'Actual sandbox checkout/status probe attempted; end-to-end provider callbacks and payment simulations remain blocked without a publicly reachable isolated notification endpoint.'
(runtime.EVIDENCE / 'integration-results.json').write_text(json.dumps(integration, indent=2))
print(json.dumps(parsed))
