#!/bin/sh
set -eu

if [ "${CHURCHCRM_PERSISTENT_DATA:-0}" = 1 ]; then
    app=/var/www/html
    data=/var/lib/churchcrm
    umask 077
    if [ ! -d "$data" ] || [ ! -w "$data" ]; then
        echo 'ChurchCRM: /var/lib/churchcrm must be writable by UID 33.' >&2
        exit 1
    fi
    mkdir -p "$data/Person" "$data/Family"
    # Railway mounts volumes as root and supports a root bootstrap via RUN_UID.
    if [ "$(id -u)" = 0 ]; then
        chown 33:33 "$data" "$data/Person" "$data/Family"
    fi

    # Refuse migration in place: existing installations need a backup and restore.
    if [ -f "$app/Include/Config.php" ] && [ ! -L "$app/Include/Config.php" ]; then
        echo 'ChurchCRM: move the existing Config.php to the persistent disk first.' >&2
        exit 1
    fi
    ln -sfn "$data/Config.php" "$app/Include/Config.php"
    # Config.php resolves its bootstrap relative to its real location on disk.
    ln -sfn "$app/Include/LoadConfigs.php" "$data/LoadConfigs.php"
    for kind in Person Family; do
        source="$app/Images/$kind"
        if [ ! -L "$source" ]; then
            if find "$source" -mindepth 1 ! -name .htaccess ! -name .gitkeep | grep -q .; then
                echo "ChurchCRM: move existing $kind photos to the persistent disk first." >&2
                exit 1
            fi
            # Refresh access rules from each image rather than freezing them on disk.
            if [ -f "$source/.htaccess" ]; then
                cp "$source/.htaccess" "$data/$kind/.htaccess"
            else
                printf 'Require all denied\n' > "$data/$kind/.htaccess"
            fi
            chmod 644 "$data/$kind/.htaccess"
            rm -f "$source/.htaccess" "$source/.gitkeep"
            rmdir "$source"
            ln -s "$data/$kind" "$source"
        fi
    done
fi

exec docker-php-entrypoint "$@"
