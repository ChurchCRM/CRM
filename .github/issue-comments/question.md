## Thanks for the question! ❓

💬 **Fastest answer: ask in Discord** — maintainers and experienced users hang out there:
👉 **<https://discord.gg/tuWyFzj3Nj>**

To help us answer quickly here on GitHub, please make sure to include:

- **What you're trying to do** — your goal or workflow
- **What you've tried** — steps, settings, or docs you've checked
- **Expected behavior** — what you thought would happen

---

### 📋 If your question involves an error

**1. In-app logs (easiest, admins only)**
👉 **Admin → System Maintenance → System Logs** — or click the 🐞 debug icon in the footer.

**2. PHP error log (server shell access)**
- `/var/log/php-fpm/error.log`
- `/var/log/apache2/error.log`
- `/var/log/nginx/error.log`
- or wherever `error_log` points in `php.ini`

**3. Docker**
```sh
docker logs <container-name> --tail 200
```

Please redact any private data before pasting.

---

**Power users:** A [Cypress Recorder](https://docs.cypress.io/) capture helps us reproduce UI flows.

📘 **[Diagnostics Guide](https://docs.churchcrm.io/administration/bug-reporting-and-diagnostics)**

---

**Note:** ChurchCRM is maintained by volunteers. Response times on GitHub vary — **Discord is usually faster**. Thanks for asking! 🙏
