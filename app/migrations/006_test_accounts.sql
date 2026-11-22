-- B1 Volunteers · conturi de test (doar pe site-ul de test)
ALTER TABLE users ADD COLUMN is_test TINYINT NOT NULL DEFAULT 0;
