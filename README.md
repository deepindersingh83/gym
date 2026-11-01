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
3. **Run the auto installer** over SSH as the site user (not root):
   ```bash
   su - <site-user>
   cd ~/htdocs/yourapp.com
   git clone -b claude/lucid-gauss-uhwvaf https://github.com/deepindersingh83/gym.git .
   bash install.sh --php=/usr/bin/php8.3
   ```
   The installer:
   - checks PHP, its extensions and Composer;
   - creates `.env` and asks for your website address and the database from step 2;
   - checks the database connection, and explains how to fix it if it fails;
   - installs packages, generates the app key, creates the tables and software plans;
   - creates the super admin **admin@syberinfo.com.au**. You type a password, or press Enter to have one generated and shown once;
   - links storage, publishes assets, builds caches, and prints the exact cron and queue-worker lines for your server.

   It is safe to run again: existing data, prices and passwords are never overwritten. Use `bash install.sh --yes` to skip the questions. To add another super admin: `php artisan gym:make-admin someone@yourcompany.com`.
4. **Email.** Set `MAIL_MAILER=smtp` and your SMTP details in `.env` (used for email reminders, invites and password resets), then run `bash install.sh --yes`.
5. **Run `php artisan gym:install` by hand** only if you set up packages yourself; it does the database and app steps of the installer.
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
bash install.sh --yes --php=/usr/bin/php8.3
supervisorctl restart gym-queue   # as root
```

### Troubleshooting

- **Login or signup just reloads the page.** The browser could not load Livewire's script. CloudPanel's Nginx serves every `.js` address itself, and Livewire's default script address only exists as a Laravel route, so it returns 404. `composer install` and `install.sh` now publish the script to `public/vendor/livewire`, which fixes it. On an existing server, run `bash install.sh --yes` (or `php artisan vendor:publish --tag=livewire:assets --force && php artisan optimize`). To confirm, open the browser's developer tools → Network and reload: nothing should show 404.
- **HTTP 526 from Cloudflare.** The server's SSL certificate is missing or invalid. Install a Cloudflare Origin Certificate in CloudPanel and set Cloudflare SSL to Full (strict).
- **"Operation not permitted" or `git pull`: "cannot open .git/FETCH_HEAD: Permission denied".** Some files are owned by root because a command was once run as root. As root (your VPS provider's browser terminal or root SSH login): `chown -R <site-user>:<site-user> /home/<site-user>/htdocs/yourapp.com`. Then run `git pull && bash install.sh --yes` as the site user. The installer now checks this first and prints the exact command.
- **"tempnam(): file created in the system's temporary directory".** `bootstrap/cache` is not writable, usually because a command was run as root. As root: `chown -R <site-user>:<site-user> ~<site-user>/htdocs/yourapp.com`.

### Gym custom domains (members.theirgym.com)

Owners enter their domain under **Gym settings → Website → Your own domain** and add a **CNAME** record pointing to `CUSTOM_DOMAIN_TARGET`. Their website, `/join` and the member app then run on that domain. The app re-checks pending domains every 15 minutes.

**Automatic SSL (recommended): Cloudflare for SaaS**
1. Your platform domain must be on Cloudflare. In the zone, go to **SSL/TLS → Custom Hostnames**, enable it, and set a fallback origin such as `customers.yourapp.com` (a proxied A record to your server).
2. Create an API token with **Zone → SSL and Certificates → Edit** for that zone.
3. In `.env`: `CUSTOM_DOMAIN_TARGET=customers.yourapp.com`, `CLOUDFLARE_ZONE_ID=...`, `CLOUDFLARE_API_TOKEN=...`, then run `bash install.sh --yes`.
4. In CloudPanel's **Vhost** editor, let the site answer any host. Add `_` to `server_name`, for example `server_name yourapp.com *.yourapp.com _;`, so gym domains reach the app.

**Without Cloudflare:** leave the Cloudflare values empty. A domain goes live when its DNS points to your server. Then add it to the site in CloudPanel (**Domains**) and issue a Let's Encrypt certificate for it.

### Facebook & Instagram lead ads

- **Quick option, no Meta app:** under **Gym settings → Integrations → Lead webhook**, create an address and use it in Zapier, Make or Pabbly ("Facebook Lead Ads → Webhook POST").
- **Direct option:** create a Meta app with the **Webhooks** product. Subscribe the **Page → leadgen** field with callback URL `https://yourapp.com/webhooks/meta` and the verify token from `META_VERIFY_TOKEN`. Put the app secret in `META_APP_SECRET`. The app needs the `leads_retrieval` and `pages_manage_metadata` permissions, which require Meta app review. Each gym then enters its page ID and page token in Integrations and clicks **Connect page**.

