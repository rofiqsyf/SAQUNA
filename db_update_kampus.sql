CREATE TABLE IF NOT EXISTS master_kampus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_kampus VARCHAR(100) NOT NULL,
    alamat VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS master_gedung (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kampus_id INT UNSIGNED NOT NULL,
    nama_gedung VARCHAR(100) NOT NULL,
    FOREIGN KEY (kampus_id) REFERENCES master_kampus(id) ON DELETE CASCADE
);

-- Insert default kampus if empty
INSERT INTO master_kampus (nama_kampus, alamat) 
SELECT 'Kampus Utama', 'Wonosobo' 
WHERE NOT EXISTS (SELECT 1 FROM master_kampus);

-- Insert distinct gedungs from ruangan into master_gedung
INSERT INTO master_gedung (kampus_id, nama_gedung)
SELECT 
    (SELECT id FROM master_kampus LIMIT 1), 
    r.gedung 
FROM (SELECT DISTINCT gedung FROM ruangan WHERE gedung IS NOT NULL AND gedung != '') AS r
WHERE NOT EXISTS (
    SELECT 1 FROM master_gedung mg WHERE mg.nama_gedung COLLATE utf8mb4_unicode_ci = r.gedung COLLATE utf8mb4_unicode_ci
);

-- Alter ruangan table
ALTER TABLE ruangan ADD COLUMN gedung_id INT UNSIGNED NULL;

-- Update gedung_id in ruangan
UPDATE ruangan r 
JOIN master_gedung mg ON r.gedung COLLATE utf8mb4_unicode_ci = mg.nama_gedung COLLATE utf8mb4_unicode_ci
SET r.gedung_id = mg.id;

-- Drop old gedung column
ALTER TABLE ruangan DROP COLUMN gedung;

-- Make gedung_id a foreign key
ALTER TABLE ruangan ADD CONSTRAINT fk_ruangan_gedung FOREIGN KEY (gedung_id) REFERENCES master_gedung(id) ON DELETE SET NULL;
