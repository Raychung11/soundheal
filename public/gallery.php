<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Gallery';

$photos = db()->query(
    "SELECT g.id, g.image, g.caption, g.category, g.event_id, g.created_at,
            e.title AS event_title, e.slug AS event_slug
       FROM gallery_photos g
       LEFT JOIN events e ON e.id = g.event_id
      WHERE g.status = 'visible'
      ORDER BY g.sort_order ASC, g.id DESC"
)->fetchAll();

// Distinct categories → filter chips at the top.
$categories = array_values(array_unique(array_filter(array_map(
    fn($p) => trim((string) ($p['category'] ?? '')), $photos
))));
sort($categories);

// Alpine-ready photo list. We hand the client only the fields it
// needs for the grid + lightbox so nothing sensitive leaks.
$photosLite = array_map(function ($p) {
    return [
        'id'       => (int) $p['id'],
        'src'      => (string) (str_starts_with((string) $p['image'], '/') ? url($p['image']) : $p['image']),
        'caption'  => (string) ($p['caption']  ?? ''),
        'category' => (string) ($p['category'] ?? ''),
        'event'    => $p['event_title'] ? (string) $p['event_title'] : '',
    ];
}, $photos);

require __DIR__ . '/../includes/header.php';
?>

<section class="relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 to-transparent"></div>
  <div class="relative max-w-6xl mx-auto px-6 py-20 md:py-28">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Gallery</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-tight">Moments held in sound</h1>
    <p class="mt-6 max-w-2xl text-beige-100/70 leading-[1.85] font-light">A quiet look at the sessions, workshops and gatherings we've held together. Tap any image to see it up close.</p>
  </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-24"
         x-data="galleryPage(<?= htmlspecialchars(json_encode($photosLite, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>)">

  <?php if ($photos): ?>
    <!-- Category filter chips. 'All' resets. -->
    <?php if (count($categories) > 0): ?>
      <div class="flex flex-wrap gap-2">
        <button type="button" @click="activeCat = ''"
                :class="!activeCat ? 'border-gold-500/50 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400'"
                class="text-xs uppercase tracking-[0.2em] px-4 py-2 rounded-full border transition">All</button>
        <?php foreach ($categories as $cat): ?>
          <button type="button" @click="activeCat = <?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>"
                  :class="activeCat === <?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?> ? 'border-gold-500/50 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400'"
                  class="text-xs uppercase tracking-[0.2em] px-4 py-2 rounded-full border transition capitalize"><?= e($cat) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Masonry-ish grid via CSS columns — each photo drops into the
         shortest column, so the layout stays visually balanced no
         matter what mix of portraits and landscapes admins upload. -->
    <div class="mt-8 columns-1 sm:columns-2 md:columns-3 lg:columns-4 gap-4 space-y-4">
      <template x-for="(p, idx) in visible" :key="p.id">
        <button type="button" @click="openAt(idx)"
                class="block w-full break-inside-avoid group rounded-2xl overflow-hidden border border-white/5 bg-navy-900/40 hover:border-gold-500/40 transition">
          <div class="relative">
            <img :src="p.src" :alt="p.caption" loading="lazy"
                 class="w-full h-auto object-cover transition duration-500 group-hover:scale-[1.02]">
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950/85 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition"></div>
            <div class="absolute bottom-3 left-3 right-3 opacity-0 group-hover:opacity-100 transition">
              <p x-show="p.caption" x-text="p.caption" class="text-xs text-beige-100 drop-shadow"></p>
              <p x-show="p.category || p.event" class="text-[10px] uppercase tracking-widest text-gold-400/85 mt-1">
                <span x-show="p.category" x-text="p.category"></span>
                <template x-if="p.category && p.event"><span class="text-beige-100/40"> · </span></template>
                <span x-show="p.event" x-text="p.event"></span>
              </p>
            </div>
          </div>
        </button>
      </template>
    </div>

    <p x-show="!visible.length" x-cloak class="mt-16 text-center text-beige-100/55 italic">No photos in this category yet.</p>

    <!-- Lightbox. Arrow keys / swipe to move, Escape to close. -->
    <div x-show="lightbox !== null" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 bg-navy-950/95 backdrop-blur flex flex-col"
         @keydown.escape.window="close()"
         @keydown.arrow-left.window="prev()"
         @keydown.arrow-right.window="next()">
      <div class="flex items-center justify-between p-4 sm:p-6">
        <p class="text-xs uppercase tracking-widest text-beige-100/50">
          <span x-text="(lightbox ?? 0) + 1"></span> / <span x-text="visible.length"></span>
        </p>
        <button type="button" @click="close()" aria-label="Close"
                class="p-2 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 transition">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
      <div class="flex-1 flex items-center justify-center px-4 sm:px-8 pb-6 relative">
        <button type="button" @click="prev()" aria-label="Previous"
                class="hidden sm:flex absolute left-4 top-1/2 -translate-y-1/2 h-11 w-11 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 items-center justify-center transition">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m15 6-6 6 6 6"/></svg>
        </button>
        <img :src="current?.src" :alt="current?.caption"
             class="max-h-[80vh] max-w-full rounded-2xl border border-white/5 shadow-[0_40px_80px_-20px_rgba(0,0,0,0.7)]">
        <button type="button" @click="next()" aria-label="Next"
                class="hidden sm:flex absolute right-4 top-1/2 -translate-y-1/2 h-11 w-11 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 items-center justify-center transition">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 6 6 6-6 6"/></svg>
        </button>
      </div>
      <div class="px-6 pb-8 text-center">
        <p x-show="current?.caption" x-text="current?.caption" class="text-beige-100"></p>
        <p x-show="current?.category || current?.event"
           class="mt-1 text-[10px] uppercase tracking-widest text-gold-400/85">
          <span x-show="current?.category" x-text="current?.category"></span>
          <template x-if="current?.category && current?.event"><span class="text-beige-100/40"> · </span></template>
          <span x-show="current?.event" x-text="current?.event"></span>
        </p>
      </div>
    </div>
  <?php else: ?>
    <div class="border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="font-serif text-2xl text-beige-100/80">The gallery is quiet for now.</p>
      <p class="mt-3 text-beige-100/60">Photos from our next gathering will appear here.</p>
    </div>
  <?php endif; ?>
</section>

<script>
function galleryPage(all) {
  return {
    all: all || [],
    activeCat: '',
    lightbox: null,
    get visible() {
      if (!this.activeCat) return this.all;
      return this.all.filter(p => p.category === this.activeCat);
    },
    get current() { return this.lightbox === null ? null : this.visible[this.lightbox] || null; },
    openAt(i)  { this.lightbox = i; document.body.style.overflow = 'hidden'; },
    close()    { this.lightbox = null; document.body.style.overflow = ''; },
    prev()     { if (this.lightbox === null) return; this.lightbox = (this.lightbox - 1 + this.visible.length) % this.visible.length; },
    next()     { if (this.lightbox === null) return; this.lightbox = (this.lightbox + 1) % this.visible.length; },
  };
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
