FROM wehostasia/laravel:nginx-php-8.3

RUN echo "0 * * * * www-data /usr/bin/php /var/www/artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/hi-task-scheduler
