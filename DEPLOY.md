# Deploying to Hostinger via Git Auto-Deploy

This project deploys with **Hostinger's built-in Git integration** — you push to GitHub, Hostinger pulls into `public_html/` automatically.

Two one-time setups are needed:

1. A **SSH Deploy Key** so Hostinger can read a private GitHub repo.
2. A **Webhook** so GitHub tells Hostinger when a new commit lands.

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

## 3 · Configure Hostinger's Git panel

**Path in hPanel:** `Websites` → `Manage` on jaemiesoundbath.com → `Advanced` → `Git`.

- **Repository address:** `git@github.com:Raychung11/soundheal.git`
- **Branch:** `main` (or `claude/setup-php-wellness-platform-mjtkQ` while iterating pre-merge)
- **Directory:** `public_html`
- Click **Create**.

Hostinger will do the initial clone. If it errors on the key, re-run
`ssh -T git@github.com` from the account and confirm the fingerprint.

---

## 4 · Wire the webhook so pushes auto-deploy

Hostinger shows a **Webhook URL** in the same Git panel after the
first clone (it looks like `https://webhooks.hostinger.com/deploy/…`).
Copy it, then:

1. Open <https://github.com/Raychung11/soundheal/settings/hooks>.
2. **Add webhook**.
3. **Payload URL:** paste Hostinger's URL.
4. **Content type:** `application/json`.
5. **Which events?** _Just the push event._
6. **Active:** ticked.
7. Add webhook.

Push a commit to the branch Hostinger is watching → within a few
seconds the site should reflect the change. GitHub's webhook page
shows the delivery status ("✓ 200") so you can debug.

---

## 5 · Files that must be created by hand on the server

The following are **git-ignored** and must live on Hostinger, not in
the repo:

| Path | Why | How |
|---|---|---|
| `.env` | secrets (DB, mail, API keys) | copy `.env.example` → `.env`, fill in |
| `uploads/**` files | user-uploaded media (photos, audio, PDFs) | already there; back these up |
| `logs/*.log` | rotating logs | Apache creates on first write |
| Migrations run once | schema changes | `mysql -u … < database/migrations/xxx.sql` on each new file |

**After any deploy that adds a new `database/migrations/xxx.sql`
file**, SSH into Hostinger and run it against the live database. There
is no auto-migrator — this is deliberate so surprising schema changes
never happen without an explicit human `mysql <` command.

---

## Rolling back

Hostinger's Git panel has a **Pull** button that runs `git pull` on
demand — useful when the webhook missed a push. To roll back to a
previous commit, SSH in and run:

```bash
cd ~/public_html
git fetch origin
git reset --hard <sha-you-want>
```

The webhook won't fight you — it only pulls on new pushes, not on
manual resets.

---

## Security notes baked into the codebase

- `.htaccess` returns 403 for `/.git/…`, `.env`, `config/`, `includes/`,
  `logs/`, `database/`, and repo metadata (`README.md`, `composer.json`,
  etc.), so even if Hostinger drops those into the docroot they cannot
  be fetched over HTTPS.
- All credentials live in `.env` or in the DB (`site_settings`), never
  in git.
- Uploads use hashed filenames — no user-controlled paths on disk.
