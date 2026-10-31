#!/usr/bin/env bash
#
# GymFlow auto installer — first install and later updates.
#
#   bash install.sh            interactive: asks for anything missing in .env
#   bash install.sh --yes      non-interactive: uses .env as it is
#
# Options:
#   --yes          Do not ask questions (CI / re-runs after git pull)
#   --dev          Keep APP_ENV=local and APP_DEBUG=true, install dev packages
#   --allow-root   Run even when started as root (not recommended on CloudPanel)
#   --php=PATH     PHP binary to use, e.g. --php=/usr/bin/php8.3
#
# Run it as the CloudPanel *site user* from the project folder:
#   su - <site-user>
#   cd ~/htdocs/yourapp.com && bash install.sh

set -Eeuo pipefail

cd "$(dirname "$(readlink -f "$0")")"
APP_DIR="$(pwd)"

ASSUME_YES=0
DEV=0
ALLOW_ROOT=0
PHP_BIN="${PHP_BIN:-php}"
MIN_PHP="8.3"
REQUIRED_EXTENSIONS=(pdo_mysql mbstring intl zip gd fileinfo openssl tokenizer xml ctype curl)

for arg in "$@"; do
    case "$arg" in
        --yes|-y) ASSUME_YES=1 ;;
        --dev) DEV=1 ;;
        --allow-root) ALLOW_ROOT=1 ;;
        --php=*) PHP_BIN="${arg#--php=}" ;;
        -h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (see --help)"; exit 1 ;;
    esac
done

# Interactive only when a terminal is attached and --yes was not given.
INTERACTIVE=0
if [[ $ASSUME_YES -eq 0 && -t 0 ]]; then INTERACTIVE=1; fi

# ---------------------------------------------------------------- output
if [[ -t 1 ]]; then
    B=$'\e[1m'; G=$'\e[32m'; Y=$'\e[33m'; R=$'\e[31m'; C=$'\e[36m'; N=$'\e[0m'
else
    B=''; G=''; Y=''; R=''; C=''; N=''
fi
step() { echo; echo "${B}${C}==>${N} ${B}$*${N}"; }
ok()   { echo "    ${G}✓${N} $*"; }
warn() { echo "    ${Y}!${N} $*"; }
fail() { echo; echo "${R}${B}✗ $*${N}" >&2; exit 1; }
trap 'fail "Installer stopped at line $LINENO. Fix the problem above and run: bash install.sh"' ERR

