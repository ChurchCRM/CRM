FROM churchcrm/crm:latest-php8-apache

# Render has no Blueprint user override for initializing an attached disk.
USER root
COPY --chmod=755 docker/apache/persistent-entrypoint.sh /usr/local/bin/churchcrm-entrypoint
RUN mkdir -p /var/lib/churchcrm && \
    chown www-data:www-data /var/lib/churchcrm && \
    printf '<Directory /var/lib/churchcrm>\n    Require all denied\n</Directory>\n' > /etc/apache2/conf-enabled/churchcrm-persistent.conf
ENTRYPOINT ["churchcrm-entrypoint"]
CMD ["apache2-foreground"]
