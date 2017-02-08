ALTER TABLE group_grp
ADD COLUMN grp_active BOOLEAN NOT NULL AFTER grp_hasSpecialProps,
ADD COLUMN grp_enable_email_export BOOLEAN NOT NULL AFTER grp_active;
