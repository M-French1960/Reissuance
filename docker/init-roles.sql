-- Cree le role applicatif au premier demarrage de la base.
--
-- L'application ne tourne JAMAIS sous le proprietaire du schema : c'est ce
-- qui rend le journal d'audit inalterable (docs/ARCHITECTURE_LOCAL.md 5.1).
-- Les droits precis sont poses par la migration 2026_01_01_000800.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'phoenix_app') THEN
        -- Mot de passe de developpement local uniquement. En usage reel, le
        -- changer et le sortir du depot.
        CREATE ROLE phoenix_app LOGIN PASSWORD 'phoenix_app_local';
    END IF;
END
$$;

GRANT USAGE ON SCHEMA public TO phoenix_app;
