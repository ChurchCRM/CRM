#!/usr/bin/env bash
set -euo pipefail

image=${1:?Usage: test-production-image.sh IMAGE apache|fpm}
flavor=${2:?Specify apache or fpm}
case "$flavor" in
  apache) port=8080 ;;
  fpm) port=9000 ;;
  *) echo 'Expected apache or fpm' >&2; exit 1 ;;
esac

docker run --rm --cap-drop ALL --security-opt no-new-privileges "$image" sh -ec '
  test "$(id -u)" -ne 0
  ! command -v gcc
  ! command -v g++
  ! command -v make
  test ! -d /usr/include/linux
  test ! -d /usr/local/include/php
  test -f /var/www/html/.htaccess
  test -f /var/www/html/vendor/autoload.php
  test ! -e /var/www/html/apache/default.conf
  test ! -e /var/www/html/Include/Config.php
  php -r '\''foreach (["bcmath", "curl", "exif", "gd", "gettext", "iconv", "intl", "mbstring", "mysqli", "Zend OPcache", "pdo_mysql", "xml", "zip"] as $extension) {
      if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: $extension\n"); exit(1); }
  }
  if (extension_loaded("xdebug")) { exit(1); }'\''
'

container=$(docker run -d --cap-drop ALL --security-opt no-new-privileges "$image")
trap 'docker rm -f "$container" >/dev/null' EXIT
for attempt in {1..30}; do
  if docker exec "$container" php -r '$s = @fsockopen("127.0.0.1", (int)$argv[1]); if (!$s) { exit(1); } fclose($s);' "$port"; then
    if [[ "$flavor" == apache ]]; then
      docker exec "$container" php -r '
        $context = stream_context_create(["http" => ["follow_location" => 0, "ignore_errors" => true]]);
        file_get_contents("http://127.0.0.1:8080/", false, $context);
        if (!preg_match("~^HTTP/\\S+ (200|302|303) ~", $http_response_header[0] ?? "")) { exit(1); }
      '
    fi
    exit 0
  fi
  sleep 1
done
docker logs "$container"
exit 1
