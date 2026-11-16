"""Provision and operate only the disposable ClinicOS readiness instance."""
import base64
import json
import os
from pathlib import Path
import secrets
import socket
import subprocess
import sys
import tempfile
import time

REPO = Path(__file__).resolve().parents[3]
EVIDENCE = REPO / 'qa-audit/production-readiness/evidence'
RUNTIME = json.loads((EVIDENCE / 'runtime.json').read_text(encoding='utf-8-sig'))
ROOT = Path(RUNTIME['root'])
assert ROOT.resolve().is_relative_to(Path(os.environ['TEMP']).resolve())
assert ROOT.name.startswith('clinicos-readiness-')
assert RUNTIME['database'].startswith('clinicos_pr_')
MYSQL = Path(RUNTIME['mysql_base']) / 'bin'
PRIVATE = ROOT / 'env.json'

def port_open(port):
    with socket.socket() as connection:
        connection.settimeout(.3)
        return connection.connect_ex(('127.0.0.1', port)) == 0

def env():
    return {**os.environ, **json.loads(PRIVATE.read_text())}

def run(command, **kwargs):
    return subprocess.run(command, cwd=REPO, env=env(), capture_output=True, **kwargs)

def php(arguments, **kwargs):
    return run(['php', '-d', 'extension=gd', *arguments], **kwargs)

def launch(name, command, environment=None):
    process = subprocess.Popen(command, cwd=REPO, env=environment or env(), stdin=subprocess.DEVNULL,
                               stdout=(ROOT / (name + '.out')).open('ab'),
                               stderr=(ROOT / (name + '.err')).open('ab'), creationflags=subprocess.CREATE_NO_WINDOW)
    (ROOT / (name + '.pid')).write_text(str(process.pid))
    return process

def sql(query, database=None):
    command = [str(MYSQL / 'mysql.exe'), '--no-defaults', '--host=127.0.0.1',
               '--port=' + str(RUNTIME['mysql_port']), '--user=root', '--batch', '--skip-column-names']
    if database:
        assert database.startswith('clinicos_pr_')
        command.append(database)
    result = subprocess.run(command, input=query.encode(), capture_output=True)
    if result.returncode:
        raise RuntimeError('Isolated MySQL command failed; inspect private runtime log')
    return result.stdout.decode().strip()

def setup():
    if not PRIVATE.exists():
        values = {
            'APP_ENV': 'local', 'APP_DEBUG': 'false', 'APP_URL': f"http://127.0.0.1:{RUNTIME['app_port']}",
            'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
            'APP_CONFIG_CACHE': str(ROOT / 'config.php'), 'APP_ROUTES_CACHE': str(ROOT / 'routes.php'),
            'LARAVEL_STORAGE_PATH': str(ROOT / 'storage'), 'AUDIT_ROOT': str(ROOT),
            'DB_CONNECTION': 'mysql', 'DB_HOST': '127.0.0.1', 'DB_PORT': str(RUNTIME['mysql_port']),
            'DB_DATABASE': RUNTIME['database'], 'DB_USERNAME': 'readiness', 'DB_PASSWORD': secrets.token_hex(24), 'DB_URL': '',
            'SESSION_DRIVER': 'database', 'SESSION_COOKIE': 'clinicos_readiness', 'SESSION_DOMAIN': 'null', 'SESSION_SECURE_COOKIE': 'false',
            'CACHE_STORE': 'database', 'CACHE_PREFIX': 'readiness_', 'QUEUE_CONNECTION': 'database', 'DB_QUEUE_RETRY_AFTER': '90',
            'MAIL_MAILER': 'smtp', 'MAIL_SCHEME': 'smtp', 'MAIL_HOST': '127.0.0.1', 'MAIL_PORT': str(RUNTIME['smtp_port']),
            'MAIL_URL': 'null', 'MAIL_USERNAME': 'null', 'MAIL_PASSWORD': 'null', 'MAIL_FROM_ADDRESS': 'audit@clinicos.test',
            'BROADCAST_CONNECTION': 'reverb', 'REVERB_APP_ID': 'readiness', 'REVERB_APP_KEY': 'readiness-local',
            'REVERB_APP_SECRET': secrets.token_hex(24), 'REVERB_HOST': '127.0.0.1', 'REVERB_PORT': str(RUNTIME['reverb_port']),
            'REVERB_SCHEME': 'http', 'REVERB_SERVER_HOST': '127.0.0.1', 'REVERB_SERVER_PORT': str(RUNTIME['reverb_port']),
            'REVERB_ALLOWED_ORIGINS': '127.0.0.1,localhost',
            'VITE_REVERB_APP_KEY': 'readiness-local', 'VITE_REVERB_HOST': '127.0.0.1',
            'VITE_REVERB_PORT': str(RUNTIME['reverb_port']), 'VITE_REVERB_SCHEME': 'http',
            'DOKU_CLIENT_ID': '', 'DOKU_SECRET_KEY': '', 'DOKU_BASE_URL': 'https://api-sandbox.doku.com',
            'GOOGLE_CLIENT_ID': '', 'GOOGLE_CLIENT_SECRET': '',
        }
        PRIVATE.write_text(json.dumps(values))
    for path in ['framework/views', 'framework/cache', 'framework/sessions', 'logs', 'app/private', 'app/public']:
        (ROOT / 'storage' / path).mkdir(parents=True, exist_ok=True)
    if not port_open(RUNTIME['mysql_port']):
        launch('mysql', [str(MYSQL / 'mysqld.exe'), '--no-defaults', '--basedir=' + RUNTIME['mysql_base'],
                         '--datadir=' + str(ROOT / 'mysql'), '--port=' + str(RUNTIME['mysql_port']),
                         '--bind-address=127.0.0.1', '--mysqlx=OFF', '--skip-log-bin', '--console'])
        for _ in range(100):
            if port_open(RUNTIME['mysql_port']):
                break
            time.sleep(.2)
    assert port_open(RUNTIME['mysql_port']), 'Isolated MySQL unavailable'
    assert Path(sql('SELECT @@datadir')).resolve() == (ROOT / 'mysql').resolve(), 'Refusing a MySQL instance outside the disposable datadir'
    password = env()['DB_PASSWORD']
    sql(f"CREATE DATABASE IF NOT EXISTS `{RUNTIME['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        f"CREATE USER IF NOT EXISTS 'readiness'@'127.0.0.1' IDENTIFIED BY '{password}';"
        "GRANT ALL ON `clinicos_pr_%`.* TO 'readiness'@'127.0.0.1';")
    result = php(['artisan', 'migrate', '--force', '--no-interaction'])
    (EVIDENCE / 'migrations.txt').write_bytes(result.stdout + result.stderr)
    assert result.returncode == 0, 'Migration failed; see migrations.txt'
    result = subprocess.run(['npm.cmd', 'install', '--prefix', str(ROOT / 'tools'), 'playwright'], capture_output=True,
                            creationflags=subprocess.CREATE_NO_WINDOW)
    (EVIDENCE / 'playwright-install.txt').write_bytes(result.stdout + result.stderr)
    assert result.returncode == 0, 'Temporary Playwright installation failed'
    result = run(['npm.cmd', 'run', 'build', '--', '--outDir', str(ROOT / 'public/build')])
    (EVIDENCE / 'build.txt').write_bytes(result.stdout + result.stderr)
    assert result.returncode == 0, 'Temporary Vite build failed'
    print(json.dumps({'setup': 'PASS', 'mysql_version': sql('SELECT VERSION()'), 'database': RUNTIME['database'],
                      'mysql_port': RUNTIME['mysql_port'], 'root': str(ROOT)}))

