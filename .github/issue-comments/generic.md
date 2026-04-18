## Thanks for opening an issue! 🙏

GitHub issues are exactly where this belongs — it's how we track and ship work.

We couldn't auto-detect the issue type. To help us route this correctly, please do one of the following:

1. **Add a label**: `bug`, `question`, `enhancement`, etc. (if you have permission)
2. **Re-create with a template**: Close this issue and [create a new one](../../issues/new/choose) using the Bug Report, Question, or Feature Request form.
3. **Just tell us**: Reply below with the issue type and relevant details.

---

**For bug reports**, please include:
- Short summary of the problem
- Steps to reproduce
- **Server-side logs** (see below)
- Screenshots (redact private data)

**For questions**, please include:
- What you're trying to do
- What you've already tried

---

### 📋 How to collect server-side error logs

**1. In-app (admins):** Admin → System Maintenance → System Logs (or the 🐞 debug icon)
**2. PHP error log:** `/var/log/php-fpm/error.log`, `/var/log/apache2/error.log`, `/var/log/nginx/error.log`
**3. Docker:** `docker logs <container-name> --tail 200`
**4. Browser DevTools:** Console + Network tabs

📘 **[Diagnostics Guide](https://docs.churchcrm.io/administration/bug-reporting-and-diagnostics)**

---

**Note:** ChurchCRM is maintained by volunteers, so GitHub response times can vary. If it's urgent or you'd like to chat, drop into [Discord](https://discord.gg/tuWyFzj3Nj) — we'll still track everything here. Thanks for your patience! 🙏
