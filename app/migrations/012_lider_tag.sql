-- B1 Volunteers · eticheta Lider: doar aceste persoane pot fi alese pe locul de lider
ALTER TABLE users ADD COLUMN is_leader TINYINT NOT NULL DEFAULT 0;
