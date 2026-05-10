</main>
<footer class="border-t border-white/5 bg-navy-950 mt-24">
  <div class="max-w-6xl mx-auto px-4 py-12 grid md:grid-cols-4 gap-10">
    <div>
      <div class="font-serif text-2xl text-gold-400"><?= e(config('app.name')) ?></div>
      <p class="text-sm text-beige-100/60 mt-3 leading-relaxed"><?= e(config('app.tagline')) ?>. A sanctuary for sound, breath and stillness.</p>
    </div>
    <div>
      <h4 class="text-sm uppercase tracking-[0.2em] text-gold-400/80">Experience</h4>
      <ul class="mt-3 space-y-2 text-sm">
        <li><a class="hover:text-gold-400" href="<?= url('/public/experiences.php') ?>">Experiences</a></li>
        <li><a class="hover:text-gold-400" href="<?= url('/public/events.php') ?>">Upcoming sessions</a></li>
        <li><a class="hover:text-gold-400" href="<?= url('/public/membership.php') ?>">Membership</a></li>
      </ul>
    </div>
    <div>
      <h4 class="text-sm uppercase tracking-[0.2em] text-gold-400/80">Connect</h4>
      <ul class="mt-3 space-y-2 text-sm">
        <li><a class="hover:text-gold-400" href="<?= url('/public/contact.php') ?>">Contact</a></li>
        <li><a class="hover:text-gold-400" href="<?= url('/public/contact.php#corporate') ?>">Corporate wellness</a></li>
      </ul>
    </div>
    <div>
      <h4 class="text-sm uppercase tracking-[0.2em] text-gold-400/80">Care</h4>
      <p class="text-xs text-beige-100/50 mt-3 leading-relaxed">Our offerings are not medical advice. Please consult qualified professionals for medical or mental-health concerns.</p>
    </div>
  </div>
  <div class="border-t border-white/5">
    <div class="max-w-6xl mx-auto px-4 py-6 text-xs text-beige-100/40 flex flex-col sm:flex-row gap-2 justify-between">
      <span>© <?= date('Y') ?> <?= e(config('app.name')) ?>. All rights reserved.</span>
      <span>Built with care for stillness.</span>
    </div>
  </div>
</footer>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
