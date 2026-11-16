# Integration suite

The unit suite (`composer test`) boots on the hand-written stubs in
`tests/stubs`. This suite boots a real WordPress instead, on a real MySQL
database, and exercises the adapter against it: the `$wpdb` connection adapter
and SQL dialect, options and the Settings API, the hook bridge, WP-Cron
registration, attribute REST routes dispatched through the REST kernel, and
text-domain translation. It has its own configuration
(`phpunit.integration.xml`) and bootstrap; the unit suite excludes this
directory.

CI runs it once per supported WordPress major (the latest major and the
previous one), on the newest patch release of each.

## Run it locally

1. Start a MySQL 8.4 database, for example:

   ```bash
   docker run -d --name wp-integration-db -p 127.0.0.1:33306:3306 \
     -e MYSQL_DATABASE=wordpress -e MYSQL_USER=wordpress \
     -e MYSQL_PASSWORD=wordpress -e MYSQL_RANDOM_ROOT_PASSWORD=yes mysql:8.4
   ```

2. Download a WordPress major (the newest patch release, checksum verified)
   into `.wordpress/<major>` (gitignored):

   ```bash
   bin/wp-integration-setup.sh 7.1
   ```

3. Run the suite:

   ```bash
   WORDPRESS_DIR="$PWD/.wordpress/7.1" WORDPRESS_MAJOR=7.1 \
   WORDPRESS_DB_HOST=127.0.0.1:33306 WORDPRESS_DB_NAME=wordpress \
   WORDPRESS_DB_USER=wordpress WORDPRESS_DB_PASSWORD=wordpress \
   composer test:integration
   ```

The bootstrap drops every table with the `wp_` prefix in that database and
installs a fresh site on each run, so point it at a database used for nothing
else. `WORDPRESS_DB_NAME`, `WORDPRESS_DB_USER` and `WORDPRESS_DB_HOST` are
required: the generated `wp-config.php` refuses to run without them instead of
falling back to a guessed database. `WORDPRESS_MAJOR` is optional; when set, a test asserts that the loaded
WordPress belongs to that major.