### Member app sign-in

- Members sign in at `/member` with their **mobile number (or email) and password**. This needs no SMS or WhatsApp. Staff can set the password when adding a member (**Member app login**) or later with **Set app password**.
- When the platform has WhatsApp or SMS set up (`WHATSAPP_*` / `MSG91_*`), every gym also gets **Sign in with a one-time code**. Codes are free for gyms; they don't need credits or their own subscription. A gym with its own WhatsApp/SMS account also gets codes on its own domain. Without any provider, the code option is hidden and the login page tells members to ask the front desk. Members tap **Sign in with a one-time code** to get a 6-digit code by WhatsApp (SMS if WhatsApp fails). Codes expire after 10 minutes, lock after 5 wrong tries, are rate limited, don't use message credits, and are hidden in Sent messages.
- Staff give a member access from the member's page: **Invite to member app** (email link, or WhatsApp/SMS if they have no email) or **Set app password** at the desk. Online signup doesn't need an email.
- WhatsApp only allows business-started messages from approved templates. Create an **Authentication** template in Meta, then enter its name for the "Member app login code" event under **Messaging → Message templates**. Do the same with a DLT template for SMS.

### Message credits (SMS / WhatsApp)

- Each WhatsApp or SMS sent through **your** platform account (`WHATSAPP_*`, `MSG91_*` in `.env`) uses the gym's prepaid credits. That's 1 credit per message by default (`CREDITS_PER_WHATSAPP`, `CREDITS_PER_SMS`).
- Free: gyms that connect their own WhatsApp or MSG91 account, email, and member-app notifications.
- New gyms get `CREDITS_WELCOME` free credits. Owners get one alert a day when the balance drops below `CREDITS_LOW_BALANCE`. With no credits left, messages are paused and listed as "Out of message credits" under Sent messages, and they go out on the next run after a top-up.
- **Buying:** gyms buy packs under **Messaging → Message credits**. Packs and prices are in `config/gym.php` (`credits.packs`). Payment goes to your Razorpay account (`PLATFORM_RAZORPAY_*`). Add the webhook `https://yourapp.com/webhooks/razorpay-platform` with the event `payment_link.paid`, and credits are added when it's paid. Without those keys, the page tells owners to contact you; add credits by hand in **Admin → Gyms → ⋮ → Adjust message credits**.

### Partners, usage and software payments

- **Admin → Partners:** add resellers or affiliates with a commission %. They sign in at `/partner` to see their referral link (`?ref=CODE`), referred gyms and commissions.
- **Admin → Gyms → ⋮ → Record payment:** records what a gym paid you. Referred gyms automatically create the partner's commission. Pay commissions out under **Admin → Commissions**.
- **Admin → Gym usage:** each gym marked Active, At risk or Inactive, with the reasons, last staff login and check-in trend.

### India and Australia

Each gym has a **country** (Gym settings → Details).

| | India | Australia |
| --- | --- | --- |
| Currency | ₹ (INR) | $ (AUD) |
| GST | Added to prices, split into CGST + SGST; needs a GSTIN | Included in prices (10%); needs an ABN and "Registered for GST" |
| Tax invoice | GSTIN, SAC 999723, place of supply | ABN, "Total includes GST of $x" |
| Mobile numbers | 98765 43210 | 0412 345 678 |
| Payment methods | Cash, GPay, UPI, card, bank transfer, online (Razorpay) | Cash, card, bank transfer / PayID |
| Time zone | IST | Sydney, Melbourne, Brisbane, Adelaide, Perth, Hobart or Darwin |

The demo seeder includes an Australian gym: `owner.au@example.com` / `password`.

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