# ---------------------------------------------------------------- .env helpers
env_get() {
    # Value of KEY from .env, without surrounding quotes; empty if missing or commented out.
    local line
    line="$(grep -E "^${1}=" .env 2>/dev/null | tail -n1 || true)"
    line="${line#*=}"
    if [[ "$line" == \"*\" ]]; then
        line="${line:1:${#line}-2}"
        line="$(printf '%s' "$line" | sed -e 's/\\\(.\)/\1/g')"
    fi
    printf '%s' "$line"
}

env_or() {
    # env_or KEY DEFAULT [IGNORE]: the .env value, or DEFAULT when empty (or equal to IGNORE).
    local value
    value="$(env_get "$1")"
    if [[ -z "$value" || ( -n "${3:-}" && "$value" == "$3" ) ]]; then value="$2"; fi
    printf '%s' "$value"
}

env_set() {
    # Set KEY=VALUE in .env, replacing an existing or commented-out line.
    # Values with spaces or special characters are double-quoted and escaped.
    local key="$1" value="$2" line
    if [[ "$value" =~ ^[A-Za-z0-9_./:@+-]*$ ]]; then
        line="${key}=${value}"
    else
        line="${key}=\"$(printf '%s' "$value" | sed -e 's/[\\"$`]/\\&/g')\""
    fi
    if grep -qE "^#?[[:space:]]*${key}=" .env; then
        local tmp
        tmp="$(mktemp "${APP_DIR}/.env.XXXXXX")"
        # Pass the line through the environment: "awk -v" would mangle backslashes.
        ENV_KEY="$key" ENV_LINE="$line" awk '
            !done && $0 ~ "^#?[[:space:]]*" ENVIRON["ENV_KEY"] "=" { print ENVIRON["ENV_LINE"]; done=1; next }
            { print }
        ' .env > "$tmp"
        cat "$tmp" > .env && rm -f "$tmp"
    else
        printf '%s\n' "$line" >> .env
    fi
}

ask() {
    # ask VAR "Question" "default" [secret]
    local __var="$1" question="$2" default="${3:-}" secret="${4:-}" answer=""
    if [[ $INTERACTIVE -eq 0 ]]; then printf -v "$__var" '%s' "$default"; return; fi
    if [[ -n "$secret" ]]; then
        read -r -s -p "    ${question}${default:+ [hidden, Enter keeps current]}: " answer; echo
    else
        read -r -p "    ${question}${default:+ [$default]}: " answer
    fi
    printf -v "$__var" '%s' "${answer:-$default}"
}

# ---------------------------------------------------------------- 1. checks
echo "${B}GymFlow installer${N}  ·  ${APP_DIR}"

step "Checking the server"

if [[ $EUID -eq 0 && $ALLOW_ROOT -eq 0 ]]; then
    owner="$(stat -c '%U' "$APP_DIR")"
    fail "Do not run this as root — files would become owned by root and the site would break.
   Run it as the site user instead:
       su - ${owner} -c 'cd ${APP_DIR} && bash install.sh'
   (or add --allow-root if you really mean it)"
fi

command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP not found ('$PHP_BIN'). Use --php=/usr/bin/php8.3"
PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [[ "$("$PHP_BIN" -r "echo version_compare(PHP_VERSION, '${MIN_PHP}', '>=') ? 'yes' : 'no';")" != "yes" ]]; then
    fail "PHP ${MIN_PHP}+ is required, but '$PHP_BIN' is ${PHP_VERSION}.
   On CloudPanel run:  bash install.sh --php=/usr/bin/php8.3   (and pick PHP 8.3+ for the site)"
fi
ok "PHP ${PHP_VERSION} ($(command -v "$PHP_BIN"))"

missing=()
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    "$PHP_BIN" -r "exit(extension_loaded('${ext}') ? 0 : 1);" || missing+=("$ext")
done
if [[ ${#missing[@]} -gt 0 ]]; then
    fail "Missing PHP extensions: ${missing[*]}
   Ask your host / install them for PHP ${PHP_VERSION}, e.g.  apt install php${PHP_VERSION}-{intl,zip,gd,mysql,mbstring,xml,curl}"
fi
ok "PHP extensions"

if command -v composer >/dev/null 2>&1; then
    COMPOSER=("$PHP_BIN" "$(command -v composer)")
elif [[ -f composer.phar ]]; then
    COMPOSER=("$PHP_BIN" composer.phar)
else
    fail "Composer not found. Install it: https://getcomposer.org/download/ (CloudPanel includes it as 'composer')"
fi
# Root was explicitly allowed above, so stop Composer from asking about it.
[[ $EUID -ne 0 ]] || export COMPOSER_ALLOW_SUPERUSER=1
ok "Composer $("${COMPOSER[@]}" --version --no-ansi --no-interaction 2>/dev/null </dev/null | awk '{print $3}')"

[[ -f artisan && -f composer.json ]] || fail "Run this from the project folder (artisan not found in ${APP_DIR})"

# Every file must belong to the user running the installer. Files left behind by an
# earlier run as root can't be changed by the site user (git pull, composer and the
# caches would all fail), so stop here with the one command that fixes it.
ME="$(id -un)"
# Cached views and config are rebuilt anyway: remove stale ones we may not own.
for cache_dir in storage/framework/views storage/framework/cache/data bootstrap/cache; do
    [[ -d "$cache_dir" ]] && find "$cache_dir" -type f ! -user "$ME" -delete 2>/dev/null || true
done
mapfile -t not_mine < <(find . ! -user "$ME" -print 2>/dev/null | head -n 5)
if [[ ${#not_mine[@]} -gt 0 ]]; then
    printf '    %s\n' "${not_mine[@]}" | sed "s|^    \./|    ${APP_DIR}/|"
    fail "Some files are owned by another user (usually root), so ${ME} cannot update them.
   Log in as root once and run:

       chown -R ${ME}:${ME} ${APP_DIR}

   (No root password? Use your VPS provider's browser terminal / root login.)
   Then, as ${ME}:  cd ${APP_DIR} && git pull && bash install.sh --yes"
fi
ok "All files belong to ${ME}"

# ---------------------------------------------------------------- 2. .env
step "Setting up .env"

if [[ ! -f .env ]]; then
    cp .env.example .env
    chmod 640 .env
    ok "Created .env from .env.example"
else
    ok ".env found (existing values are kept)"
fi

# Website address
current_url="$(env_get APP_URL)"
if [[ -z "$current_url" || "$current_url" == "http://localhost"* ]] && [[ $INTERACTIVE -eq 1 && $DEV -eq 0 ]]; then
    ask APP_URL_VALUE "Website address (e.g. https://gym.example.com)" "${current_url:-https://}"
    env_set APP_URL "${APP_URL_VALUE%/}"
fi

# Environment
if [[ $DEV -eq 1 ]]; then
    env_set APP_ENV local; env_set APP_DEBUG true
else
    env_set APP_ENV production; env_set APP_DEBUG false
fi
ok "APP_ENV=$(env_get APP_ENV), APP_DEBUG=$(env_get APP_DEBUG)"

# Database
db_conn="$(env_get DB_CONNECTION)"
if [[ $INTERACTIVE -eq 1 && ( "$db_conn" == "sqlite" || -z "$(env_get DB_DATABASE)" ) && $DEV -eq 0 ]]; then
    echo "    Enter the MySQL database you created in CloudPanel → Databases:"
    ask DB_HOST_V "Database host" "$(env_or DB_HOST 127.0.0.1)"
    ask DB_PORT_V "Database port" "$(env_or DB_PORT 3306)"
    ask DB_NAME_V "Database name" "$(env_or DB_DATABASE '' laravel)"
    ask DB_USER_V "Database user" "$(env_or DB_USERNAME '' root)"
    [[ -n "$DB_NAME_V" && -n "$DB_USER_V" ]] || fail "Database name and user are required."
    ask DB_PASS_V "Database password" "$(env_get DB_PASSWORD)" secret
    env_set DB_CONNECTION mysql
    env_set DB_HOST "$DB_HOST_V"
    env_set DB_PORT "$DB_PORT_V"
    env_set DB_DATABASE "$DB_NAME_V"
    env_set DB_USERNAME "$DB_USER_V"
    env_set DB_PASSWORD "$DB_PASS_V"
fi
ok "Database: $(env_get DB_CONNECTION) · $(env_get DB_DATABASE)"

# Super admin
[[ -n "$(env_get SUPER_ADMIN_EMAIL)" ]] || env_set SUPER_ADMIN_EMAIL "admin@syberinfo.com.au"
ADMIN_PASSWORD=""
if [[ $INTERACTIVE -eq 1 && -z "$(env_get SUPER_ADMIN_PASSWORD)" ]]; then
    ask ADMIN_PASSWORD "Password for $(env_get SUPER_ADMIN_EMAIL) (Enter = generate one)" "" secret
    if [[ -n "$ADMIN_PASSWORD" && ${#ADMIN_PASSWORD} -lt 8 ]]; then fail "The admin password must be at least 8 characters."; fi
fi
ok "Super admin: $(env_get SUPER_ADMIN_EMAIL)"

# ---------------------------------------------------------------- 3. folders
step "Preparing folders"
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
ok "storage/ and bootstrap/cache/ are writable"

# ---------------------------------------------------------------- 4. packages
step "Installing PHP packages (this can take a few minutes)"
composer_flags=(--no-interaction --prefer-dist --optimize-autoloader)
[[ $DEV -eq 1 ]] || composer_flags+=(--no-dev)
"${COMPOSER[@]}" install "${composer_flags[@]}"
ok "Packages installed"

# ---------------------------------------------------------------- 5. app key
step "Application key"
if [[ -z "$(env_get APP_KEY)" ]]; then
    "$PHP_BIN" artisan key:generate --force --no-interaction
    ok "Generated APP_KEY"
else
    ok "APP_KEY already set (kept, so existing sessions and encrypted data stay valid)"
fi

# ---------------------------------------------------------------- 6. database & app setup
step "Database and first-time setup"
# The admin password is passed only through the environment of this one process,
# so it is never written to .env or shell history.
SUPER_ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(env_get SUPER_ADMIN_PASSWORD)}" "$PHP_BIN" artisan gym:install --no-interaction

# ---------------------------------------------------------------- 7. next steps
SITE_USER="$(id -un)"
PHP_PATH="$(command -v "$PHP_BIN")"
step "Last steps (do these once in CloudPanel)"
cat <<EOF
    1. ${B}Cron job${N} — CloudPanel → your site → Cron Jobs → Add, every minute:
         * * * * * ${PHP_PATH} ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1

    2. ${B}Queue worker${N} (emails for invites and password resets) — as root:
         apt install -y supervisor
         nano /etc/supervisor/conf.d/gymflow-queue.conf      and paste:

[program:gymflow-queue]
command=${PHP_PATH} ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=${SITE_USER}
autostart=true
autorestart=true
stdout_logfile=${APP_DIR}/storage/logs/queue.log

         then run:  supervisorctl reread && supervisorctl update

    3. ${B}SSL${N} — CloudPanel → SSL/TLS → Let's Encrypt (or a Cloudflare Origin Certificate).

    4. ${B}Email${N} — set MAIL_* in .env, then run: bash install.sh --yes

${G}${B}All done!${N} Open $(env_get APP_URL)/admin and sign in as $(env_get SUPER_ADMIN_EMAIL).
To update later:  git pull && bash install.sh --yes
EOF
