"# allocate_funds_sks" 

<label>
docker compose build php <br>
docker compose up -d <br>
docker compose exec php php composer install<br>
docker compose exec php php artisan config:clear <br>
docker compose exec php php artisan migrate <br>
</label>



<label>
docker compose exec --user root php sh -c "mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R u+rwX storage bootstrap/cache" </label><br>
