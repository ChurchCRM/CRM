## Thanks for the detailed report! 🙏

We can see you've included system diagnostics — very helpful. We'll review this and respond as soon as we can.

💬 **Want a quicker reply?** Drop into our Discord — maintainers often respond there first:
👉 **<https://discord.gg/tuWyFzj3Nj>**

---

### 🧭 If you saw a server-side error, please add the logs

**1. In-app (easiest, admins only)**
👉 **Admin → System Maintenance → System Logs** — or click the 🐞 debug icon in the footer.

**2. PHP error log (server shell access)**
- `/var/log/php-fpm/error.log`
- `/var/log/apache2/error.log`
- `/var/log/nginx/error.log`
- or wherever `error_log` points in your `php.ini`

Run `tail -n 200 <path>` right after reproducing the bug.

**3. Docker users**
```sh
docker logs <container-name> --tail 200
```

**4. Browser DevTools**
Open Console + Network tab → reproduce → paste any errors / failed requests.

⚠️ **Please redact private data** (names, emails, API keys) before pasting.

---

**Please note:** ChurchCRM is maintained by volunteers. While we try to respond quickly, there may be a delay. If you add logs, screenshots, or steps to reproduce, it speeds things up a lot.

Thanks for helping us improve ChurchCRM! 🙌
