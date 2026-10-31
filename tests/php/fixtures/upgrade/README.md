# Upgrade fixtures

These immutable SQL inputs let the upgrade tests compare current behavior with
the schema and migration that existed before the change.

- `Install-7.8.0.sql`: `ChurchCRM/CRM`, tag `7.8.0`, `src/mysql/install/Install.sql`.
- `7.8.1-original.sql`: `ChurchCRM/CRM`, commit `ed7ec9ccd7594953d9094843346730ef5310ff4d`, `src/mysql/upgrade/7.8.1.sql`.

Keep historical inputs fixed when changing the current upgrader. Add a separate
fixture when a test needs a different historical state.
