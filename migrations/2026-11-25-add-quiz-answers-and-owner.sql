ALTER TABLE Tietovisat
    ADD COLUMN Vastaus1 VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN Vastaus2 VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN Vastaus3 VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN Vastaus4 VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN OikeaVastaus TINYINT UNSIGNED NULL,
    ADD COLUMN OmistajaID INT NULL,
    ADD COLUMN VisaTunniste CHAR(32) NULL,
    ADD INDEX idx_tietovisat_omistaja (OmistajaID, VisaTunniste),
    ADD CONSTRAINT fk_tietovisat_omistaja
        FOREIGN KEY (OmistajaID) REFERENCES kayttajat (UserID)
        ON DELETE SET NULL;

GRANT DELETE ON peliprojekti.Tietovisat TO 'user'@'%';