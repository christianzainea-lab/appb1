-- B1 Volunteers · verificare: coloana pentru calendar există (în caz că 009 a fost întreruptă)
ALTER TABLE users ADD COLUMN calendar_token VARCHAR(64) NULL;
