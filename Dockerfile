FROM debian

RUN apt-get -y update

RUN apt-get install -y \
    php7.0 php7.0-dev php7.0-phar php7.0-json php7.0-iconv php7.0-mbstring php7.0-sockets php7.0-xml php7.0-zip \
    libevent-dev libpq-dev g++ make autoconf

RUN pecl install raphf && echo extension=raphf.so > /etc/php/7.0/cli/conf.d/raphf.ini
RUN pecl install pq    && echo extension=pq.so    > /etc/php/7.0/cli/conf.d/pq.ini

RUN apt-get install -y libssl-dev pkg-config
RUN pecl install event && echo extension=event.so > /etc/php/7.0/cli/conf.d/event.ini

ADD https://getcomposer.org/download/1.5.0/composer.phar /usr/local/bin/composer
RUN chmod a+x /usr/local/bin/composer

WORKDIR /usr/src/app

COPY php.ini /etc/php/7.0/cli/
COPY composer.* /usr/src/app/
RUN composer install

COPY . .
