CREATE VIEW emails_duplicate AS
    SELECT
        email, total
    FROM
        (SELECT
            email, COUNT(*) AS total
        FROM
            (SELECT
            fam_Email AS email, 'family' AS type, fam_id AS id
        FROM
            family_fam
        WHERE
            fam_email IS NOT NULL
                AND fam_email != '' UNION SELECT
            per_email AS email, 'person_home' AS type, per_id AS id
        FROM
            person_per
        WHERE
            per_email IS NOT NULL
                AND per_email != '' UNION SELECT
            per_WorkEmail AS email, 'person_work' AS type, per_id AS id
        FROM
            person_per
        WHERE
            per_WorkEmail IS NOT NULL
                AND per_WorkEmail != '') emails
        GROUP BY email) AS emailTotals
    WHERE
        total > 1
