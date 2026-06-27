-- Pokreni jednom pre testiranja upload-a dokumenata ponuda.

ALTER TABLE ponude
    ADD COLUMN IF NOT EXISTS sz_id INT NULL AFTER izvodjac_id;

CREATE TABLE IF NOT EXISTS ponuda_dokumenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ponuda_id INT NOT NULL,
    naziv_fajla VARCHAR(255) NOT NULL,
    putanja VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    velicina INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ponuda_dokumenti_ponuda (ponuda_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
