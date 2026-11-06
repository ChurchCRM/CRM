-- Stored coordinates for a person's own address (#10888): group and cart maps read these instead of geocoding on every load.
-- GeocodeTriedAt set with no coordinates means the address was tried and not found; NULL means never tried.
ALTER TABLE `person_per`
  ADD COLUMN `per_Latitude` double DEFAULT NULL,
  ADD COLUMN `per_Longitude` double DEFAULT NULL,
  ADD COLUMN `per_GeocodeTriedAt` timestamp NULL DEFAULT NULL;

ALTER TABLE `family_fam`
  ADD COLUMN `fam_GeocodeTriedAt` timestamp NULL DEFAULT NULL;
