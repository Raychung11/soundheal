# Deploying to Hostinger

Deploy is SSH-driven. After you push to GitHub you SSH into the
Hostinger box and run `./deploy.sh`. The script fetches the latest
code, fixes writable-directory permissions, and tells you if any new
database migrations need applying.

One-time setup:

1. A **SSH Deploy Key** so Hostinger can read a private GitHub repo.
2. Clone the repo into `~/domains/jaemiesoundbath.com/public_html`.

After that, every deploy is just:

```bash
cd ~/domains/jaemiesoundbath.com/public_html
./deploy.sh                # pull + chmod + list pending migrations
./deploy.sh --migrate      # same, then also apply pending migrations
```

Everything below is a one-time setup unless the SSH key is ever rotated.

---

## 1 · Generate an SSH key on Hostinger

Hostinger's Git panel can generate a key for you, but a manual key
lives on the account rather than the site and works for any repo you
add later.

**Path in hPanel:** `Advanced` → `SSH Access`. Turn SSH on and note
the host / port / username. Then in your terminal:

```bash
ssh -p <port> <hostinger-user>@<hostinger-host>

# Once you're in:
mkdir -p ~/.ssh && chmod 700 ~/.ssh
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519_soundheal -C "hostinger-soundheal-deploy" -N ""
cat ~/.ssh/id_ed25519_soundheal.pub          # ← copy this whole line
```

Add a small SSH config so `git` picks the right key without arguments:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_soundheal
    IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config
```

Verify GitHub accepts the key (this will ask you to confirm the host
fingerprint the first time — say **yes**):

```bash
ssh -T git@github.com
# Expected: "Hi Raychung11! You've successfully authenticated…"
```

---

## 2 · Add the public key to GitHub as a Deploy Key

1. Open <https://github.com/Raychung11/soundheal/settings/keys>.
2. **Add deploy key**.
3. Title: `Hostinger — jaemiesoundbath.com`.
4. Key: paste the whole `ssh-ed25519 …` line from step 1.
5. **Do not** tick "Allow write access" — Hostinger only ever needs to pull.
6. Add key.

Deploy keys are per-repo and read-only by default, which is exactly what a puller wants.

---

## 3 · Clone the repo into the docroot

Hostinger's Git panel can do this for you, or you can do it by hand:

```bash
cd ~/domains/jaemiesoundbath.com
git clone -b claude/setup-php-wellness-platform-mjtkQ \
    git@github.com:Raychung11/soundheal.git public_html
```

(Swap the branch for `main` once the PR is merged.)

If you already have a `public_html/` you want to keep, back it up
first — `git clone` refuses to write into a non-empty directory.

---

## 4 · Deploy on every push

After each `git push` from your dev machine, SSH in and run:

```bash
cd ~/domains/jaemiesoundbath.com/public_html
./deploy.sh
```

The script prints what changed, fixes permissions, and lists any
pending migrations. When migrations show up, re-run with
`./deploy.sh --migrate` to apply them.

If you want fully-automatic deploys instead of SSH-and-run, wire
GitHub's push webhook to Hostinger's Git panel URL — but the manual
`./deploy.sh` gives you a beat of control before schema changes land,
which matters more than the extra keystroke.

---

## 5 · Files that must be created by hand on the server

The following are **git-ignored** and must live on Hostinger, not in
the repo:

| Path | Why | How |
|---|---|---|
| `.env` | secrets (DB, mail, API keys) | copy `.env.example` → `.env`, fill in |
| `uploads/**` files | user-uploaded media (photos, audio, PDFs) | already there; back these up |
| `logs/*.log` | rotating logs | Apache creates on first write |

**Migrations are surfaced by `deploy.sh`** — after a `./deploy.sh`
pulls in a new `database/migrations/xxx.sql` file, the script prints
a warning and lists the pending files. Run `./deploy.sh --migrate` to
apply them. The applied set is tracked in a `schema_migrations` table
on the DB so each migration only runs once even if you re-invoke.

---

## Rolling back

`deploy.sh` always fast-forwards to the tip of the tracked branch.
To roll back:

```bash
cd ~/domains/jaemiesoundbath.com/public_html
git fetch origin
git reset --hard <sha-you-want>
```

Then don't run `./deploy.sh` again until you're ready to move forward
— the next invocation will pull the branch tip back in.

If you want to freeze on an older commit for a while, push a hotfix
branch (`hotfix/rollback-<date>`) to that sha and point `deploy.sh` at
it by checking that branch out on the server.

---

## Security notes baked into the codebase

- `.htaccess` returns 403 for `/.git/…`, `.env`, `config/`, `includes/`,
  `logs/`, `database/`, and repo metadata (`README.md`, `composer.json`,
  etc.), so even if Hostinger drops those into the docroot they cannot
  be fetched over HTTPS.
- All credentials live in `.env` or in the DB (`site_settings`), never
  in git.
- Uploads use hashed filenames — no user-controlled paths on disk.
