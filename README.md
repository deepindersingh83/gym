# GymFlow — multi-gym management SaaS

A Laravel 13 + Filament 5 app for running one gym or many: members, membership plans, renewals, payments and GST invoices, reminders, attendance, trainers, and owner reports. Each gym is a separate tenant, and its data is never visible to another gym.

## What's included

| Area | Features |
| --- | --- |
| **Platform** | Super-admin panel (`/admin`): all gyms, software plans (Basic / Pro / Enterprise) with member, branch and staff limits, trial extension, recording a gym's payment. Gym owners sign up and go through an onboarding wizard (details, GST, opening hours, logo and colour, starter plans). 14-day free trial; changes are locked when the trial or plan ends. |
| **Roles** | Gym Owner, Branch Manager, Trainer and Receptionist in the gym panel (`/app`), plus Member in the member app (`/member`). Branch staff only see their branch. Trainers only see their own members. The role matrix is in `app/Support/Access.php`. |
| **Multi-branch** | Branches per gym. Owners switch between "All branches" and a single branch from the top bar. |
| **Branding** | Each gym has its own logo and colour in the panel, member app, public page and PDFs. Optional subdomain per gym (`fitzone.yourapp.com`). |
| **Audit log** | Who created, changed or deleted payments, memberships, members, expenses, cash closings, plans, coupons and staff. |
| **Members** | Photo, personal details, height, weight, goal, emergency contact, PAR-Q health questionnaire, assigned trainer. Public signup page per gym: `/join/{gym-slug}`. |
| **Plans** | Monthly, quarterly, half-yearly, yearly, day pass, personal training (with session counts), couple and family, custom. Admission fee charged on the first membership only. |
| **Discounts** | Coupons and offers (festival, referral, corporate) with percentage or fixed value, validity dates and usage limits. |
| **Billing** | GST invoices (CGST/SGST, SAC 999723) and receipts as PDFs. Part payments with dues tracking and an outstanding-dues report (Excel and PDF). Payment modes: cash, GPay, UPI, card, bank transfer, online. Razorpay payment links and **UPI AutoPay** for automatic renewals. Day-end cash closing. Expenses and profit. |
| **Reminders** | Daily job sends renewal reminders (e.g. 7, 3 and 1 days before), expiry notices, pending-dues reminders, and birthday and anniversary wishes, over WhatsApp, SMS, email and member-app notifications. Each message is sent once. Gyms can edit message templates and send broadcasts. |
| **Fitness** | Body measurement history (weight, BMI, body fat, chest, waist, hips, arms, thighs) with progress charts. Workout and diet plans assigned by trainers. Fitness goals. |
| **Staff** | Staff profiles with role, branch and shift. Staff attendance. Trainer ratings and feedback from members. |
| **Member app** | Membership and renewal date, QR code for check-in, renewing and paying dues online, payment history with invoices, progress charts, workout and diet plans, rating their trainer. |
| **Check-in** | Front-desk page: a USB or Bluetooth QR scanner (or typing a member ID or phone number) checks the member in and shows whether their membership is valid and whether they owe dues. |
| **Dashboard** | Revenue today, this month and this year; active vs expired members; renewals due this week; dues outstanding; profit and loss for any period; collections by payment mode; 12-month chart. Excel export for members and payments. |

## Local setup

```bash
composer run setup                      # install, .env, key, migrate + software plans, storage link
php artisan db:seed --class=DemoSeeder  # optional demo gym
php artisan serve
```

Demo logins all use the password `password`:

| Email | Where | Role |
| --- | --- | --- |
| `admin@example.com` | `/admin` | Super admin |
| `owner@example.com` | `/app` | Gym owner (FitZone Gym, 2 branches) |
| `reception@example.com` | `/app` | Receptionist (main branch) |
| `trainer@example.com` | `/app` | Trainer |
| `member@example.com` | `/member` | Member |

Run the tests with `php artisan test`. Check code style with `vendor/bin/pint --test`.

No Node or npm build is needed: Filament's assets are published by `composer install`, and the public pages use inline CSS.

## Deploying on CloudPanel

