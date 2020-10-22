
FROM mediawiki:1.34

RUN apt-get -y update && \
apt-get install -y ghostscript xpdf-utils

RUN php -r "copy('https://github.com/simplesamlphp/simplesamlphp/releases/download/v1.18.8/simplesamlphp-1.18.8.tar.gz', 'simplesamlphp-1.18.8.tar.gz');" && \
tar -xvf simplesamlphp-1.18.8.tar.gz -C /var/

RUN mv /var/simplesamlphp-1.18.8 /var/simplesamlphp

COPY simplesamlphp/config/*.php /var/simplesamlphp/config/

RUN touch /var/simplesamlphp/modules/cron/enable /var/simplesamlphp/modules/metarefresh/enable

RUN mkdir /tmp/backup && \
    mv /var/www/html/* /tmp/backup && \
    mkdir /var/www/html/wiki && \
    mv /tmp/backup/* /var/www/html/wiki/

# Required Extensions
RUN php -r "copy('https://extdist.wmflabs.org/dist/extensions/PluggableAuth-REL1_34-17fb1ea.tar.gz', 'PluggableAuth-REL1_34-17fb1ea.tar.gz');" && \
    tar -xzf PluggableAuth-REL1_34-17fb1ea.tar.gz -C /var/www/html/wiki/extensions && \
    rm -rf PluggableAuth-REL1_34-17fb1ea.tar.gz

RUN php -r "copy('https://extdist.wmflabs.org/dist/extensions/SimpleSAMLphp-REL1_34-e6154d8.tar.gz', 'SimpleSAMLphp-REL1_34-e6154d8.tar.gz');" && \
    tar -xzf SimpleSAMLphp-REL1_34-e6154d8.tar.gz -C /var/www/html/wiki/extensions && \
    rm -rf /var/www/html/SimpleSAMLphp-REL1_34-e6154d8.tar.gz

RUN php -r "copy('https://extdist.wmflabs.org/dist/extensions/Lockdown-REL1_34-95cdc71.tar.gz', 'Lockdown-REL1_34-95cdc71.tar.gz');" && \
    tar -xvf Lockdown-REL1_34-95cdc71.tar.gz -C /var/www/html/wiki/extensions && \
    rm -rf Lockdown-REL1_34-95cdc71.tar.gz

# Nice to have extensions
RUN php -r "copy('https://extdist.wmflabs.org/dist/extensions/Math-REL1_34-b1a022f.tar.gz', 'Math-REL1_34-b1a022f.tar.gz');" && \
    tar -xzf Math-REL1_34-b1a022f.tar.gz -C /var/www/html/wiki/extensions && \
    rm -rf Math-REL1_34-b1a022f.tar.gz

RUN php -r "copy('https://translatewiki.net/mleb/MediaWikiLanguageExtensionBundle-2020.04.tar.bz2', 'MediaWikiLanguageExtensionBundle-2020.04.tar.bz2');" && \
tar --wildcards -C /var/www/html/wiki/extensions -xjf MediaWikiLanguageExtensionBundle-2020.04.tar.bz2 --strip 1 extensions/* && \
rm -rf MediaWikiLanguageExtensionBundle-2020.04.tar.bz2


# Wiki configuratoin
COPY LocalSettings.php /var/www/html/wiki/

RUN ln -s /var/simplesamlphp/www /var/www/html/simplesamlphp

RUN mkdir /var/simplesamlphp/metadata/o365 /var/simplesamlphp/data/db && \
    chown www-data:www-data /var/simplesamlphp/data/db /var/simplesamlphp/metadata/o365 && \
    chown www-data:www-data /var/www/html/wiki/images

# Custom logo and favicon
COPY assets/logo.png /var/www/html/wiki/resources/assets/wiki.png
COPY assets/favicon.ico /var/www/html/wiki/resources/assets/favicon.ico

COPY html/index.php /var/www/html/index.php
COPY etc/apache2/conf-available/docker-php.conf /etc/apache2/conf-available/docker-php.conf

COPY docker-php-entrypoint /usr/local/bin/docker-php-entrypoint
