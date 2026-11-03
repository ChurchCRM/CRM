# Large-dataset upgrade benchmark

Run the benchmark with:

```sh
npm run benchmark:upgrade:large
```

The command uses the existing test Compose database service in a unique Compose project, loads the repository's ChurchInfo 1.3.1 fixture into isolated benchmark databases, and executes the registered upgrade files in order. It creates the large synthetic data at the 7.8.0 checkpoint, runs the 7.8.0-to-current path twice, then repeats that recent path from a separate clean checkpoint. It reports each SQL statement and file duration. PHP migration files are timed as whole files.

The harness mirrors the order in `upgrade.json` and invokes the PHP migration files after initializing the application. It does not run the web request loop: timing each SQL statement requires the harness to execute the same SQL statements individually.

The oldest included fixture is ChurchInfo 1.3.1; the repository has no 1.2.14 dump. The timing report focuses on 7.8.0-to-current and does not claim an exact 1.2.14 starting-schema run.

Requirements: Docker Compose, PHP extensions from `npm run env:check -- php`, and installed PHP dependencies (`npm run build:php`). The database container uses MariaDB 10.11 from `docker/docker-compose.yaml`. Set `UPGRADE_BENCHMARK_PORT` when host port 33318 is occupied.

The harness uses a temporary `src/Include/Config.php` to initialize ChurchCRM for PHP migrations, then removes it on exit. Start from a clean checkout without an existing `src/Include/Config.php`. Benchmark databases and Compose volumes are removed with the unique Compose project when the command exits.

## Timing report — 2026-11-03

The measured development checkout was package version 7.8.3. MariaDB reported `10.11.19-MariaDB-ubu2204`; PHP was 8.5.12. Docker Desktop ran on a 6-core, 8 GiB host. Each run completed through 7.8.3, and the post-upgrade `UpgradeService` check and family/person count smoke check passed.

The synthetic generator is deterministic and contains no real church data. Final counts at the 7.8.0 checkpoint:

| Table | Rows |
|---|---:|
| `family_fam` | 9,000 |
| `person_per` | 25,000 |
| `pledge_plg` | 400,000 |
| `email_log_eml` | 500,000 |
| `event_attend` | 100,000 |
| `note_nte` | 50,001 (50,000 generated plus one fixture row) |

These measurements cover the recent 7.8.0-to-7.8.3 path. The older fixture was used to prepare a real 7.8.0 schema; data is generated at that checkpoint so the large email-log and pledge rekey operations run at the target scale. Earlier 7.x migrations are not included in the large-data timing table.

### Per-file timings

Seconds, from two full benchmark runs and the separate 7.8.0-to-current run:

| Upgrade file | Full run 1 | Full run 2 | 7.8.0 path run |
|---|---:|---:|---:|
| `7.8.0.sql` | 0.1103 | 0.2135 | checkpoint 0.0986 |
| `7.8.1.sql` | 19.0102 | 19.1870 | 19.8255 |
| `7.8.1.php` | 0.0213 | 0.0224 | 0.0237 |
| `7.8.2.sql` | 0.0099 | 0.0086 | 0.0075 |
| `7.8.2-pledge-groupkey-index.sql` | 0.4492 | 0.3075 | 0.3337 |
| `7.8.2-tokens-valid-until-datetime.sql` | 0.0083 | 0.0054 | 0.0054 |
| `7.8.3.sql` | 0.0014 | 0.0011 | 0.0010 |
| `7.8.3-member-sign-in.sql` | 0.0060 | 0.0033 | 0.0037 |

### Top five statements

The ordering matched in both full runs. Statement duration is shown in seconds.

| Rank | Statement | Run 1 | Run 2 | Table operation and lock window | Recommendation |
|---:|---|---:|---:|---|---|
| 1 | `7.8.1.sql`: payment rekey `UPDATE pledge_plg ... JOIN` | 9.5110 | 9.2503 | Updates payment rows in `pledge_plg`; InnoDB holds affected row locks until the autocommit statement ends. | Chunk by stable batches of old `plg_GroupKey` values, updating each whole group. A rerun can select only groups that still contain both pledge and payment rows. |
| 2 | `7.8.1.sql`: denomination rekey `UPDATE pledge_denominations_pdem ... JOIN` | 7.0312 | 7.3527 | Updates matching denomination rows while scanning/grouping `pledge_plg`; the measured write lock is on `pledge_denominations_pdem`. | Use the same bounded old-GroupKey batches as the payment rekey, in the same script. |
| 3 | `7.8.1.sql`: `UPDATE email_log_eml SET eml_Attempts = 1 ...` | 2.0666 | 2.2236 | Updates up to 500,000 rows in the hot `email_log_eml` table; affected row locks last for the statement. | Chunk by `eml_ID`, filtering `eml_Attempts = 0` and sent/failed status so a rerun skips completed rows. |
| 4 | `7.8.2-pledge-groupkey-index.sql`: add `idx_plg_GroupKey` | 0.4464 | 0.3056 | Builds an index on the 400,000-row `pledge_plg`; metadata/table lock is held during index creation. | Leave. The measured build is under 0.5 seconds. |
| 5 | `7.8.1.sql`: add `idx_eml_Batch` | 0.3020 | 0.2470 | Builds an index on the 500,000-row `email_log_eml`; metadata/table lock is held during index creation. | Leave. The measured build is under 0.31 seconds. |

Lock durations above use the statement wall time as the autocommit lock window. The harness does not collect concurrent-session lock waits. No single statement approached 30 seconds; the two pledge updates still warrant chunking because they hold locks for multiple seconds on an active table.

No upgrade SQL was changed. The measured chunking recommendations should be handled in separate follow-up work with before/after runs using this command.
