CREATE TABLE IF NOT EXISTS finansijski_planovi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sz_id INT NOT NULL,
    godina INT NOT NULL,
    tekuce_po_delu DECIMAL(12,2) NOT NULL DEFAULT 0,
    upravljanje_po_delu DECIMAL(12,2) NOT NULL DEFAULT 0,
    garaza_po_mestu DECIMAL(12,2) NOT NULL DEFAULT 0,
    investiciono_po_m2 DECIMAL(12,2) NOT NULL DEFAULT 0,
    stepen_naplate DECIMAL(5,2) NOT NULL DEFAULT 100,
    nepredvidjeni_proc DECIMAL(5,2) NOT NULL DEFAULT 0,
    napomena TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sz_godina (sz_id, godina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finansijski_plan_stavke (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    tip ENUM('priliv','odliv') NOT NULL,
    naziv VARCHAR(190) NOT NULL,
    grupa VARCHAR(120) NULL,
    period ENUM('mesecno','godisnje','jednokratno') NOT NULL DEFAULT 'godisnje',
    iznos DECIMAL(12,2) NOT NULL DEFAULT 0,
    napomena TEXT NULL,
    predefinisana TINYINT(1) NOT NULL DEFAULT 0,
    aktivna TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_tip (plan_id, tip),
    CONSTRAINT fk_fin_plan_stavke_plan FOREIGN KEY (plan_id) REFERENCES finansijski_planovi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
