import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createHmac } from 'node:crypto';

const origin = process.env.TEST_ORIGIN || 'http://127.0.0.1:18088';
const root = process.env.TEST_ROOT ?? '/shepherd';
const fixtures = JSON.parse(readFileSync(process.argv[2], 'utf8'));
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; console.log(`PASS ${message}`); }
function token(html) { return /name="csrf_token"[^>]*value="([^"]+)"/.exec(html)?.[1] || /data-csrf-token="([^"]+)"/.exec(html)?.[1]; }
function totp(secret) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const bits = [...secret].map((c) => alphabet.indexOf(c).toString(2).padStart(5,'0')).join('');
  const bytes = bits.match(/.{8}/g).map((byte) => parseInt(byte, 2));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));
  const hash = createHmac('sha1', Buffer.from(bytes)).update(counter).digest();
  return String((hash.readUInt32BE(hash[19]&15)&0x7fffffff)%1000000).padStart(6,'0');
}
class Browser {
  cookies = new Map();
  async request(path, options = {}) {
    const headers = { Cookie: [...this.cookies].map(([k,v]) => `${k}=${v}`).join('; '), ...options.headers };
    const response = await fetch(origin + root + path, { ...options, headers, redirect: 'manual' });
    if (process.env.TEST_DEBUG) console.log(options.method || 'GET', path, response.status, response.headers.get('Location') || '');
    for (const cookie of response.headers.getSetCookie()) {
      const [key, ...value] = cookie.split(';')[0].split('=');
      this.cookies.set(key, value.join('='));
    }
    return response;
  }
  async login(account, factor) {
    await this.request('/session/begin');
    let response = await this.request('/session/begin', { method: 'POST', body: new URLSearchParams({ User: account.username, Password: 'Upgrade-test-password-2026!' }) });
    if (factor) {
      check(response.headers.get('Location')?.includes('/session/two-factor'), 'enrolled login requires second factor');
      const denied = await this.request('/api/background/website-content/session', { headers: { Accept: 'application/json' } });
      check([401,403].includes(denied.status), 'pending factor cannot authorize website editing');
      response = await this.request('/session/two-factor?recovery', { method: 'POST', body: new URLSearchParams({ TwoFACode: factor }) });
    }
    check(response.status === 302, 'login completes with a redirect');
  }
}
const anonymous = new Browser();
check((await anonymous.request('/healthz')).status === 200, 'readiness');
check((await anonymous.request('/api/public/website-content/home')).status === 200, 'public content API');
check((await anonymous.request('/api/background/website-content/session', {headers:{Accept:'application/json'}})).status === 401, 'anonymous editor refusal');
for (const path of ['/Include/Config.php','/vendor/autoload.php','/mysql/install/Install.sql','/Images/Person/private.jpg','/admin/views/users.php','/session/templates/begin-session.php']) {
  check((await anonymous.request(path)).status === 404, `private path denied: ${path}`);
}
const member = new Browser();
await member.login(fixtures.member);
check((await member.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 403, 'member cannot authorize CMS');
check((await member.request('/admin/api/user/1/permissions',{headers:{Accept:'application/json'}})).status === 403, 'member cannot access administration API');
const enrollment = new Browser();
await enrollment.login(fixtures.enroll);
check((await enrollment.request('/v2/user/current/manage2fa', {headers:{Accept:'text/html'}})).status === 200, 'required enrollment page has no redirect loop');
check((await enrollment.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 403, 'unenrolled administrator cannot authorize CMS');
check((await anonymous.request('/api/background/website-content/session', {headers:{'x-api-key':fixtures.enroll.apiKey,Accept:'application/json'}})).status === 401, 'unenrolled administrator API token refused');
const enrollmentPage = await (await enrollment.request('/v2/user/current/manage2fa', {headers:{Accept:'text/html'}})).text();
const enrollmentCSRF = token(enrollmentPage);
check(typeof enrollmentCSRF === 'string', 'enrollment exposes a session CSRF token');
const securityPost = (browser, path, body = {}) => browser.request(`/api/user/current/${path}`, {method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':enrollmentCSRF},body:JSON.stringify(body)});
const provision = await securityPost(enrollment, 'refresh2fasecret');
check(provision.status === 200, '2FA provisioning succeeds');
const provisioned = await provision.json();
check(typeof provisioned.TwoFASecret === 'string', '2FA secret can be entered manually');
const confirmed = await securityPost(enrollment, 'test2FAEnrollmentCode', {enrollmentCode:totp(provisioned.TwoFASecret)});
check(confirmed.status === 200 && (await confirmed.json()).IsEnrollmentCodeValid, '2FA enrollment survives between requests');
const recovery = await securityPost(enrollment, 'refresh2farecoverycodes');
check(recovery.status === 200 && (await recovery.json()).TwoFARecoveryCodes.length === 12, 'enrolled user receives recovery codes');
check((await enrollment.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 200, 'enrolled administrator gains CMS access');
const admin = new Browser();
await admin.login(fixtures.admin, fixtures.admin.recoveryCodes[0]);
const session = await admin.request('/api/background/website-content/session', {headers:{Accept:'application/json'}});
check(session.status === 200, 'completed administrator can authorize CMS');
const sessionData = await session.json();
const csrf = sessionData.CSRFToken;
check(typeof csrf === 'string' && csrf.length === 64, 'editor issues CSRF token');
const document = await (await admin.request('/api/public/website-content/home')).json();
const payload = JSON.stringify({ revision: document.revision, content: { 'home.upgrade-test': { base: 'Original', value: `Disposable verification ${Date.now()}` } } });
check((await admin.request('/api/website-content/home', {method:'PUT',headers:{'Content-Type':'application/json'},body:payload})).status === 403, 'editor enforces CSRF');
const saved = await admin.request('/api/website-content/home', {method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:payload});
check(saved.status === 200, 'editor saves isolated content');
const changed = await saved.json();
check((await admin.request('/api/website-content/home', {method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:payload})).status === 409, 'editor rejects stale revision');
check((await admin.request('/api/website-content/home', {method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({revision:changed.revision,content:document.content})})).status === 200, 'editor restores original test content');
check((await admin.request('/admin/system/users',{headers:{Accept:'text/html'}})).headers.get('Content-Type')?.includes('text/html'), 'CRM admin page returns HTML');
const adminAPI = await admin.request(`/admin/api/user/${fixtures.member.id}/permissions`,{headers:{Accept:'application/json'}});
check(adminAPI.status === 200 && adminAPI.headers.get('Content-Type')?.includes('application/json'), 'CRM admin API returns JSON');
check((await anonymous.request('/api/user/current/refresh2farecoverycodes', {method:'POST',headers:{'x-api-key':fixtures.admin.apiKey,Accept:'application/json'}})).status === 403, 'API token cannot change account security');
check((await admin.request('/api/user/current/remove2fasecret', {method:'POST',headers:{Accept:'application/json'}})).status === 403, 'factor removal enforces CSRF');
const replay = new Browser();
await replay.login(fixtures.admin, fixtures.admin.recoveryCodes[0]);
check((await replay.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 401, 'recovery code cannot be reused');
const memberSecondSession = new Browser();
await memberSecondSession.login(fixtures.member);
check((await memberSecondSession.request('/api/user/current/2fa-status', {headers:{Accept:'application/json'}})).status === 200, 'second member session is active');
const passwordPage = await (await member.request('/v2/user/current/changepassword', {headers:{Accept:'text/html'}})).text();
const passwordCSRF = token(passwordPage);
const changedPassword = await member.request('/v2/user/current/changepassword', {method:'POST',body:new URLSearchParams({csrf_token:passwordCSRF,OldPassword:'Upgrade-test-password-2026!',NewPassword1:'Replacement-credential-2026!',NewPassword2:'Replacement-credential-2026!'})});
check([200,302].includes(changedPassword.status), 'self-service password change completes');
check((await memberSecondSession.request('/api/user/current/2fa-status',{headers:{Accept:'application/json'}})).status === 401, 'password change invalidates the other session');
check((await member.request('/api/user/current/2fa-status',{headers:{Accept:'application/json'}})).status === 200, 'password change preserves the acting session');
await admin.request('/session/end');
check((await admin.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 401, 'logout invalidates editor session');
console.log(`${checks} functional checks passed.`);
