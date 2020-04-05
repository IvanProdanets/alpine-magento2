FROM php:7.2-fpm-alpine3.9

RUN apk --update --no-cache add \
    bash \
	curl \
	g++ \
	freetype-dev \
	icu \
	icu-dev \
	libjpeg-turbo-dev \
	libpng-dev \
	libxml2-dev \
	libxslt-dev \
	shadow \
	zlib-dev

RUN docker-php-ext-configure \
	gd --with-jpeg-dir=/usr/local/ --with-freetype-dir=/usr/include/
RUN docker-php-ext-install \
	gd \
	bcmath \
	intl \
	pdo_mysql \
	soap \
	xsl \
	sockets \
	zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN usermod -u 1000 nobody && \
	groupmod -g 1000 nobody

COPY ./bin/startup.sh /usr/local/bin/startup.sh
COPY ./conf/php.ini /usr/local/etc/php/php.ini
COPY ./conf/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY ./bin/install-magento /usr/local/bin/install-magento
RUN chmod +x /usr/local/bin/install-magento

COPY ./bin/install-sampledata /usr/local/bin/install-sampledata
RUN chmod +x /usr/local/bin/install-sampledata

EXPOSE 9000
WORKDIR /var/www/html
CMD [ "sh", "/usr/local/bin/startup.sh" ]
