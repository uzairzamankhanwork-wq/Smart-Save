# Setting up SmartSave on a new PC (Windows + VS Code)

## 1. Install the tools (once)

1. **PHP 8.3 or newer**: easiest is <https://php.new> (one command, includes Composer). Or download from <https://windows.php.net/download> and add the folder to `PATH`.
2. **Composer**: <https://getcomposer.org/download/> (skip if php.new installed it).
3. **Node.js LTS**: <https://nodejs.org>
4. **VS Code**: <https://code.visualstudio.com>

Then open a **new** terminal and check:

```bash
php -v
```

```bash
composer -V
```

```bash
node -v
```

## 2. Switch on the PHP extensions SmartSave needs

```bash
php --ini
```

This shows which `php.ini` is used. Open that file **as Administrator** and make sure these lines exist without a leading `;`:

```ini
extension=bcmath
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=pdo_mysql
```

(If `php.ini` doesn't exist, copy `php.ini-development` to `php.ini` in the PHP folder first.)
Check with `php -m`. You should see `bcmath` and `pdo_sqlite` in the list.

## 3. Open the project

1. Unzip `SmartSave.zip`, e.g. to `C:\Projects\SmartSave`.
2. In VS Code: **File → Open Folder…** and choose the `SmartSave` folder.
3. Accept the **recommended extensions** pop-up (Laravel, PHP Intelephense, Tailwind CSS).

## 4. First-time setup (one command)

In VS Code's terminal (**Terminal → New Terminal**):

```bash
composer setup
```

This installs the PHP and JavaScript libraries, creates `.env`, generates a secret app key, creates the SQLite database and tables, and builds the CSS/JS. It takes a few minutes the first time.

## 5. Create your admin account

```bash
php artisan smartsave:create-admin
```

Optional, to explore with sample data instead (demo logins `ayesha@smartsave.test` / `DemoPass123`):

```bash
php artisan db:seed --class=DemoSeeder
```

## 6. Run it

```bash
php artisan serve
```

Open <http://localhost:8000>. Keep the terminal open while using the app (`Ctrl+C` stops it).

**From a phone on the same Wi-Fi:** run `php artisan serve --host=0.0.0.0 --port=8000`, find the PC's IPv4 address with `ipconfig`, and open `http://<that-address>:8000` on the phone.

Tip: everything above is also under **Terminal → Run Task…** in VS Code ("SmartSave: …").

## 7. While developing

| Task | Command |
|---|---|
| Live-reload CSS/JS while editing views | `npm run dev` (in a second terminal) |
| Run the tests | `php artisan test` |
| Format PHP code | `php vendor/bin/pint` |
| Reset the database (deletes all data!) | `php artisan migrate:fresh` |

## Troubleshooting

| Problem | Fix |
|---|---|
| `could not find driver` | `pdo_sqlite` (or `pdo_mysql`) isn't enabled. See step 2. |
| `Call to undefined function bcadd()` | `bcmath` isn't enabled. See step 2. |
| Page has no styling / `Vite manifest not found` | Run `npm install` then `npm run build`. |
| `No application encryption key` | Run `php artisan key:generate`. |
| Old page after code changes | `php artisan view:clear` |
| Port 8000 already in use | `php artisan serve --port=8001` |

Full documentation (features, security, deployment, backups) is in **README.md**.