1. **Create the site.** In CloudPanel, go to **Sites → Add Site → Create a PHP Site**, choose the **Laravel** application template and **PHP 8.3 or newer**, and enter your domain. CloudPanel sets the web root to `public/`.
2. **Create a database.** In the site's **Databases** tab, create a MySQL database and user.
3. **Deploy the code** over SSH as the site user:
   ```bash
   cd ~/htdocs/yourapp.com
   git clone https://github.com/deepindersingh83/gym.git .
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   ```
4. **Edit `.env`:**
   ```dotenv
   APP_NAME="GymFlow"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourapp.com
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=...
   DB_USERNAME=...
   DB_PASSWORD=...
   QUEUE_CONNECTION=database
   MAIL_MAILER=smtp        # plus your SMTP details, used for email reminders and password resets
   ```
5. **Prepare the app:**
   ```bash
   php artisan migrate --seed --force   # also creates the super admin admin@syberinfo.com.au (SUPER_ADMIN_* in .env)
   php artisan storage:link
   php artisan gym:make-admin someone@yourcompany.com   # optional: add another super admin
   php artisan optimize
   ```
6. **Cron.** In the site's **Cron Jobs** tab, add a job that runs every minute:
   ```
   * * * * * /usr/bin/php8.3 /home/<site-user>/htdocs/yourapp.com/artisan schedule:run >> /dev/null 2>&1
   ```
   This sends reminders daily at 09:00, in the timezone set by `APP_TIMEZONE`.
7. **Queue worker.** Password-reset and invite emails are queued. As root, install Supervisor (`apt install supervisor`) and add `/etc/supervisor/conf.d/gym-queue.conf`:
   ```ini
   [program:gym-queue]
   command=/usr/bin/php8.3 /home/<site-user>/htdocs/yourapp.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   user=<site-user>
   autostart=true
   autorestart=true
   stdout_logfile=/home/<site-user>/logs/queue.log
   ```
   Then run `supervisorctl reread && supervisorctl update`.
8. **SSL.** Use CloudPanel's **SSL/TLS → Let's Encrypt** for the main domain.
9. **Subdomain per gym (optional).**
   - Add a wildcard DNS record `*.yourapp.com` (Cloudflare recommended).
   - In CloudPanel's **Vhost** editor, change `server_name` to `yourapp.com *.yourapp.com`.
   - Let's Encrypt in CloudPanel cannot issue wildcard certificates. Create a free Cloudflare Origin Certificate for `*.yourapp.com` and `yourapp.com` and import it under **SSL/TLS → Import Certificate**.
   - Set `GYM_TENANT_DOMAIN=yourapp.com`, then run `php artisan optimize`.
10. **Backups.** Set up CloudPanel's remote backups (S3, Wasabi or similar) and include the database.

**Updating later:**

```bash
git pull
composer install --no-dev -o
php artisan migrate --force
php artisan optimize
supervisorctl restart gym-queue
```

## Integrations (per gym, under Gym settings → Integrations)

- **Razorpay:** each gym adds its own keys, so money goes to that gym's account. Set the webhook URL shown on the settings page with the events `payment_link.paid` and `subscription.charged`, and paste in its webhook secret. Payment links settle dues or renew memberships automatically. UPI AutoPay charges each renewal and creates the next membership.
- **WhatsApp:** uses the Meta WhatsApp Cloud API. Business-initiated messages must use an **approved template**. Put the template's name in the message template; the `{placeholders}` are sent as the template's variables, in the order they appear.
- **SMS:** uses the MSG91 flow API. Indian SMS needs a DLT-approved template, configured the same way.
- **Email:** uses Laravel's mail settings.

A channel without credentials doesn't fail: the message is written to the log and shown as "logged" under **Sent messages**.

## Not built yet

- Automated billing of gyms for the software plans. Today the super admin records a gym's payment.
- Native mobile apps. The member app is a mobile-friendly web app.
- Biometric or RFID door hardware.
- Lead/CRM, group class booking, and point-of-sale/inventory, which were not in the chosen scope.

## Code map

- `app/Models` — models. `Concerns/BelongsToGym` keeps each gym's data separate (and branch-scoped where relevant); `Concerns/Auditable` writes the audit log.
- `app/Support/Tenancy.php` — current gym and branch. `app/Support/Access.php` — role permissions.
- `app/Services` — membership pricing and renewals, Razorpay, messaging channels.
- `app/Filament/Admin`, `app/Filament/Gym`, `app/Filament/Member` — the three panels.
- `app/Console/Commands/SendReminders.php` — the daily reminder job.
