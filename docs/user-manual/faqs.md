# Frequently Asked Questions

> 💬 **Have a question not answered here?** Post it in [GitHub Discussions](https://github.com/ChurchCRM/CRM/discussions) — the community is very active and helpful.

---

## Installation & First Login

### How do I log in to a fresh install?

The installation wizard creates an admin user with these credentials:

- **Username:** `admin`
- **Password:** `changeme`

**Change this password immediately** after your first login.

### I get a message about "Secret Keys missing from Config.php"

Two-factor authentication (2FA) was added in v4.x and requires a manual change to the `Config.php` file to clear this message. The system will still work normally if you ignore it, but you cannot use 2FA without making the required change. See the [Secret Keys in Config.php documentation](https://github.com/ChurchCRM/CRM/wiki/Secret-Keys-in-Config.php) for details.

---

## Maps & Geographic Features

### How do I set up map features?

ChurchCRM supports two mapping providers — choose one:

- **Google Maps API**
- **Microsoft Bing Maps**

Both require external configuration before use. See the [Geographic Features documentation](https://github.com/ChurchCRM/CRM/wiki/Geographic) for setup instructions.

---

## Errors & Troubleshooting

### I get "Too Many Redirects" or errors making API calls

Check whether `mod_rewrite` is enabled on your server. See [issue #3153](https://github.com/ChurchCRM/CRM/issues/3153) for diagnostic steps.

### Internal Server Error 500

See the [500 Error guide](https://github.com/ChurchCRM/CRM/wiki/500-Error). The most common cause is incorrect file permissions — ensure files are set to `644` and folders to `755`. See [File System Permissions](https://github.com/ChurchCRM/CRM/wiki/File-System-Permissions).

### How do I enable detailed error reporting?

In your `Config.php` file, change:

```
error_reporting(E_ERROR);
```

to:

```
error_reporting(E_ALL);
```

See the full list of [PHP error reporting constants](http://php.net/manual/en/errorfunc.constants.php).

### How do I enable application logs?

1. Go to **Admin → System Settings**
2. Find the logging settings and set the log level (default is INFO; use DEBUG for more detail)
3. Logs are created in the `/logs` directory

> **Note:** Logs are not automatically cleaned. It is the administrator's responsibility to remove old log files.

---

## Apache Configuration

### Apache2 VirtualHost Config

See the [cloud9 config example](https://github.com/ChurchCRM/CRM/blob/master/cloud9/001-cloud9.conf) as a reference for `mod_rewrite` configuration.

### My host doesn't have `register_globals` turned OFF

Create a file called `.htaccess` in the ChurchCRM root directory and add:

```
php_flag register_globals off
```

---

## Logos & Reports

### How do I add my church's logo or letterhead?

Many reports allow you to include a logo or letterhead. The default image files are in the `/Images` directory. **Do not rename your image to match the default filenames** — system upgrades will overwrite them.

Instead, follow these steps:

1. Ensure your image is **500×80 pixels** (or an exact multiple of this ratio, e.g. 1000×160). PNG or JPEG format. Only PNG supports transparency.
2. Upload your image to the `/Images` directory via FTP/sFTP/SSH
3. Log in to ChurchCRM with admin privileges
4. Go to **Admin → Edit General Settings → Report Settings**
5. Change the value of `sDirLetterHead` to `../Images/<your_file_name>` and click **Save Settings**

---

## Getting More Help

- 💬 **[GitHub Discussions](https://github.com/ChurchCRM/CRM/discussions)** — Community Q&A (most active)
- 🐛 **[Report a Bug](https://github.com/ChurchCRM/CRM/wiki/Reporting-Issues)**
- 📖 **[Full Troubleshooting Guide](https://github.com/ChurchCRM/CRM/wiki/Troubleshooting)**
- 🔧 **[Admin Guide](https://github.com/ChurchCRM/CRM/wiki#-administrators)** — Server setup and maintenance
