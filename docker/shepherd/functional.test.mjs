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
for (const body of [{},{User:'x'},{Password:'x'},{User:[],Password:{}},null]) {
  check((await anonymous.request('/session/begin',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).status < 500, 'malformed login input is handled without a server error');
}
check((await anonymous.request('/session/two-factor')).status === 302, 'anonymous second-factor page redirects to login');
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

const postJSON = (browser, path, body = {}, csrfToken = csrf) => browser.request(path, {method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-Token':csrfToken},body:JSON.stringify(body)});
check((await member.request('/plugins/api/plugins/signup-sheets/enable',{method:'POST',headers:{Accept:'application/json','X-CSRF-Token':csrf}})).status === 403, 'member cannot enable plugins');
check((await postJSON(admin, '/plugins/api/plugins/signup-sheets/enable', {}, '')).status === 403, 'plugin changes enforce CSRF');
check((await postJSON(admin, '/plugins/api/plugins/signup-sheets/enable')).status === 200, 'signup plugin enables and provisions its schema');
check((await admin.request('/plugins/signup-sheets',{headers:{Accept:'text/html'}})).status === 200, 'signup sheets list renders');
check((await postJSON(admin, '/plugins/signup-sheets/api/sheets', {title:'Disposable sheet'}, '')).status === 403, 'sheet writes enforce CSRF');
const createdSheet = await postJSON(admin, '/plugins/signup-sheets/api/sheets', {title:'Disposable upgrade sheet',status:'open',isPublic:true});
check(createdSheet.status === 201, 'administrator creates a public signup sheet');
const sheetId = (await createdSheet.json()).sheetId;
const createdSlot = await postJSON(admin, `/plugins/signup-sheets/api/sheets/${sheetId}/slots`, {title:'Disposable volunteer',capacity:1});
check(createdSlot.status === 201, 'administrator creates a signup slot');
const slotId = (await createdSlot.json()).slotId;
const sheetPage = await (await admin.request(`/plugins/signup-sheets/${sheetId}`,{headers:{Accept:'text/html'}})).text();
const sheetToken = /\/external\/signup-sheets\/([a-zA-Z0-9_-]+)/.exec(sheetPage)?.[1];
check(Boolean(sheetToken), 'sheet exposes its preserved public URL');
check((await anonymous.request(`/external/signup-sheets/${sheetToken}`)).status === 200, 'public signup sheet is reachable');
const claimed = await anonymous.request(`/external/signup-sheets/${sheetToken}/claim`, {method:'POST',body:new URLSearchParams({slotId,name:'Disposable volunteer',email:'volunteer@example.invalid',quantity:1})});
const claimHTML = await claimed.text();
const claimToken = /\/external\/signup-sheets\/manage\/([a-zA-Z0-9_-]+)/.exec(claimHTML)?.[1];
check(claimed.status === 200 && Boolean(claimToken), 'public visitor claims a slot and receives a management link');
const fullSheet = await (await anonymous.request(`/external/signup-sheets/${sheetToken}`)).text();
check(!fullSheet.includes('volunteer@example.invalid'), 'public roster withholds volunteer contact information');
check((await anonymous.request(`/external/signup-sheets/manage/${claimToken}/cancel`,{method:'POST'})).status === 200, 'volunteer can cancel their own claim');
check((await postJSON(admin, '/plugins/api/plugins/signup-sheets/disable')).status === 200, 'signup plugin disables');
check((await anonymous.request(`/external/signup-sheets/${sheetToken}`)).status === 404, 'disabled plugin withdraws public routes');
check((await postJSON(admin, '/plugins/api/plugins/signup-sheets/enable')).status === 200, 'signup plugin re-enables');
check((await anonymous.request(`/external/signup-sheets/${sheetToken}`)).status === 200, 're-enabling preserves the sheet and public URL');
check((await admin.request(`/plugins/signup-sheets/api/sheets/${sheetId}`,{method:'DELETE',headers:{Accept:'application/json','X-CSRF-Token':csrf}})).status === 200, 'disposable signup sheet is removed');

async function capturedMail(address, subject) {
  for(let attempt=0;attempt<15;attempt++) {
    const list = await (await fetch(origin+'/_preview-mail/api/v1/messages')).json();
    const message = list.messages?.find(message => message.Subject.includes(subject) && message.To.some(to=>to.Address===address));
    if(message) return await (await fetch(origin+'/_preview-mail/api/v1/message/'+message.ID)).json();
    await new Promise(resolve=>setTimeout(resolve,200));
  }
  throw new Error('Expected message was not captured by local Mailpit: '+subject);
}
const applicant = new Browser();
const signupHTML = await (await applicant.request('/session/signup')).text();
const signupCSRF = token(signupHTML);
const username = 'test.signup.'+Date.now();
const email = username+'@example.invalid';
const signupBody = new URLSearchParams({first_name:'Disposable',last_name:'Applicant',username,email,csrf_token:signupCSRF});
check((await applicant.request('/session/signup',{method:'POST',headers:{'X-API-Key':'invalid'},body:new URLSearchParams({first_name:'Bypass'})})).status === 403, 'arbitrary API-key header cannot bypass public CSRF');
check((await applicant.request('/session/signup',{method:'POST',body:signupBody})).status === 200, 'account request submitted');
const verificationMail = await capturedMail(email,'Verify your account request');
const verificationURL = /https?:\/\/[^\s"<>]+\/session\/signup\/verify\?token=[a-f0-9]+/.exec(verificationMail.HTML)?.[0];
check(Boolean(verificationURL), 'account verification message is captured locally');
const expectedOrigin = process.env.TEST_PUBLIC_ORIGIN || origin.replace('127.0.0.1','localhost');
check(verificationURL.startsWith(expectedOrigin+root+'/session/'), 'verification link uses the configured preview origin and base path');
check((await applicant.request(new URL(verificationURL).pathname.slice(root.length)+new URL(verificationURL).search)).status === 200, 'email verification completes');
check((await applicant.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 401, 'email verification alone grants no account session');
const requestsHTML = await (await admin.request('/admin/shepherd/signup-requests',{headers:{Accept:'text/html'}})).text();
const applicantSection = requestsHTML.slice(requestsHTML.indexOf(email));
const requestId = /\/signup-requests\/(\d+)\/approve/.exec(applicantSection)?.[1];
check(Boolean(requestId), 'verified request appears in native administration');
check((await member.request(`/admin/shepherd/signup-requests/${requestId}/approve`,{method:'POST',headers:{Accept:'application/json'},body:new URLSearchParams({csrf_token:csrf})})).status === 403, 'non-administrator cannot approve signup');
const approval = await admin.request(`/admin/shepherd/signup-requests/${requestId}/approve`,{method:'POST',body:new URLSearchParams({csrf_token:csrf,profile:'self_service',existing_person_id:'0'})});
check(approval.status === 303 && !approval.headers.get('Location')?.includes('error='), 'administrator approves the verified request');
const setupMail = await capturedMail(email,'Your account request was approved');
const setupURL = /https?:\/\/[^\s"<>]+\/session\/signup\/password\/[a-f0-9]+/.exec(setupMail.HTML)?.[0];
check(Boolean(setupURL) && setupURL.startsWith(expectedOrigin+root+'/session/'), 'single-use password setup link uses the preview origin');
const setupPath = new URL(setupURL).pathname.slice(root.length);
const setupHTML = await (await applicant.request(setupPath)).text();
const setupResponse = await applicant.request(setupPath,{method:'POST',body:new URLSearchParams({csrf_token:token(setupHTML),password:'New-applicant-password-2026!',password_confirmation:'New-applicant-password-2026!'})});
check((await setupResponse.text()).includes('Your password is set'), 'approved applicant sets a password');
check(!(await (await applicant.request(setupPath)).text()).includes('name="password_confirmation"'), 'password setup token cannot be reused');

const resetBrowser = new Browser();
check((await resetBrowser.request('/session/forgot-password/reset-request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({userName:fixtures.member.username})})).status === 200, 'password reset request accepted');
const resetMail = await capturedMail(fixtures.member.username+'@example.invalid','Password Reset Link');
const resetURL = /https?:\/\/[^\s"<>]+\/session\/forgot-password\/set\/[a-zA-Z0-9_-]+/.exec(resetMail.HTML)?.[0];
check(Boolean(resetURL) && resetURL.startsWith(expectedOrigin+root+'/session/'), 'password reset link is captured with the preview origin');
const resetPath = new URL(resetURL).pathname.slice(root.length);
const resetHTML = await (await resetBrowser.request(resetPath)).text();
check(resetHTML.includes('csrf_token'), 'reset GET renders confirmation without changing credentials');
check((await member.request('/api/user/current/2fa-status',{headers:{Accept:'application/json'}})).status === 200, 'reset GET leaves existing authenticated session valid');
check((await resetBrowser.request(resetPath,{method:'POST'})).status === 403, 'password reset mutation requires CSRF');
check((await resetBrowser.request(resetPath,{method:'POST',body:new URLSearchParams({csrf_token:token(resetHTML)})})).status === 200, 'confirmed password reset completes');
check((await member.request('/api/user/current/2fa-status',{headers:{Accept:'application/json'}})).status === 401, 'password reset invalidates existing sessions');
check(!(await (await resetBrowser.request(resetPath)).text()).includes('csrf_token'), 'password reset token cannot be reused');

await admin.request('/session/end');
check((await admin.request('/api/background/website-content/session',{headers:{Accept:'application/json'}})).status === 401, 'logout invalidates editor session');
console.log(`${checks} functional checks passed.`);
