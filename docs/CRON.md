# Scheduled jobs (cron)

Everything on the site that needs to run on a timer lives here. Only one
endpoint exists today (`api/send_reminders.php`); this doc is also the
place to record future scheduled work so nobody has to grep the codebase
to figure out what should be firing.

---

## What runs when

| Endpoint / script                        | Cadence     | Purpose                                                                                                                                       |
| ---------------------------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `api/send_reminders.php?token=<TOKEN>`   | every 30 min | Sends the 24-hour and 2-hour "your session is soon" reminder emails. Idempotent — each booking's reminder is stamped so a re-run never resends. |

Anything else the app depends on runs *inline* from a user action
(booking → invoice, payment webhook → tickets, corporate lead → event
spawn) and does not need a cron entry.

---

## Set the reminder token

The endpoint fails closed without a shared secret. Set the token once
in the DB — any long random string will do:

```sql
UPDATE site_settings SET value = 'PICK-A-LONG-RANDOM-STRING' WHERE `key` = 'reminder_cron_token';
```

(Migration `044_phase7_booking_reminders.sql` creates the row with a
random placeholder. Rotate it whenever a staff member with cron access
leaves.)

Confirm it's set:

```
curl -fsS "https://jaemiesoundbath.com/api/send_reminders.php?token=YOUR-TOKEN"
```

You should get a JSON `{"ok":true,"sent":N,"...":"..."}` response. A
`403` or `500` means the token is missing or wrong.

---

## Hostinger cPanel setup

1. cPanel → **Cron Jobs**.
2. **Common Settings:** *Once per 30 minutes (\*/30 \* \* \* \*)*.
3. **Command:**

   ```
   /usr/bin/curl -fsS "https://jaemiesoundbath.com/api/send_reminders.php?token=YOUR-TOKEN" >/dev/null
   ```

   The `-fsS` flags:
   - `-f` — fail non-zero on HTTP errors so cPanel logs the failure
   - `-s` — silent (no progress bar in the log)
   - `-S` — but still show the error message on failure

4. **Save.** cPanel shows the entry in the "Current Cron Jobs" list.

The URL can be called from anywhere — a different server, an external
monitor, a laptop at home. The token is the only credential.

---

## Raw crontab (VPS / SSH access)

If you're not on shared hosting:

```
# m  h dom mon dow  command
*/30 *  *   *   *   /usr/bin/curl -fsS "https://jaemiesoundbath.com/api/send_reminders.php?token=YOUR-TOKEN" >/dev/null 2>&1
```

---

## CLI alternative (Hostinger PHP cron)

Some Hostinger plans offer PHP-CLI cron directly. The reminder script
skips the token check when invoked from CLI (auth becomes "you have
filesystem access"):

```
*/30 *  *   *   *   /usr/local/bin/php /home/u822252863/domains/jaemiesoundbath.com/public_html/api/send_reminders.php >/dev/null 2>&1
```

Replace the path with whatever `php -r 'echo PHP_BINARY;'` reports on
that host.

---

## Verifying it's working

- **Every run stamps `event_bookings.reminder_24h_sent_at` /
  `reminder_2h_sent_at`** once the corresponding email fires. Any row
  with a NULL stamp on an event whose `starts_at` was ~24h ago means
  the cron didn't run.
- Log tail: `logs/php-error.log` for exceptions; the endpoint itself
  echoes a JSON summary you can save with
  `curl ... >> logs/reminder-cron.log 2>&1`.
- Sanity check: `SELECT COUNT(*) FROM event_bookings WHERE reminder_24h_sent_at IS NOT NULL AND created_at > NOW() - INTERVAL 7 DAY;`
  — should grow over time.

---

## When to add a new cron

Prefer inline / event-driven work first. Only reach for a cron entry
when:

- The trigger is *time itself*, not a user action (reminders, expiring
  memberships, subscription renewal charges, month-end reports).
- OR the work is too heavy for a request-thread and can be deferred to
  a background sweep.

When you do add one, mirror the pattern already established:

1. Endpoint lives under `api/*.php`.
2. Fails closed without a token stored in `site_settings`.
3. Runs from CLI without a token.
4. Every effect it produces is *idempotent* — stamp what you send /
   record what you paid so a double-fire is a no-op.
5. Add a row to the "What runs when" table above.
