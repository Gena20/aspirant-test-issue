Test task
=====================================

Done
--------------

- import 10 trailers;
- display trailers on the home page;
- show trailer info with "source" button;
- add users.

Set up docker
--------------
Need to run these commands:

- docker-compose up -d
- docker-compose exec app bin/console orm:schema-tool:create
- docker-compose exec app bin/console fetch:trailers
- docker-compose exec app php -S 127.0.0.1:8080 -t public