-- Cree le compte applicatif au premier demarrage de la base.
--
-- L'application ne tourne JAMAIS sous le compte proprietaire du schema :
-- c'est ce qui rend le journal d'audit inalterable
-- (docs/ARCHITECTURE_LOCAL.md 5.1).
--
-- Ce fichier cree le COMPTE et rien d'autre. Les droits sont poses table par
-- table par la migration 2026_01_01_000800, puis rafraichis par
-- 2026_01_11_000100 et par `php artisan phoenix:droits`.
--
-- POURQUOI AUCUN `GRANT ... ON phoenix.*` ICI, meme « pour demarrer » : sur
-- MySQL les droits de base et de table s'ADDITIONNENT. Un droit de base ne
-- peut pas etre repris par une revocation sur une table : il rendrait le
-- journal d'audit modifiable par l'application, definitivement et sans que
-- rien ne le signale (D-051).

CREATE USER IF NOT EXISTS 'phoenix_app'@'%'
    -- Mot de passe de developpement local uniquement. En usage reel, le
    -- changer et le sortir du depot.
    IDENTIFIED BY 'phoenix_app_local';

-- Le compte proprietaire est cree par l'image via MYSQL_USER. Il lui faut
-- GRANT OPTION pour pouvoir, depuis les migrations, accorder ses droits au
-- compte applicatif.
GRANT ALL PRIVILEGES ON `phoenix`.* TO 'phoenix_owner'@'%' WITH GRANT OPTION;
