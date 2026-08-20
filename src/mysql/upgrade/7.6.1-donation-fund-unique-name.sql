-- ChurchCRM 7.6.1 — Add UNIQUE constraint on donationfund_fun.fun_Name
--
-- The service layer enforces name uniqueness in PHP, but without a DB-level
-- constraint two concurrent admin requests could both pass the check-then-act
-- guard and save duplicate names, corrupting fund dropdowns throughout the UI.
--
-- De-duplication pass: if any duplicates already exist (e.g. pre-constraint
-- installs), keep the row with the lowest fun_ID and delete the rest.
-- Idempotent: safe to re-run; subsequent runs match nothing.

DELETE f2
FROM donationfund_fun f2
INNER JOIN (
    SELECT fun_Name, MIN(fun_ID) AS keep_id
    FROM donationfund_fun
    WHERE fun_Name IS NOT NULL
    GROUP BY fun_Name
    HAVING COUNT(*) > 1
) dup ON f2.fun_Name = dup.fun_Name AND f2.fun_ID != dup.keep_id;

-- Add the UNIQUE index only when it does not already exist.
-- Uses information_schema to make the statement idempotent.
SET @exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'donationfund_fun'
      AND INDEX_NAME   = 'fun_Name_unique'
);

SET @sql = IF(@exists = 0,
    'ALTER TABLE `donationfund_fun` ADD UNIQUE KEY `fun_Name_unique` (`fun_Name`)',
    'SELECT ''fun_Name_unique already exists — skipping'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
