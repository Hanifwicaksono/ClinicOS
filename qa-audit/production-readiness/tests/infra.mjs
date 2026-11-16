import http from 'node:http';
import net from 'node:net';
import { readFileSync, writeFileSync, createReadStream, statSync, existsSync } from 'node:fs';
import { resolve, join, extname } from 'node:path';
import assert from 'node:assert/strict';

const config = JSON.parse(readFileSync('qa-audit/production-readiness/evidence/runtime.json', 'utf8'));
assert.ok(process.env.AUDIT_ROOT === config.root);
const inbox = [];
const smtp = net.createServer(socket => {
    socket.setEncoding('utf8');
    socket.write('220 localhost ClinicOS isolated test inbox\r\n');
    let buffer = '', data = false, content = [], recipients = [];
    socket.on('data', chunk => {
        buffer += chunk;
        while (buffer.includes('\r\n')) {
            const next = buffer.indexOf('\r\n');
            const line = buffer.slice(0, next); buffer = buffer.slice(next + 2);
            if (data) {
                if (line === '.') {
                    const file = join(config.root, `mail-${inbox.length + 1}.eml`);
                    writeFileSync(file, content.join('\r\n'));
                    inbox.push({ recipients, file, received_at: new Date().toISOString() });
                    writeFileSync(join(config.root, 'inbox.json'), JSON.stringify(inbox));
                    data = false; content = []; recipients = [];
                    socket.write('250 queued in isolated inbox\r\n');
                } else content.push(line.startsWith('..') ? line.slice(1) : line);
            } else if (/^(EHLO|HELO)/i.test(line)) socket.write('250-localhost\r\n250 8BITMIME\r\n');
            else if (/^MAIL FROM/i.test(line)) socket.write('250 sender accepted\r\n');
            else if (/^RCPT TO/i.test(line)) {
                const recipient = line.match(/<([^>]+)>/)?.[1];
                if (!recipient?.endsWith('@clinicos.test')) { socket.write('550 testing domain only\r\n'); continue; }
                recipients.push(recipient); socket.write('250 recipient accepted\r\n');
            } else if (/^DATA/i.test(line)) { data = true; socket.write('354 end with dot\r\n'); }
            else if (/^QUIT/i.test(line)) socket.end('221 bye\r\n');
            else if (/^RSET/i.test(line)) { content = []; recipients = []; socket.write('250 reset\r\n'); }
            else socket.write('250 ok\r\n');
        }
    });
    socket.on('error', () => {});
});
smtp.listen(config.smtp_port, '127.0.0.1');

let index = 0;
const types = { '.js': 'application/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png', '.ico': 'image/x-icon', '.woff2': 'font/woff2' };
http.createServer((request, response) => {
    const path = new URL(request.url, 'http://localhost').pathname;
    if (/^\/(build|css|js|images|storage)\//.test(path) || path === '/favicon.ico') {
        const base = path.startsWith('/build/') ? join(config.root, 'public') :
            path.startsWith('/storage/') ? join(config.root, 'storage/app/public') : resolve('public');
        const relative = path.startsWith('/storage/') ? path.slice(9) : path.slice(1);
        const file = resolve(base, relative);
        if (!file.startsWith(resolve(base) + '\\') || !existsSync(file) || !statSync(file).isFile()) {
            response.writeHead(404); response.end(); return;
        }
        response.writeHead(200, { 'Content-Type': types[extname(file)] || 'application/octet-stream' });
        createReadStream(file).pipe(response); return;
    }
    const upstream = http.request({ host: '127.0.0.1', port: config.backend_ports[index++ % config.backend_ports.length],
        path: request.url, method: request.method, headers: { ...request.headers, 'x-forwarded-proto': 'http' } }, result => {
        response.writeHead(result.statusCode, result.headers); result.pipe(response);
    });
    upstream.on('error', () => { response.writeHead(502); response.end('Isolated backend unavailable'); });
    request.pipe(upstream);
}).listen(config.app_port, '127.0.0.1');