if __name__ == '__main__':
    action = sys.argv[1]
    if action == 'init':
        assert not port_open(RUNTIME['mysql_port']), 'Stop the previous owned audit instance before initializing another run'
        new_root = Path(tempfile.mkdtemp(prefix='clinicos-readiness-'))
        (new_root / 'mysql').mkdir(); (new_root / 'tools').mkdir()
        new_runtime = {**RUNTIME, 'root': str(new_root), 'database': 'clinicos_pr_' + secrets.token_hex(5)}
        result = subprocess.run([str(MYSQL / 'mysqld.exe'), '--no-defaults', '--initialize-insecure',
            '--basedir=' + RUNTIME['mysql_base'], '--datadir=' + str(new_root / 'mysql'), '--console'], capture_output=True)
        (new_root / 'mysql-initialize.log').write_bytes(result.stdout + result.stderr)
        assert result.returncode == 0, 'Disposable MySQL initialization failed'
        (EVIDENCE / 'runtime.json').write_text(json.dumps(new_runtime, indent=2))
        print(json.dumps({'initialized': True, 'root': str(new_root)}))
    elif action == 'setup':
        setup()
    elif action == 'php':
        result = php(sys.argv[2:]); sys.stdout.buffer.write(result.stdout); sys.stderr.buffer.write(result.stderr); sys.exit(result.returncode)
    elif action == 'node':
        result = subprocess.run(['node', *sys.argv[2:]], cwd=REPO, env=env()); sys.exit(result.returncode)
    elif action == 'sql':
        print(sql(sys.stdin.read(), RUNTIME['database']))
    elif action == 'restart-http':
        values = json.loads(PRIVATE.read_text())
        values.update({'MAIL_URL': 'null', 'MAIL_USERNAME': 'null', 'MAIL_PASSWORD': 'null'})
        PRIVATE.write_text(json.dumps(values))
        for index, port in enumerate(RUNTIME['backend_ports']):
            pid = int((ROOT / f'http{index}.pid').read_text())
            command = f"$p=Get-CimInstance Win32_Process -Filter 'ProcessId={pid}'; if ($p.CommandLine -like '*qa-audit/production-readiness/tests/router.php*') {{ Stop-Process -Id {pid} -Force }} else {{ exit 1 }}"
            assert subprocess.run(['powershell', '-NoProfile', '-Command', command], capture_output=True).returncode == 0
            launch(f'http{index}', ['php', '-d', 'extension=gd', '-S', f'127.0.0.1:{port}', 'qa-audit/production-readiness/tests/router.php'])
        print(json.dumps({'http_restarted': True}))
    elif action == 'start':
        launch('infra', ['node', 'qa-audit/production-readiness/tests/infra.mjs'])
        launch('reverb', ['php', 'artisan', 'reverb:start', '--no-interaction'])
        launch('worker', ['php', 'artisan', 'queue:work', '--sleep=1', '--tries=2', '--timeout=30', '--no-interaction'])
        for index, port in enumerate(RUNTIME['backend_ports']):
            launch(f'http{index}', ['php', '-d', 'extension=gd', '-S', f'127.0.0.1:{port}', 'qa-audit/production-readiness/tests/router.php'])
        print(json.dumps({'started': True}))
