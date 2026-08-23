-- Base séparée pour la suite de tests (phpunit.xml), afin que
-- php artisan test ne touche jamais aux données de développement.
CREATE DATABASE politimatch_testing;
