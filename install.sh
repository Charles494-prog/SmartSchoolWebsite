#!/usr/bin/env bash
# SmartSchool one-command installer for Linux (Ubuntu/Debian/Linux Mint).
#
#   curl -fsSL https://raw.githubusercontent.com/Charles494-prog/SmartSchoolWebsite/main/install.sh | bash
#
# What it does:
#   1. Installs what SmartSchool needs (PHP, Composer, Node.js) – asks for your password once.
#   2. Downloads SmartSchool to ~/smartschool (or updates it if it's already there).
#   3. Asks how your school will use it: Offline on school Wi-Fi (LAN) or Online (web host).
#   4. Prepares the database and starts SmartSchool, then shows the address to open.
#
# Safe to run again: it updates the code and keeps your data.
set -euo pipefail

REPO="https://github.com/Charles494-prog/SmartSchoolWebsite.git"
DIR="${SMARTSCHOOL_DIR:-$HOME/smartschool}"
PORT="${SMARTSCHOOL_PORT:-8000}"

bold() { printf '\033[1m%s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✔\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
fail() { printf '\n\033[31mSetup stopped:\033[0m %s\n' "$*" >&2; exit 1; }
ask()  { local a; read -r -p "$1" a </dev/tty || true; echo "${a:-$2}"; }

bold "SmartSchool installer"
echo

# ---------------------------------------------------------------- 1. requirements
need_pkg=()
php_ok=false
if command -v php >/dev/null && php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then php_ok=true; fi
$php_ok || need_pkg+=(php-cli)
for ext in sqlite3 mbstring xml gd zip intl curl; do
  php -m 2>/dev/null | grep -qi "^${ext}$" || need_pkg+=("php-${ext}")
done
php -m 2>/dev/null | grep -qi "^pdo_mysql$" || need_pkg+=(php-mysql)
command -v composer >/dev/null || need_pkg+=(composer)
command -v node >/dev/null || need_pkg+=(nodejs npm)
command -v git >/dev/null || need_pkg+=(git)
command -v unzip >/dev/null || need_pkg+=(unzip)

PREBUILT=0
if [ ${#need_pkg[@]} -gt 0 ]; then
  if command -v apt-get >/dev/null && command -v sudo >/dev/null && sudo -v 2>/dev/null; then
    bold "1/4  Installing what SmartSchool needs: ${need_pkg[*]}"
    sudo apt-get update -qq
    sudo apt-get install -y -qq "${need_pkg[@]}" >/dev/null
  elif $php_ok; then
    # Shared hosting (e.g. Hostinger): no sudo, but PHP is there – use the ready-made package.
    PREBUILT=1
    warn "Can't install system packages here (shared hosting?) – using the ready-made SmartSchool package instead."
  else
    fail "PHP 8.3 or newer is needed. On shared hosting, choose PHP 8.3+ in your hosting panel (Hostinger: hPanel > Advanced > PHP Configuration), then run this again."
  fi
fi
php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || fail "PHP 8.3 or newer is needed (found $(php -r 'echo PHP_VERSION;')). On older Ubuntu, add the ondrej/php PPA first."
ok "PHP $(php -r 'echo PHP_VERSION;') ready"

# ---------------------------------------------------------------- 2. download / update
bold "2/4  Downloading SmartSchool to $DIR"
if [ "$PREBUILT" = 1 ]; then
  mkdir -p "$DIR"
  curl -fsSL -o /tmp/smartschool-app.zip "https://github.com/Charles494-prog/SmartSchoolWebsite/releases/latest/download/smartschool-app.zip" || fail "could not download SmartSchool."
  unzip -oq /tmp/smartschool-app.zip -d "$DIR" && rm -f /tmp/smartschool-app.zip
  cd "$DIR"
  ok "Downloaded the ready-made package"
else
  if [ -d "$DIR/.git" ]; then
    git -C "$DIR" pull --ff-only -q && ok "Updated to the latest version"
  else
    git clone -q --depth 1 "$REPO" "$DIR" && ok "Downloaded"
  fi
  cd "$DIR"
  composer install --no-dev --optimize-autoloader --no-interaction -q
  npm ci --silent >/dev/null 2>&1 && npm run build --silent >/dev/null 2>&1
fi
ok "Program files ready"

# ---------------------------------------------------------------- 3. mode
bold "3/4  How will your school use SmartSchool?"
if [ -f .env ] && grep -q '^SMARTSCHOOL_MODE=' .env; then
  MODE=$(grep '^SMARTSCHOOL_MODE=' .env | cut -d= -f2)
  ok "Keeping your earlier choice: $MODE"
else
  echo "   1) Offline on school Wi-Fi (LAN) – recommended for most schools. No internet needed."
  echo "   2) Online (web host) – for parent and student accounts. Needs a web host and a MySQL database."
  choice=$(ask "   Type 1 or 2 and press Enter [1]: " 1)
  [ "$choice" = "2" ] && MODE=hosted || MODE=lan
fi

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --force -q
fi
set_env() { if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi; }
set_env SMARTSCHOOL_MODE "$MODE"
set_env APP_ENV production
set_env APP_DEBUG false

if [ "$MODE" = lan ]; then
  mkdir -p database
  touch database/database.sqlite
  set_env DB_CONNECTION sqlite
  set_env DB_DATABASE "$DIR/database/database.sqlite"
  LAN_IP=$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)' | head -1)
  LAN_IP=${LAN_IP:-127.0.0.1}
  set_env APP_URL "http://$LAN_IP:$PORT"
else
  echo
  echo "   Where is this running?"
  echo "   1) A fresh server I control (VPS / cloud server) – set up everything automatically"
  echo "   2) Shared hosting – I already have a MySQL database from my hosting company"
  where=$(ask "   Type 1 or 2 and press Enter [1]: " 1)
  if [ "$where" != "2" ]; then
    AUTO=1
    DOMAIN=$(ask "     Your website domain (e.g. school.example.edu.ph): " "")
    [ -n "$DOMAIN" ] || fail "a domain is needed. Point the domain's A record to this server's IP first, then run the installer again."
    CERT_EMAIL=$(ask "     Email for the free HTTPS certificate (Let's Encrypt): " "")
    bold "     Installing the web server and database (Nginx, MariaDB, PHP-FPM)…"
    sudo apt-get install -y -qq nginx mariadb-server php-fpm php-mysql certbot python3-certbot-nginx >/dev/null
    DB_PASS=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)
    sudo mysql -e "CREATE DATABASE IF NOT EXISTS smartschool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE USER IF NOT EXISTS 'smartschool'@'localhost' IDENTIFIED BY '$DB_PASS';
      ALTER USER 'smartschool'@'localhost' IDENTIFIED BY '$DB_PASS';
      GRANT ALL PRIVILEGES ON smartschool.* TO 'smartschool'@'localhost'; FLUSH PRIVILEGES;"
    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE smartschool
    set_env DB_USERNAME smartschool
    set_env DB_PASSWORD "$DB_PASS"
    set_env APP_URL "https://$DOMAIN"
    ok "Database 'smartschool' created (password saved only in $DIR/.env)"
  else
    echo "   Enter your MySQL database details (your hosting company gives you these):"
    set_env DB_CONNECTION mysql
    set_env DB_HOST "$(ask '     Host [127.0.0.1]: ' 127.0.0.1)"
    set_env DB_PORT "$(ask '     Port [3306]: ' 3306)"
    set_env DB_DATABASE "$(ask '     Database name: ' smartschool)"
    set_env DB_USERNAME "$(ask '     User: ' smartschool)"
    read -r -s -p "     Password: " dbpass </dev/tty; echo
    set_env DB_PASSWORD "$dbpass"
    set_env APP_URL "$(ask '     Website address (https://…): ' https://localhost)"
  fi
fi
ok "Mode: $([ "$MODE" = lan ] && echo 'Offline on school Wi-Fi (LAN)' || echo 'Online (web host)')"

# ---------------------------------------------------------------- 4. database + start
bold "4/4  Preparing the database"
php artisan migrate --force -q || fail "the database could not be prepared. Check the database details in $DIR/.env"
php artisan db:seed --force -q 2>/dev/null || true
php artisan config:cache -q && php artisan route:cache -q && php artisan view:cache -q
ok "Database ready"

if [ "$MODE" = hosted ] && [ "${AUTO:-0}" = 1 ]; then
  bold "     Setting up the website…"
  sock=$(ls /run/php/php*-fpm.sock 2>/dev/null | sort -V | tail -1)
  [ -n "$sock" ] || fail "PHP-FPM is not running. Try: sudo systemctl start php*-fpm"
  chmod o+x "$HOME"
  sudo chgrp -R www-data "$DIR/storage" "$DIR/bootstrap/cache"
  sudo chmod -R g+rwX "$DIR/storage" "$DIR/bootstrap/cache"
  sudo tee /etc/nginx/sites-available/smartschool >/dev/null <<EON
server {
    listen 80;
    server_name $DOMAIN;
    root $DIR/public;
    index index.php;
    client_max_body_size 25M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:$sock; }
    location ~ /\.(?!well-known) { deny all; }
}
EON
  sudo ln -sf /etc/nginx/sites-available/smartschool /etc/nginx/sites-enabled/smartschool
  sudo rm -f /etc/nginx/sites-enabled/default
  sudo nginx -t -q && sudo systemctl reload nginx
  if command -v ufw >/dev/null && sudo ufw status 2>/dev/null | grep -q "Status: active"; then sudo ufw allow 'Nginx Full' >/dev/null; fi
  if [ -n "$CERT_EMAIL" ]; then
    sudo certbot --nginx -d "$DOMAIN" -m "$CERT_EMAIL" --agree-tos --redirect -n -q && ok "HTTPS is on" \
      || warn "HTTPS could not be set up yet – check that $DOMAIN points to this server, then run: sudo certbot --nginx -d $DOMAIN"
  else
    warn "No email given – HTTPS skipped. Later run: sudo certbot --nginx -d $DOMAIN"
  fi
  ( crontab -l 2>/dev/null | grep -v 'smartschool.*schedule:run'; echo "* * * * * cd $DIR && php artisan schedule:run >> /dev/null 2>&1" ) | crontab -
  ok "Background jobs (reminders, backups) turned on"
  echo
  bold "SmartSchool is online!"
  printf '      \033[1;32mhttps://%s\033[0m\n' "$DOMAIN"
  echo "  • Open it and create the Superadmin. Write its email and password down – nobody can recover it."
  exit 0
