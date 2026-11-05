import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, existsSync, readdirSync, renameSync } from 'node:fs';
import { resolve, join } from 'node:path';
import { randomBytes } from 'node:crypto';
import { pathToFileURL } from 'node:url';

const root = resolve(import.meta.dirname, '../..');
const output = join(root, 'security-report');
if (process.argv.includes('--report-only')) {
  const { buildReport } = await import('./report.mjs');
  buildReport(output, JSON.parse(readFileSync(join(output, 'test-results.json'), 'utf8')));
  const temporary = join(output, 'report-next.pdf');
  const browserProfile = join(root, 'storage/app/security-check', 'export-' + Date.now());
  const exportRun = spawnSync('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', [
    '--headless', '--disable-gpu', '--no-pdf-header-footer', `--user-data-dir=${browserProfile}`,
    `--print-to-pdf=${temporary}`, pathToFileURL(join(output, 'SIA1_Topic9_Group_Section.html')).href,
  ], { windowsHide: true, encoding: 'utf8', timeout: 60000 });
  for (let i=0; i<30 && !existsSync(temporary); i++) await new Promise(r=>setTimeout(r,200));
  if (!existsSync(temporary)) throw Error('PDF export failed: ' + exportRun.stderr);
  renameSync(temporary, join(output, 'SIA1_Topic9_Group_Section.pdf'));
  console.log('Report layout updated from the completed browser evidence.');
  process.exit(0);
}
const resume = process.argv.includes('--resume');
const scratchRoot = join(root, 'storage/app/security-check');
const scratch = join(scratchRoot, resume ? readdirSync(scratchRoot).filter(n=>/^\d+$/.test(n)).sort().at(-1) : String(Date.now()));
mkdirSync(join(output, 'screenshots'), { recursive: true });
mkdirSync(scratch, { recursive: true });
mkdirSync(join(scratch, 'views'), { recursive: true });
const database = join(scratch, 'dummy.sqlite');
if (!resume) writeFileSync(database, '');
const base = 'http://127.0.0.1:8019';
const env = { ...process.env,
  APP_ENV: 'local', APP_DEBUG: 'false', APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
  APP_URL: base, ASSET_URL: base, DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
  SESSION_DRIVER: 'database', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', SESSION_COOKIE: 'topic9-evidence-session',
  CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
  VIEW_COMPILED_PATH: join(scratch, 'views'),
  ORDER_NOTIFICATIONS_ENABLED: 'false', NOTIFICATION_MAILER: 'array',
  GOOGLE_CLIENT_ID: '', GOOGLE_CLIENT_SECRET: '', FILESYSTEM_DISK: 'local',
};
function php(args) {
  const r = spawnSync('php', args, { cwd: root, env, encoding: 'utf8', windowsHide: true });
  if (r.status !== 0) throw Error(`PHP command failed: ${args.join(' ')}\n${r.stdout}\n${r.stderr}`);
  return r.stdout;
}
if (existsSync(join(root, 'bootstrap/cache/config.php'))) throw Error('Clear Laravel config cache before using the isolated evidence server.');
if (!resume) {
  console.log(php(['artisan', 'migrate', '--no-interaction']).trim());
  console.log(php(['tools/security-check/seed.php']).trim());
}
const server = spawn('php', ['-S', '127.0.0.1:8019', '-t', 'public', 'tools/security-check/router.php'], { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
let serverErrors = '';
server.stderr.on('data', b => { serverErrors = (serverErrors + b).slice(-6000); });
const edge = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', [
  '--headless', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=9239',
  `--user-data-dir=${join(scratch, 'browser-' + Date.now())}`, 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let socket;
const pending = new Map();
let seq = 0;
const result = resume ? JSON.parse(readFileSync(join(output,'test-results.partial.json'),'utf8')) : { tested_at: new Date().toLocaleString('en-PH', { timeZone: 'Asia/Manila', hour12: false }), timezone: 'Asia/Manila', base_url: base, database: 'Isolated SQLite dummy database; live marketplace data not used', screenshots: [], checks: {}, network:[] };
const network = result.network;
async function until(fn, label, timeout = 45000) {
  const start = Date.now();
  while (Date.now() - start < timeout) { try { const value = await fn(); if (value) return value; } catch {} await sleep(150); }
  throw Error(`Timed out: ${label}\n${serverErrors}`);
}
function send(method, params = {}) {
  const id = ++seq;
  return new Promise((resolveCall, reject) => {
    const timeout = setTimeout(() => { pending.delete(id); reject(Error(`CDP timeout: ${method}`)); }, 45000);
    pending.set(id, { resolve: value => { clearTimeout(timeout); resolveCall(value); }, reject });
    socket.send(JSON.stringify({ id, method, params }));
  });
}
async function evaluate(expression) {
  const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (r.exceptionDetails) throw Error(JSON.stringify(r.exceptionDetails));
  return r.result.value;
}
async function go(path, finalPath = path) {
  const url = path.startsWith('file:') ? path : base + path;
  console.log(`Opening ${url}`);
  if (url.startsWith('file:')) await send('Page.navigate', { url });
  else await evaluate(`(() => { setTimeout(() => location.assign(${JSON.stringify(url)}), 0); return true; })()`);
  await until(() => evaluate(`document.readyState === 'complete' && ${url.startsWith('file:') ? 'true' : `location.pathname === ${JSON.stringify(finalPath)}`} && !!document.body`), `navigate ${path}`);
  await sleep(300);
}
async function fill(selector, value) {
  await evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); if(!el) throw Error('Missing input'); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event('input', {bubbles:true})); el.dispatchEvent(new Event('change', {bubbles:true})); })()`);
}
async function submit(selector, expectedPath, expectedText) {
  await evaluate(`(() => { const f=document.querySelector(${JSON.stringify(selector)}); f.noValidate=true; f.requestSubmit(); })()`);
  await until(() => evaluate(`document.readyState === 'complete' && location.pathname === ${JSON.stringify(expectedPath)} && document.body.innerText.includes(${JSON.stringify(expectedText)})`), `submit ${selector}`);
  await sleep(250);
}
async function capture(name, title, source = 'FarSell browser screenshot') {
  await evaluate('window.scrollTo(0,0)');
  await sleep(150);
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
  const file = `screenshots/${name.split('-')[0]}.png`;
  writeFileSync(join(output, file), Buffer.from(shot.data, 'base64'));
  const url = await evaluate('location.href');
  result.screenshots.push({ file, title, url: url.startsWith('file:') ? 'Local evidence viewer (generated from actual test output)' : url, source });
  console.log(`Captured ${name}: ${title}`);
}
async function login(email) {
  await go('/login');
  await fill('#login-identifier', email);
  await fill('#login_password', 'Demo-Only-12345');
  await evaluate("document.querySelector('#login-panel form').requestSubmit()");
  // Laravel may return to the protected URL attempted before sign-in.
  await until(() => evaluate("location.pathname !== '/login' && document.readyState === 'complete' && !!document.querySelector('form[action$=\"/logout\"]')"), 'authenticated sign-in');
  await go(email.includes('admin') ? '/admin' : '/home');
}
async function logout() {
  await submit('form[action$="/logout"]', '/home', 'FarSell');
}
const escape = s => String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
function viewer(title, text, filename) {
  writeFileSync(join(output, filename), `<!doctype html><html><meta charset="utf-8"><title>${escape(title)}</title><style>body{font:19px/1.6 Consolas,monospace;background:#f6f4fa;color:#252238;padding:40px}h1{font:700 28px Arial;color:#6744a4}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:white;border:1px solid #d6cbe8;padding:24px;border-radius:12px}small{font:15px Arial;color:#666}</style><h1>${escape(title)}</h1><small>Evidence viewer · actual output from the isolated Topic 9 test run · dummy data only</small><pre>${escape(text)}</pre></html>`);
}
try {
  await until(async () => (await fetch(base + '/privacy')).status === 200, 'temporary PHP server');
  const pages = await until(async () => { const list = await (await fetch('http://127.0.0.1:9239/json/list')).json(); return list.find(p => p.type === 'page'); }, 'headless Edge');
  socket = new WebSocket(pages.webSocketDebuggerUrl);
  await new Promise((r, reject) => { socket.addEventListener('open', r, { once:true }); socket.addEventListener('error', reject, { once:true }); });
  socket.addEventListener('message', event => {
    const m = JSON.parse(event.data);
    if (m.id) { const p = pending.get(m.id); if (p) { pending.delete(m.id); m.error ? p.reject(Error(m.error.message)) : p.resolve(m.result); } }
    if (m.method === 'Network.responseReceived' && m.params.type === 'Document') network.push({ url:m.params.response.url, status:m.params.response.status, protocol:m.params.response.protocol });
    if (m.method === 'Network.requestWillBeSent' && m.params.redirectResponse) { const r=m.params.redirectResponse; network.push({url:r.url,status:r.status,location:r.headers.Location ?? r.headers.location}); }
  });
  await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable');
  await send('Network.setBlockedURLs', { urls: ['*://fonts.googleapis.com/*', '*://fonts.gstatic.com/*', '*://ui-avatars.com/*'] });
  await send('Emulation.setDeviceMetricsOverride', { width:1280,height:1000,deviceScaleFactor:1,mobile:false });
  if (!resume) {
  await go('/privacy');
  await capture('01-privacy-notice', 'Privacy notice explains the data collected and its purposes');
  result.checks.privacy_notice = true;
  await go('/register');
  await capture('02-signup-consent', 'Signup contains an unchecked, required privacy consent checkbox');
  result.checks.consent_initially_unchecked = await evaluate("!document.querySelector('#privacy-consent').checked && document.querySelector('#privacy-consent').required");
  result.checks.signup_data_fields = await evaluate("Array.from(document.querySelectorAll('#register-panel input:not([type=hidden]),#register-panel select')).map(e=>({name:e.name,type:e.type,required:e.required}))");
  await fill('#reg_name', 'Topic 9 No Consent'); await fill('#reg_email','topic9.noconsent@example.test');
  await fill('#reg_password','Demo-Only-12345'); await fill('#reg_password_confirmation','Demo-Only-12345');
  await submit('#register-panel form','/register','Please read and agree to the privacy notice.');
  await capture('03-consent-enforced', 'Server rejects registration without privacy consent');
  result.checks.server_rejects_missing_consent = true;
  await go('/register');
  await fill('#reg_name','Topic 9 Weak Password'); await fill('#reg_email','topic9.weak@example.test');
  await fill('#reg_password','123'); await fill('#reg_password_confirmation','123');
  await evaluate("document.querySelector('#privacy-consent').checked=true");
  await submit('#register-panel form','/register','at least 8 characters');
  await capture('04-weak-password', 'Weak password 123 is rejected by server-side validation');
  result.checks.weak_password_rejected = true;
  await login('topic9.buyer@example.test');
  await go('/account/profile/edit');
  await fill('#profile-name','Topic 9 Updated Dummy Buyer'); await fill('#profile-current-password','Demo-Only-12345');
  await submit('form[action$="/account/profile"]','/account/profile','Topic 9 Updated Dummy Buyer');
  await capture('05-profile-update','Buyer successfully updates their own profile');
  result.checks.profile_updated = true;
  await go('/account/profile/edit');
  await fill('#deletion-request-confirm','DELETE');
  await submit('form[action$="/account/privacy/deletion-request"]','/account/profile/edit','Request status: Pending');
  await capture('06-deletion-request','Buyer with an order can submit a deletion request for admin review');
  result.checks.deletion_request_submitted = true;
  await go('/admin');
  const adminResponse = [...network].reverse().find(r=>r.url===base+'/admin');
  if (adminResponse?.status !== 403) throw Error('Buyer unexpectedly accessed admin');
  await capture('07-admin-blocked','Buyer is blocked from the admin page with HTTP 403');
  result.checks.buyer_admin_status = adminResponse.status;
  await go('/account/profile'); await logout();
  await go('/orders','/login');
  await capture('08-logout-orders','After logout, My Orders redirects to login');
  result.checks.orders_after_logout = {final_url:await evaluate('location.href'),redirect:[...network].reverse().find(r=>r.url===base+'/orders'&&r.status===302)};
  await go('/account/profile','/login');
  result.checks.profile_after_logout = {final_url:await evaluate('location.href'),redirect:[...network].reverse().find(r=>r.url===base+'/account/profile'&&r.status===302)};
  await capture('09-logout-profile','After logout, the profile page also redirects to login');
  result.checks.transport = await evaluate('({url:location.href,scheme:location.protocol,secureContext:window.isSecureContext})');
  result.checks.transport.https_pass = result.checks.transport.scheme === 'https:';
  await login('topic9.admin@example.test');
  await go('/admin/privacy-requests');
  await fill('textarea[name="response"]','Your request has been reviewed. Please coordinate with the project administrator to confirm deletion and identify transaction records that need to be retained. No account data has been erased by this review.');
  await submit('form[action$="/review"]','/admin/privacy-requests','Reviewed');
  await capture('10-admin-privacy-review','Admin records a review response without deleting order history');
  await go('/account/profile'); await logout();
  } else if (result.screenshots.length !== 10) {
    throw Error('Resume is only supported after the first ten evidence screenshots.');
  }
  await login('topic9.buyer@example.test');
  await go('/account/profile/edit');
  await capture('11-owner-privacy-response','Account holder can read the admin response in profile settings');
  result.database_inspection = JSON.parse(php(['tools/security-check/inspect.php']));
  if (result.database_inspection.password_algorithm !== 'bcrypt' || result.database_inspection.plaintext_password_stored || !result.database_inspection.password_verification_succeeds || result.database_inspection.weak_signup_account_exists || result.database_inspection.no_consent_account_exists) throw Error('Database verification failed');
  viewer('Database password inspection', JSON.stringify(result.database_inspection,null,2), 'database-inspection.html');
  await go(pathToFileURL(join(output,'database-inspection.html')).href);
  await capture('12-database-hash','Direct SQLite inspection confirms bcrypt hashes and no rejected signup accounts','Screenshot of evidence viewer displaying direct database query output');
  const transportText = `Test URL: ${result.checks.transport.url}\nObserved scheme: ${result.checks.transport.scheme}\nHTTPS test: FAIL (local HTTP server)\n\nLoopback secure-context flag: ${result.checks.transport.secureContext}\nA localhost secure-context allowance is not HTTPS or TLS.\nNo trusted certificate or browser padlock was demonstrated.\n\nLogout / My Orders:\n${JSON.stringify(result.checks.orders_after_logout,null,2)}\n\nLogout / Profile:\n${JSON.stringify(result.checks.profile_after_logout,null,2)}`;
  viewer('Transport and logout network evidence',transportText,'transport-inspection.html');
  await go(pathToFileURL(join(output,'transport-inspection.html')).href);
  await capture('13-transport-and-redirects','Observed HTTP scheme and actual logout redirect responses','Screenshot of evidence viewer displaying captured browser network output');
  writeFileSync(join(output,'test-results.json'),JSON.stringify(result,null,2));
  console.log('Browser security checks complete. HTTPS recorded as FAIL for local HTTP.');
  // Separate report generation uses only captured results and image files.
  const { buildReport } = await import('./report.mjs');
  buildReport(output, result);
  await go(pathToFileURL(join(output,'SIA1_Topic9_Group_Section.html')).href);
  await evaluate('document.fonts.ready');
  const pdf = await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true,displayHeaderFooter:false});
  writeFileSync(join(output,'SIA1_Topic9_Group_Section.pdf'),Buffer.from(pdf.data,'base64'));
  console.log('Report PDF created: security-report/SIA1_Topic9_Group_Section.pdf');
} catch(error) {
  writeFileSync(join(output,'test-results.partial.json'),JSON.stringify(result,null,2));
  try { console.error('Browser diagnostic:',await evaluate('({url:location.href,text:document.body?.innerText?.slice(0,3000)})')); } catch {}
  console.error(serverErrors.slice(-2000));
  throw error;
} finally {
  if (socket?.readyState===WebSocket.OPEN) { try { await send('Browser.close'); } catch {} socket.close(); }
  server.kill(); edge.kill();
}
