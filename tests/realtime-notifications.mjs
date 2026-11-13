// Isolated real Reverb transport test: no application database or live credentials.
import { spawn } from 'node:child_process';
import { createHash, createHmac } from 'node:crypto';
import net from 'node:net';
import assert from 'node:assert/strict';

const reservation = net.createServer();
await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
const port = reservation.address().port;
await new Promise(resolve => reservation.close(resolve));
const key = 'notification-transport-test';
const secret = 'isolated-test-secret';
const app = 'notification-transport';
const child = spawn('php', ['artisan', 'reverb:start', '--host=127.0.0.1', `--port=${port}`], {
    windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'],
    env: { ...process.env, APP_ENV: 'testing', APP_URL: 'http://localhost', CACHE_STORE: 'array',
        APP_CONFIG_CACHE: 'bootstrap/cache/realtime-test-no-config.php',
        REVERB_APP_ID: app, REVERB_APP_KEY: key, REVERB_APP_SECRET: secret,
        REVERB_ALLOWED_ORIGINS: '*', REVERB_HOST: '127.0.0.1', REVERB_PORT: String(port), REVERB_SCHEME: 'http' },
});
let output = '';
child.stdout.on('data', data => output += data);
child.stderr.on('data', data => output += data);
let socket;
try {
    const started = Date.now();
    while (!output.includes('Starting server')) {
        if (child.exitCode !== null || Date.now() - started > 20000) throw new Error(`Reverb failed to start: ${output}`);
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    socket = new WebSocket(`ws://127.0.0.1:${port}/app/${key}?protocol=7&client=js&version=8.4.0`);
    const messages = [];
    socket.addEventListener('message', event => messages.push(JSON.parse(event.data)));
    const waitFor = async predicate => {
        const deadline = Date.now() + 6000;
        while (Date.now() < deadline) {
            const index = messages.findIndex(predicate);
            if (index >= 0) return messages.splice(index, 1)[0];
            await new Promise(resolve => setTimeout(resolve, 25));
        }
        throw new Error(`WebSocket event timeout: ${JSON.stringify(messages)} ${output}`);
    };
    const connected = await waitFor(message => message.event === 'pusher:connection_established');
    const socketId = JSON.parse(connected.data).socket_id;
    const channel = 'private-App.Models.User.1';
    const signature = createHmac('sha256', secret).update(`${socketId}:${channel}`).digest('hex');
    socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: `${key}:${signature}` } }));
    await waitFor(message => message.event === 'pusher_internal:subscription_succeeded');
    socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: 'private-App.Models.User.2', auth: `${key}:${signature}` } }));
    await waitFor(message => message.event === 'pusher:error');
    const body = JSON.stringify({ name: 'notifications.changed', channels: [channel], data: '{}' });
    const path = `/apps/${app}/events`;
    const query = `auth_key=${key}&auth_timestamp=${Math.floor(Date.now() / 1000)}&auth_version=1.0&body_md5=${createHash('md5').update(body).digest('hex')}`;
    const auth = createHmac('sha256', secret).update(`POST\n${path}\n${query}`).digest('hex');
    const response = await fetch(`http://127.0.0.1:${port}${path}?${query}&auth_signature=${auth}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body });
    assert.equal(response.status, 200, await response.text());
    const event = await waitFor(message => message.event === 'notifications.changed');
    assert.equal(event.channel, channel);
    console.log('PASS: private subscription, rejected invalid subscription, signed broadcast received over live WebSocket.');
} finally {
    socket?.close();
    child.kill();
}