fi

if [ "$MODE" = hosted ]; then
  echo
  bold "Connecting your website to SmartSchool"
  guess=$(ls -d "$HOME"/domains/*/public_html 2>/dev/null | head -1)
  guess=${guess:-$HOME/public_html}
  WEBROOT=$(ask "     Your website folder [$guess]: " "$guess")
  if [ -L "$WEBROOT" ] && [ "$(readlink -f "$WEBROOT")" = "$(readlink -f "$DIR/public")" ]; then
    ok "Already connected"
  else
    if [ -e "$WEBROOT" ]; then
      mv "$WEBROOT" "$WEBROOT.before-smartschool-$(date +%Y%m%d%H%M%S)"
      warn "Your old website folder was kept as $WEBROOT.before-smartschool-…"
    fi
    ln -s "$DIR/public" "$WEBROOT" && ok "Website folder now shows SmartSchool"
  fi
  ( crontab -l 2>/dev/null | grep -v 'smartschool.*schedule:run'; echo "* * * * * cd $DIR && php artisan schedule:run >> /dev/null 2>&1" ) | crontab - 2>/dev/null \
    && ok "Background jobs (reminders, backups) turned on" \
    || warn "Add this cron job in your hosting panel: cd $DIR && php artisan schedule:run"
  echo
  bold "SmartSchool is online!"
  echo "  • Open $(grep '^APP_URL=' .env | cut -d= -f2) and create the Superadmin. Write its email and password down."
  echo "  • Turn on free SSL (https) for your domain in your hosting panel if it isn't on yet."
  exit 0
fi

# LAN: start the app on the school network
cat > "$DIR/smartschool-start.sh" <<EOS
#!/usr/bin/env bash
cd "$DIR"
LAN_IP=\$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)' | head -1)
LAN_IP=\${LAN_IP:-127.0.0.1}
pkill -f "artisan serve --host=.* --port=$PORT" 2>/dev/null || true
pkill -f "artisan schedule:work" 2>/dev/null || true
PHP_CLI_SERVER_WORKERS=6 nohup php artisan serve --host="\$LAN_IP" --port=$PORT > storage/logs/server.log 2>&1 &
nohup php artisan schedule:work > storage/logs/scheduler.log 2>&1 &
echo "SmartSchool is running at:  http://\$LAN_IP:$PORT"
echo "Open that address in Brave or Chrome on any device connected to the same Wi-Fi."
EOS
chmod +x "$DIR/smartschool-start.sh"
if command -v ufw >/dev/null && sudo ufw status 2>/dev/null | grep -q "Status: active"; then
  sudo ufw allow "$PORT/tcp" >/dev/null && ok "Firewall: port $PORT opened for the school network"
fi
"$DIR/smartschool-start.sh" >/dev/null
sleep 2

echo
bold "SmartSchool is ready!"
echo
echo "  Open this address on any phone or computer connected to the SAME Wi-Fi:"
echo
printf '      \033[1;32mhttp://%s:%s\033[0m\n' "$LAN_IP" "$PORT"
echo
echo "  • Internet is not needed. All devices must be on the same Wi-Fi/network."
echo "  • We recommend the Brave or Chrome browser."
echo "  • First time: the browser asks you to create the Superadmin. Write its email and password down – nobody can recover it."
echo "  • Next time, start SmartSchool with:  $DIR/smartschool-start.sh"
