<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Profile';
$user = current_user();
$errors = [];
$saved = false;

if (is_post()) {
    csrf_verify();
    $name  = format_name((string) input('full_name', ''));
    $phone = normalize_phone((string) input('phone', ''));
    $addr1 = trim((string) input('address_line1', ''));
    $addr2 = trim((string) input('address_line2', ''));
    $city  = trim((string) input('city', ''));
    $state = trim((string) input('state', ''));
    $post  = trim((string) input('postcode', ''));
    $cty   = trim((string) input('country', ''));
    $newPass = (string) input('new_password', '');
    $confirm = (string) input('confirm_password', '');

    if ($name === '') { $errors[] = 'Name is required.'; }
    if ($newPass !== '' && strlen($newPass) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
    if ($newPass !== '' && $newPass !== $confirm) { $errors[] = 'Passwords do not match.'; }

    if (!$errors) {
        $sql = 'UPDATE users SET
                    full_name = :n,
                    phone = :p,
                    address_line1 = :a1,
                    address_line2 = :a2,
                    city = :ct,
                    state = :st,
                    postcode = :pc,
                    country = :country';
        $params = [
            ':n'  => $name,
            ':p'  => $phone,
            ':a1' => $addr1 ?: null,
            ':a2' => $addr2 ?: null,
            ':ct' => $city  ?: null,
            ':st' => $state ?: null,
            ':pc' => $post  ?: null,
            ':country' => $cty ?: null,
            ':id' => $user['id'],
        ];
        if ($newPass !== '') {
            $sql .= ', password_hash = :h';
            $params[':h'] = password_hash($newPass, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = :id';
        db()->prepare($sql)->execute($params);

        $_SESSION['user']['full_name'] = $name;
        audit_log('profile.update', 'users', $user['id'], ['changed_password' => $newPass !== '']);
        $saved = true;
        $user = current_user();
    }
}

$row = db()->prepare(
    'SELECT email, phone, address_line1, address_line2, city, state, postcode, country, created_at
       FROM users WHERE id = :id'
);
$row->execute([':id' => $user['id']]);
$details = $row->fetch() ?: [];

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-3xl mx-auto px-6 py-16" x-data="{
    showNew: false,
    showConfirm: false,
    pwOk: true, pwMatch: true,
    pw: '', confirm: '',
    check() { this.pwOk = this.pw === '' || this.pw.length >= 8; this.pwMatch = this.confirm === '' || this.pw === this.confirm; }
  }">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Profile</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4"><?= e($user['full_name']) ?></h1>
  <p class="text-beige-100/55 text-sm mt-2">Member since <?= e(format_datetime($details['created_at'] ?? null, 'd M Y')) ?></p>

  <?php if ($saved): ?>
    <div class="mt-6 border border-gold-500/30 rounded-2xl px-5 py-3 bg-gold-500/5 text-gold-400 text-sm">Saved.</div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
    <p class="mt-3 text-red-300/80 text-sm"><?= e($err) ?></p>
  <?php endforeach; ?>

  <form method="post" class="mt-10 space-y-10">
    <?= csrf_field() ?>

    <!-- Personal -->
    <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
      <h2 class="font-serif text-2xl text-gold-400">Personal</h2>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Full name</span>
        <input name="full_name" required value="<?= e($user['full_name']) ?>"
               x-on:blur="$el.value = $el.value.trim().toLowerCase().replace(/\s+/g, ' ').replace(/(^|[\s'\-])\p{L}/gu, c => c.toUpperCase())"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"
               style="text-transform: capitalize;">
        <span class="text-[11px] text-beige-100/40 mt-1 block">We auto-capitalise the first letter of each word when you leave the field.</span>
      </label>

      <div class="grid sm:grid-cols-2 gap-5">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
          <input value="<?= e($details['email'] ?? '') ?>" disabled
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-beige-100/60">
          <span class="text-[11px] text-beige-100/40 mt-1 block">To change your email, please contact <a class="text-gold-400/80 hover:text-gold-300" href="mailto:<?= e((string) setting('company_email', 'hello@jaemiesoundbath.com')) ?>">support</a>.</span>
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Phone</span>
          <div class="mt-2 flex items-stretch rounded-2xl bg-navy-950 border border-white/5 focus-within:border-gold-500/50 transition overflow-hidden">
            <span class="px-3 flex items-center text-gold-400 text-sm bg-navy-900/60 border-r border-white/5">+60</span>
            <input name="phone" type="tel" inputmode="tel" autocomplete="tel-national"
                   value="<?= e(preg_replace('/^\+60/', '', (string) ($details['phone'] ?? ''))) ?>"
                   placeholder="12 345 6789"
                   x-on:input="$el.value = $el.value.replace(/[^\d+\s\-]/g, '')"
                   x-on:blur="$el.value = $el.value.replace(/[^\d+]/g, '').replace(/^\+?60/, '').replace(/^0/, '')"
                   class="flex-1 bg-transparent px-4 py-3 focus:outline-none">
          </div>
          <span class="text-[11px] text-beige-100/40 mt-1 block">Type your number without the country code — we add <strong class="text-beige-100/65">+60</strong> automatically. Outside Malaysia? Start with <code class="text-gold-400/70">+</code> and the country code.</span>
        </label>
      </div>
    </section>

    <!-- Address -->
    <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
      <h2 class="font-serif text-2xl text-gold-400">Address</h2>
      <p class="text-xs text-beige-100/45 -mt-3">Used on invoices and receipts. All fields are optional.</p>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Address line 1</span>
        <input name="address_line1" value="<?= e($details['address_line1'] ?? '') ?>"
               placeholder="No. 12, Jalan Damai"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Address line 2 (optional)</span>
        <input name="address_line2" value="<?= e($details['address_line2'] ?? '') ?>"
               placeholder="Apartment / Unit / Floor"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>

      <div class="grid sm:grid-cols-2 gap-5">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">City</span>
          <input name="city" value="<?= e($details['city'] ?? '') ?>"
                 placeholder="Kuala Lumpur"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">State / Region</span>
          <input name="state" value="<?= e($details['state'] ?? '') ?>"
                 placeholder="Wilayah Persekutuan"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Postcode</span>
          <input name="postcode" value="<?= e($details['postcode'] ?? '') ?>"
                 placeholder="59100"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Country</span>
          <input name="country" value="<?= e($details['country'] ?? 'Malaysia') ?>"
                 placeholder="Malaysia"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
      </div>
    </section>

    <!-- Password -->
    <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
      <h2 class="font-serif text-2xl text-gold-400">Password</h2>
      <p class="text-xs text-beige-100/45 -mt-3">Leave blank to keep your current password.</p>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">New password</span>
        <div class="relative mt-2">
          <input name="new_password" :type="showNew ? 'text' : 'password'" minlength="8" autocomplete="new-password"
                 x-model="pw" @input="check()"
                 class="w-full rounded-2xl bg-navy-950 border px-4 py-3 pr-12 focus:outline-none transition"
                 :class="pwOk ? 'border-white/5 focus:border-gold-500/50' : 'border-red-400/50'">
          <button type="button" @click="showNew = !showNew"
                  class="absolute inset-y-0 right-0 px-4 text-beige-100/45 hover:text-gold-400 text-xs uppercase tracking-widest"
                  :aria-label="showNew ? 'Hide password' : 'Show password'"
                  x-text="showNew ? 'Hide' : 'Show'"></button>
        </div>
        <span x-show="!pwOk" class="text-xs text-red-300/85 mt-1 block">Must be at least 8 characters.</span>
      </label>

      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Confirm new password</span>
        <div class="relative mt-2">
          <input name="confirm_password" :type="showConfirm ? 'text' : 'password'" minlength="8" autocomplete="new-password"
                 x-model="confirm" @input="check()"
                 class="w-full rounded-2xl bg-navy-950 border px-4 py-3 pr-12 focus:outline-none transition"
                 :class="pwMatch ? 'border-white/5 focus:border-gold-500/50' : 'border-red-400/50'">
          <button type="button" @click="showConfirm = !showConfirm"
                  class="absolute inset-y-0 right-0 px-4 text-beige-100/45 hover:text-gold-400 text-xs uppercase tracking-widest"
                  :aria-label="showConfirm ? 'Hide password' : 'Show password'"
                  x-text="showConfirm ? 'Hide' : 'Show'"></button>
        </div>
        <span x-show="!pwMatch" class="text-xs text-red-300/85 mt-1 block">Passwords don't match.</span>
        <span x-show="pw !== '' && pwMatch && pwOk" class="text-xs text-gold-400/80 mt-1 block">Looks good.</span>
      </label>
    </section>

    <div class="flex gap-3">
      <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Save changes</button>
      <a href="<?= url('/member/dashboard.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Back to sanctuary</a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
