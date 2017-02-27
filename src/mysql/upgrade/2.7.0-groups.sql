ALTER TABLE group_grp
ADD COLUMN grp_active BOOLEAN NOT NULL DEFAULT 1 AFTER grp_hasSpecialProps,
ADD COLUMN grp_include_email_export BOOLEAN NOT NULL DEFAULT 1 AFTER grp_active;