## Thanks for the bug report! 🐛

💬 **Need help fast?** Ask in our Discord — often the quickest way to reach maintainers:
👉 **<https://discord.gg/tuWyFzj3Nj>**

To help us investigate quickly, please make sure the issue includes:

- **ChurchCRM version** — check the page footer or **Admin → System Settings**
- **Problem summary** — one sentence describing what went wrong
- **Steps to reproduce** — numbered steps are best
- **Server-side logs** (see below)
- **Screenshots** — please redact private data

---

### 📋 How to collect server-side error logs

**1. In-app (easiest, admins only)**
👉 **Admin → System Maintenance → System Logs** — or click the 🐞 debug icon in the footer.

**2. PHP error log (server shell access)**
Typical locations:
- `/var/log/php-fpm/error.log`
- `/var/log/php*-fpm.log`
- `/var/log/apache2/error.log`
- `/var/log/nginx/error.log`
- or wherever `error_log` points in your `php.ini`

Run `tail -n 200 <path>` right after reproducing the bug.

**3. Docker users**
```sh
docker logs <container-name> --tail 200
```

**4. Browser DevTools**
Open DevTools → **Console** + **Network** → reproduce → paste any red errors / failed requests.

⚠️ **Redact any private data** (names, emails, API keys) before pasting.

---

**Power users:** A [Cypress Recorder](https://docs.cypress.io/) exported spec helps us add a regression test quickly.

📘 **[Bug Reporting & Diagnostics Guide](https://docs.churchcrm.io/administration/bug-reporting-and-diagnostics)**

---

**Note:** ChurchCRM is maintained by volunteers. Response times on GitHub vary — **Discord is usually faster**. Thanks for your patience! ❤️
