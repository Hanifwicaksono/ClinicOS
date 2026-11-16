"""Run preserved regression suites on a second disposable MySQL database."""
import json
from pathlib import Path
import subprocess
import runtime

database = runtime.RUNTIME['database'] + '_suite'
runtime.sql(f'CREATE DATABASE IF NOT EXISTS `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
environment = {**runtime.env(), 'APP_ENV': 'testing', 'APP_URL': 'http://localhost', 'DB_DATABASE': database, 'BCRYPT_ROUNDS': '4', 'BROADCAST_CONNECTION': 'null',
               'CACHE_STORE': 'array', 'MAIL_MAILER': 'array', 'QUEUE_CONNECTION': 'sync', 'SESSION_DRIVER': 'array',
               'LARAVEL_STORAGE_PATH': str(runtime.ROOT / 'suite-storage')}
for folder in ['framework/views', 'framework/cache', 'framework/sessions', 'logs', 'app/private', 'app/public']:
    (runtime.ROOT / 'suite-storage' / folder).mkdir(parents=True, exist_ok=True)
results = []
for name, paths in [('php-main', ['tests']), ('php-audit', ['qa-audit/tests/FullSystemAuditTest.php', 'qa-audit/tests/StaffLifecycleAuditTest.php'])]:
    command = ['php', '-d', 'extension=gd', 'vendor/bin/phpunit', '--no-progress', '--do-not-cache-result', '--log-junit',
               str(runtime.EVIDENCE / (name + '.xml')), *paths]
    with (runtime.EVIDENCE / (name + '.txt')).open('wb') as output:
        result = subprocess.run(command, cwd=runtime.REPO, env=environment, stdout=output, stderr=subprocess.STDOUT)
    results.append({'suite': name, 'exit': result.returncode})
    print(json.dumps(results[-1]), flush=True)
with (runtime.EVIDENCE / 'javascript.txt').open('wb') as output:
    javascript = subprocess.run(['node', '--test', 'tests/notification-realtime.test.mjs', 'tests/partial-workflows.test.mjs'], cwd=runtime.REPO, stdout=output, stderr=subprocess.STDOUT)
results.append({'suite': 'javascript', 'exit': javascript.returncode})
(runtime.EVIDENCE / 'suite-exits.json').write_text(json.dumps(results, indent=2))
print(json.dumps(results[-1]), flush=True)
