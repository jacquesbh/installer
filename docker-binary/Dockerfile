FROM php:7.0-cli

RUN requirements="libpng-dev libmcrypt-dev libmcrypt4 libcurl3-dev libfreetype6 libjpeg62-turbo libfreetype6-dev libjpeg62-turbo-dev libxml2-dev git libtidy-dev" \
    && apt-get update && apt-get install -y $requirements && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd mcrypt mbstring soap bcmath tidy \
    && requirementsToRemove="libpng-dev libmcrypt-dev libcurl3-dev libfreetype6-dev libjpeg62-turbo-dev libxml2-dev" \
    && apt-get purge --auto-remove -y $requirementsToRemove

WORKDIR /work

RUN mkdir /usr/local/{src,bin} \
    && git clone https://github.com/jacquesbh/installer.git /usr/local/src/installer \
    && ln -s /usr/local/src/installer/bin/Installer /usr/local/bin/installer \
    && chmod +x /usr/local/bin/installer

CMD ["/usr/local/bin/installer"]
