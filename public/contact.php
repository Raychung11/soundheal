<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Contact';
$errors = [];
$ok = false;

if (is_post()) {
    csrf_verify();
    $type = input('type', 'general');

    $name    = input('name', '');
    $email   = filter_var(input('email', ''), FILTER_VALIDATE_EMAIL) ?: '';
    $phone   = input('phone');
    $message = input('message', '');

    if ($name === '' || $email === '' || $message === '') {
        $errors[] = 'Please share your name, email and a short message.';
    }

    if (!$errors) {
        if ($type === 'corporate') {
            $stmt = db()->prepare(
                'INSERT INTO corporate_inquiries
                    (company_name, contact_name, contact_email, contact_phone, team_size, interest, message)
                 VALUES (:co, :n, :e, :p, :ts, :i, :m)'
            );
            $stmt->execute([
                ':co' => input('company', $name),
                ':n'  => $name,
                ':e'  => $email,
                ':p'  => $phone,
                ':ts' => input('team_size'),
                ':i'  => input('interest'),
                ':m'  => $message,
            ]);
            audit_log('contact.corporate', 'corporate_inquiries', (int) db()->lastInsertId());
        } else {
            $stmt = db()->prepare(
                'INSERT INTO contact_leads (name, email, phone, subject, message)
                 VALUES (:n, :e, :p, :s, :m)'
            );
            $stmt->execute([
                ':n' => $name,
                ':e' => $email,
                ':p' => $phone,
                ':s' => input('subject'),
                ':m' => $message,
            ]);
            audit_log('contact.general', 'contact_leads', (int) db()->lastInsertId());
        }
        $ok = true;
    }
}
require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-3xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Connect</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4">Whisper to us</h1>
  <p class="mt-6 text-beige-100/70 leading-relaxed">For private sessions, partnership or simply to say hello — we read every note.</p>

  <?php if ($ok): ?>
    <div class="mt-10 border border-gold-500/30 rounded-2xl p-6 bg-navy-900/40">
      <p class="font-serif text-2xl text-gold-400">Received with care.</p>
      <p class="text-beige-100/70 mt-2">We’ll be in touch within two working days.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <p class="mt-4 text-red-300/80"><?= e($err) ?></p>
  <?php endforeach; ?>

  <form method="post" class="mt-10 space-y-6">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="general">
    <div class="grid md:grid-cols-2 gap-6">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Name</span>
        <input name="name" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
        <input name="email" type="email" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Phone (optional)</span>
        <input name="phone" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Subject</span>
        <input name="subject" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Message</span>
      <textarea name="message" rows="5" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"></textarea>
    </label>
    <button class="px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Send with care</button>
  </form>
</section>

<section id="corporate" class="border-t border-white/5">
  <div class="max-w-3xl mx-auto px-6 py-24">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Corporate wellness</p>
    <h2 class="font-serif text-4xl text-beige-100 mt-4">Sanctuary, for your team</h2>
    <p class="mt-6 text-beige-100/70 leading-relaxed">On-site sound healing, breathwork retreats and quarterly reset rituals — designed for modern teams who care about their people.</p>

    <form method="post" class="mt-10 space-y-6">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="corporate">
      <div class="grid md:grid-cols-2 gap-6">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Company</span>
          <input name="company" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Your name</span>
          <input name="name" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
          <input name="email" type="email" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Team size</span>
          <input name="team_size" placeholder="e.g. 25–50" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
      </div>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">What kind of experience are you imagining?</span>
        <textarea name="message" rows="4" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"></textarea>
      </label>
      <button class="px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Request a proposal</button>
    </form>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
