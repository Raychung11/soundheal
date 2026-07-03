<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Gallery';

$photos = db()->query(
    "SELECT g.id, g.image, g.video_url, g.caption, g.category, g.event_id, g.created_at,
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
$appBase = rtrim((string) config('app.url'), '/');
$photosLite = array_map(function ($p) use ($appBase) {
    $videoUrl = (string) ($p['video_url'] ?? '');
    return [
        'id'        => (int) $p['id'],
        'src'       => gallery_thumbnail_url($p),
        'caption'   => (string) ($p['caption']  ?? ''),
        'category'  => (string) ($p['category'] ?? ''),
        'event'     => $p['event_title'] ? (string) $p['event_title'] : '',
        'is_video'  => $videoUrl !== '',
        'embed_url' => $videoUrl !== '' ? gallery_video_embed_url($videoUrl) : '',
        // Canonical share URL for this specific item — social crawlers
        // that hit it will see the per-item OG tags below.
        'share_url' => $appBase . '/public/gallery.php?item=' . (int) $p['id'],
    ];
}, $photos);

// Per-item share metadata. When ?item=<id> is present in the URL we
// override the page-level Open Graph tags so WhatsApp / Facebook
// share previews show THAT photo's thumbnail + caption instead of the
// generic gallery hero. Falls back to the first visible item as the
// featured cover for the gallery landing page.
$shareItemId  = (int) input('item', 0);
$shareItemIdx = -1;
if ($shareItemId > 0) {
    foreach ($photos as $idx => $p) {
        if ((int) $p['id'] === $shareItemId) { $shareItemIdx = $idx; break; }
    }
}
if ($shareItemIdx >= 0) {
    $sp = $photos[$shareItemIdx];
    $spTitle = trim((string) ($sp['caption'] ?? ''));
    if ($spTitle === '' && !empty($sp['event_title'])) {
        $spTitle = (string) $sp['event_title'];
    }
    if ($spTitle === '' && !empty($sp['category'])) {
        $spTitle = (string) $sp['category'];
    }
    if ($spTitle !== '') $pageTitle = $spTitle;
    $descParts = array_filter([$sp['event_title'] ?? '', $sp['category'] ?? '']);
    if ($descParts) $pageDescription = implode(' · ', $descParts) . ' — a moment from our gallery.';
    $pageImage = gallery_thumbnail_url($sp);
    $pageType  = 'article';
} elseif ($photos) {
    // Landing view — first visible photo becomes the share preview.
    $pageImage = gallery_thumbnail_url($photos[0]);
    $pageDescription = 'A quiet look at the sound baths, workshops and gatherings we\'ve held together at ' . brand_name() . '.';
}

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
         x-data="galleryPage(<?= htmlspecialchars(json_encode($photosLite, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>, <?= (int) $shareItemId ?>)">

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
                 class="w-full h-auto object-cover transition duration-500 group-hover:scale-[1.02]"
                 x-show="p.src" onerror="this.style.display='none'">
            <!-- Placeholder for video items with no cover (Vimeo w/o a thumbnail). -->
            <div x-show="!p.src" class="aspect-video bg-navy-950 flex items-center justify-center">
              <span class="font-serif text-4xl text-gold-400/30">◯</span>
            </div>
            <!-- Play overlay for video items. Larger and more prominent
                 than the admin badge because this is the primary call
                 to action on a video tile. -->
            <template x-if="p.is_video">
              <span class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="h-14 w-14 sm:h-16 sm:w-16 rounded-full bg-navy-950/70 border border-gold-500/50 flex items-center justify-center backdrop-blur transition group-hover:bg-navy-950/85 group-hover:scale-110">
                  <svg class="h-6 w-6 text-gold-400 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </span>
              </span>
            </template>
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
        <!-- Photo: zoomed image. Video: platform iframe embed with a
             16:9 aspect and autoplay in the URL. -->
        <template x-if="current && !current.is_video">
          <img :src="current.src" :alt="current.caption"
               class="max-h-[80vh] max-w-full rounded-2xl border border-white/5 shadow-[0_40px_80px_-20px_rgba(0,0,0,0.7)]">
        </template>
        <template x-if="current && current.is_video">
          <div class="w-full max-w-5xl aspect-video rounded-2xl overflow-hidden border border-white/5 shadow-[0_40px_80px_-20px_rgba(0,0,0,0.7)]">
            <iframe :src="current.embed_url" title="Video"
                    allow="autoplay; encrypted-media; picture-in-picture"
                    allowfullscreen loading="lazy"
                    class="w-full h-full"></iframe>
          </div>
        </template>
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

        <!-- Share this specific item. Each option builds a URL that
             points back at /public/gallery.php?item=<id> so WhatsApp /
             Facebook crawlers land on the same page and pick up the
             per-item OG tags. Photo previews on WhatsApp will show the
             thumbnail; video previews will show the YouTube-derived
             cover. -->
        <div class="mt-5 flex items-center justify-center gap-2 text-xs" x-show="current">
          <a x-show="current"
             :href="current ? ('https://wa.me/?text=' + encodeURIComponent((current.caption || 'A moment from our gallery') + ' — ' + current.share_url)) : '#'"
             target="_blank" rel="noopener"
             class="px-3 py-1.5 rounded-full border border-white/10 text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 transition">WhatsApp</a>
          <a x-show="current"
             :href="current ? ('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(current.share_url)) : '#'"
             target="_blank" rel="noopener"
             class="px-3 py-1.5 rounded-full border border-white/10 text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 transition">Facebook</a>
          <button type="button" @click="copyLink()"
                  class="px-3 py-1.5 rounded-full border border-white/10 text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 transition"
                  x-text="copyState || 'Copy link'"></button>
        </div>
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
function galleryPage(all, deepLinkId) {
  return {
    all: all || [],
    activeCat: '',
    lightbox: null,
    copyState: '',
    init() {
      // Deep-link ?item=<id> — auto-open the matching photo in the
      // lightbox so someone arriving via a shared WhatsApp / Facebook
      // link lands directly on the moment that was shared instead of
      // the top of the grid.
      if (!deepLinkId) return;
      const idx = (this.all || []).findIndex(p => Number(p.id) === Number(deepLinkId));
      if (idx >= 0) {
        this.$nextTick(() => this.openAt(idx));
      }
    },
    get visible() {
      if (!this.activeCat) return this.all;
      return this.all.filter(p => p.category === this.activeCat);
    },
    get current() { return this.lightbox === null ? null : this.visible[this.lightbox] || null; },
    openAt(i)  { this.lightbox = i; document.body.style.overflow = 'hidden'; },
    close()    { this.lightbox = null; document.body.style.overflow = ''; this.copyState = ''; },
    prev()     { if (this.lightbox === null) return; this.lightbox = (this.lightbox - 1 + this.visible.length) % this.visible.length; this.copyState = ''; },
    next()     { if (this.lightbox === null) return; this.lightbox = (this.lightbox + 1) % this.visible.length; this.copyState = ''; },
    async copyLink() {
      if (!this.current) return;
      const url = this.current.share_url;
      try { await navigator.clipboard.writeText(url); }
      catch (_) {
        const t = document.createElement('textarea');
        t.value = url; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (_) {}
        t.remove();
      }
      this.copyState = 'Copied ✓';
      setTimeout(() => { this.copyState = ''; }, 1600);
    },
  };
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
