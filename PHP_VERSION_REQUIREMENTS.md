# PHP Version Requirements - Unnatti ERP

## 🔒 Security Requirements

This application requires **PHP 8.4.27 or later** to run safely.

### Critical Security Vulnerabilities Patched

In October 2026, two critical remote code execution (RCE) vulnerabilities were discovered in PHP:

- **CVE-2026-109618** - RCE via OOM corruption
- **CVE-2026-109619** - RCE via stale global state

Both vulnerabilities allow **unauthenticated remote attackers** to execute arbitrary code on your server.

## ✅ Supported PHP Versions

| PHP Version | Status | Security Level |
|-------------|--------|----------------|
| **8.4.27+** | ✅ **Recommended** | Fully Patched & Secure |
| **8.3.36+** | ✅ Supported | Patched (older branch) |
| 8.3.35 or lower | ❌ **VULNERABLE** | Critical RCE flaws |
| 8.2.x | ❌ **VULNERABLE** | Critical RCE flaws |
| 8.1.x | ❌ **VULNERABLE** | Critical RCE flaws |
| 8.0.x | ❌ **VULNERABLE** | Critical RCE flaws + EOL |
| 7.x | ❌ **VULNERABLE** | Multiple issues + EOL |

## 📥 How to Install PHP 8.4.27

### Option 1: XAMPP (Recommended for Windows)

1. **Download:** [XAMPP 8.4.27](https://www.apachefriends.org/download.html)
2. **Install to:** `H:\xampp`
3. **Includes:** PHP 8.4.27, MySQL, Apache, phpMyAdmin

### Option 2: Standalone PHP

1. **Download:** [PHP 8.4.27 for Windows](https://windows.php.net/download/)
2. **Get:** VS16 x64 Thread Safe ZIP
3. **Extract to:** `H:\php-8.4.27`
4. **Configure:** Add to PATH, enable extensions

## 🔍 Version Check

The application automatically checks your PHP version when it starts:

- ✅ **PHP 8.4.27+** → Application runs normally
- ✅ **PHP 8.3.36+** → Application runs with warning logged
- ❌ **Below 8.3.36** → Application BLOCKS with error page

### Manual Check

Check your current PHP version:

```bash
php -v
```

Expected output:
```
PHP 8.4.27 (cli) (built: Oct 15 2026 ...)
```

## 🛡️ Why This Matters

Running vulnerable PHP versions exposes your business to:

- 🔴 Remote attackers taking full control of your server
- 🔴 Data theft (customer info, financial records)
- 🔴 Malware installation
- 🔴 Business disruption
- 🔴 Legal liability for data breaches

## 📚 References

- [PHP Official Security Announcement](https://www.php.net/archive/2026.php#2026-10-21-1)
- [CISA Alert on PHP Vulnerabilities](https://www.cisa.gov/news-events/alerts/2026/10/21/critical-vulnerabilities-php-require-immediate-patching)
- [CVE-2026-109618 Details](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2026-109618)
- [CVE-2026-109619 Details](https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2026-109619)

## 🆘 Support

If you need help upgrading PHP:

1. Check the installation guide: `XAMPP Installation on H Drive - Complete Guide.md`
2. Verify your installation: Run `php -v` in command prompt
3. Test the application: Visit `http://localhost/unnatti/`

---

**Last Updated:** November 11, 2026  
**Minimum PHP Version:** 8.4.27 (or 8.3.36)  
**Reason:** Critical security patches for CVE-2026-109618 & CVE-2026-109619
