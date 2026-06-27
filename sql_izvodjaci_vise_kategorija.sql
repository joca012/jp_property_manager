-- Pokreni jednom pre kopiranja/otvaranja novih PHP fajlova.
-- Uvodimo vezu: jedan izvođač može imati više kategorija radova.

ALTER TABLE izvodjaci
    ADD COLUMN IF NOT EXISTS maticni_broj VARCHAR(20) NULL AFTER pib,
    ADD COLUMN IF NOT EXISTS adresa VARCHAR(255) NULL AFTER maticni_broj;

ALTER TABLE ponude
    ADD COLUMN IF NOT EXISTS izvodjac_id INT NULL AFTER dobavljac;

CREATE TABLE IF NOT EXISTS izvodjac_kategorije (
    id INT AUTO_INCREMENT PRIMARY KEY,
    izvodjac_id INT NOT NULL,
    kategorija_id INT NOT NULL,
    UNIQUE KEY uq_izvodjac_kategorija (izvodjac_id, kategorija_id),
    KEY idx_ik_izvodjac (izvodjac_id),
    KEY idx_ik_kategorija (kategorija_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ako si već imao staru kolonu izvodjaci.kategorija, prebaci postojeće vrednosti u novu tabelu.
INSERT IGNORE INTO izvodjac_kategorije (izvodjac_id, kategorija_id)
SELECT i.id, kr.id
FROM izvodjaci i
JOIN kategorije_radova kr ON kr.naziv = i.kategorija
WHERE i.kategorija IS NOT NULL AND i.kategorija <> '';
