-- Modification du champ duree pour accepter explicitement les nombres négatifs
ALTER TABLE heures_supplementaires MODIFY COLUMN duree DECIMAL(4,2) NOT NULL; 