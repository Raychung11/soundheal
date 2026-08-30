<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug = trim((string) input('slug', ''));
$post = $slug !== '' ? blog_get_by_slug($slug) : null;

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="max-w-3xl mx-auto px-6 py-24 text-center">
            <h1 class="font-serif text-4xl text-beige-100">This entry is not here</h1>
            <p class="mt-4 text-beige-100/70">It may still be a draft, or has been archived. Browse the <a href="' . url('/public/blog.php') . '" class="text-gold-400 hover:text-gold-300">journal</a>.</p>
          </div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Set per-post SEO before the header renders — this is what makes
// social shares of a single post show its own cover + excerpt instead
// of the site-wide default.
$pageTitle       = (string) $post['title'];
$pageDescription = trim((string) ($post['excerpt'] ?? '')) !== ''
    ? (string) $post['excerpt']
    : 'A reflection from the sanctuary.';
$pageType        = 'article';
if (!empty($post['cover_image'])) {
    $pageImage = (string) $post['cover_image'];
}

// Render body before header so the "does the page need Instagram's
// embed.js?" flag is set correctly for the footer include.
$bodyHtml = blog_render_body((string) ($post['body'] ?? ''));

// Canonical share URL — absolute so the string still points home
// when pasted into WhatsApp, LinkedIn, etc.
$shareUrl     = rtrim((string) config('app.url'), '/') . '/public/blog_post.php?slug=' . urlencode((string) $post['slug']);
$shareTitle   = (string) $post['title'];
$shareTagline = trim((string) ($post['excerpt'] ?? '')) !== ''
    ? (string) $post['excerpt']
    : 'A reflection from ' . brand_name() . '.';
// The WhatsApp text is what appears next to the link preview in the
// message field before the user hits send. Facebook / X pull their own
// preview from the OG tags but we still hand a title through where the
// platform supports it.
$shareUrlEnc   = urlencode($shareUrl);
$shareTitleEnc = urlencode($shareTitle);
$shareWaText   = urlencode($shareTitle . ' — ' . $shareTagline);

require __DIR__ . '/../includes/header.php';
?>

<article class="max-w-3xl mx-auto px-6 pt-16 pb-20">
  <p class="text-xs text-beige-100/50">
    <a href="<?= url('/public/blog.php') ?>" class="hover:text-gold-400">Journal</a>
  </p>

  <p class="mt-6 text-[11px] uppercase tracking-[0.35em] text-gold-400/70">
    <?= e(format_datetime((string)$post['published_at'], 'd M Y')) ?>
    <?php if (!empty($post['author_name'])): ?>
      · by <?= e((string)$post['author_name']) ?>
    <?php endif; ?>
  </p>

  <h1 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4 leading-tight"><?= e((string)$post['title']) ?></h1>
  <?php if (!empty($post['subtitle'])): ?>
    <p class="text-lg text-beige-100/75 mt-3"><?= e((string)$post['subtitle']) ?></p>
  <?php endif; ?>

  <?php if (!empty($post['cover_image'])): ?>
    <figure class="mt-10 rounded-3xl overflow-hidden border border-white/5">
      <img src="<?= e(media_src((string)$post['cover_image'])) ?>" alt="" class="w-full h-auto">
    </figure>
  <?php endif; ?>

  <div class="mt-10 text-beige-100/85 text-[16px] leading-[1.85] blog-body">
    <?= $bodyHtml ?>
  </div>

  <?php
    $tagList = array_values(array_filter(array_map('trim', explode(',', (string) ($post['tags'] ?? '')))));
  ?>
  <?php if ($tagList): ?>
    <div class="mt-12 flex flex-wrap gap-2">
      <?php foreach ($tagList as $t): ?>
        <a href="<?= url('/public/blog.php?tag=' . urlencode($t)) ?>"
           class="px-3 py-1.5 rounded-full text-xs border border-white/10 text-beige-100/65 hover:border-gold-500/40 hover:text-gold-400 transition">
          #<?= e($t) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Share row: platform pills for anyone, plus a "Share on device"
       pill that only reveals on browsers where navigator.share works
       (iOS / Android / recent Chrome). The individual pills are the
       fallback path everyone always sees. -->
  <div class="mt-12 pt-8 border-t border-white/5" x-data="{
        copied: false,
        canShareNative: false,
        init() { this.canShareNative = !!(navigator.share); },
        async copy() {
          const url = <?= htmlspecialchars(json_encode($shareUrl), ENT_QUOTES, 'UTF-8') ?>;
          try { await navigator.clipboard.writeText(url); }
          catch (e) {
            const t = document.createElement('textarea');
            t.value = url; document.body.appendChild(t); t.select();
            document.execCommand('copy'); t.remove();
          }
          this.copied = true;
          setTimeout(() => this.copied = false, 1600);
        },
        async native() {
          if (!navigator.share) return;
          try {
            await navigator.share({
              title: <?= htmlspecialchars(json_encode($shareTitle), ENT_QUOTES, 'UTF-8') ?>,
              text:  <?= htmlspecialchars(json_encode($shareTagline), ENT_QUOTES, 'UTF-8') ?>,
              url:   <?= htmlspecialchars(json_encode($shareUrl), ENT_QUOTES, 'UTF-8') ?>,
            });
          } catch (e) { /* user cancelled — that's fine */ }
        }
      }">
    <p class="text-[10px] uppercase tracking-[0.35em] text-beige-100/50">Share this reflection</p>
    <div class="mt-4 flex flex-wrap items-center gap-2">
      <a href="https://wa.me/?text=<?= $shareWaText ?>%20<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 text-xs text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M20.52 3.48A11.87 11.87 0 0 0 12.02 0C5.48 0 .17 5.3.17 11.83c0 2.08.55 4.11 1.6 5.9L0 24l6.44-1.7a11.86 11.86 0 0 0 5.58 1.42h.01c6.53 0 11.84-5.3 11.84-11.83a11.76 11.76 0 0 0-3.35-8.41Zm-8.5 18.2h-.01a9.86 9.86 0 0 1-5.02-1.37l-.36-.21-3.82 1 1.02-3.72-.24-.38a9.83 9.83 0 0 1-1.5-5.19c0-5.44 4.43-9.86 9.86-9.86 2.63 0 5.1 1.03 6.96 2.89a9.78 9.78 0 0 1 2.88 6.98c0 5.43-4.43 9.86-9.77 9.86Zm5.42-7.38c-.3-.15-1.75-.86-2.03-.96-.27-.1-.47-.15-.66.15-.2.3-.76.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.66-1.6-.91-2.19-.24-.57-.48-.5-.66-.51-.17-.01-.37-.01-.57-.01-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.23 1.35.2 1.86.12.57-.08 1.75-.71 2-1.4.24-.7.24-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z"/>
        </svg>
        WhatsApp
      </a>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 text-xs text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.13 8.44 9.88v-6.99H7.9V12h2.54V9.8c0-2.51 1.49-3.89 3.77-3.89 1.09 0 2.24.19 2.24.19v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99C18.34 21.13 22 16.99 22 12Z"/>
        </svg>
        Facebook
      </a>
      <a href="https://twitter.com/intent/tweet?url=<?= $shareUrlEnc ?>&text=<?= $shareTitleEnc ?>" target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 text-xs text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231L18.244 2.25Zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77Z"/>
        </svg>
        X / Twitter
      </a>
      <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
         class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 text-xs text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67h-3.56V9h3.42v1.56h.05a3.75 3.75 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.23 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.46c.98 0 1.77-.77 1.77-1.72V1.72C24 .77 23.21 0 22.23 0Z"/>
        </svg>
        LinkedIn
      </a>
      <a href="mailto:?subject=<?= $shareTitleEnc ?>&body=<?= urlencode($shareTagline . "\n\n" . $shareUrl) ?>"
         class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 text-xs text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>
        </svg>
        Email
      </a>
      <button type="button" @click="copy()"
              class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-white/10 text-xs text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
        </svg>
        <span x-show="!copied">Copy link</span>
        <span x-show="copied" x-cloak class="text-gold-400">Copied ✓</span>
      </button>
      <button type="button" x-show="canShareNative" x-cloak @click="native()"
              class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-gold-500/40 bg-gold-500/10 text-xs text-gold-400 hover:bg-gold-500/20 transition">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"/><path d="M16 6 12 2 8 6"/><path d="M12 2v14"/>
        </svg>
        Share…
      </button>
    </div>
  </div>

  <div class="mt-10 pt-8 border-t border-white/5 flex items-center justify-between text-sm">
    <a href="<?= url('/public/blog.php') ?>" class="text-beige-100/60 hover:text-gold-400">← All entries</a>
    <a href="<?= url('/public/contact.php') ?>" class="text-gold-400 hover:text-gold-300">Reflect back →</a>
  </div>
</article>

<style>
  /* Give render_rich_text's paragraph output some room inside the post body. */
  .blog-body .blog-prose > p { margin-top: 1.1em; }
  .blog-body .blog-prose > p:first-child { margin-top: 0; }
  .blog-body .blog-prose > ul { margin-top: 1em; padding-left: 1.2em; list-style: disc; }
  .blog-body .blog-prose > ul li { margin: .3em 0; }
</style>

<?php if (function_exists('blog_page_needs_instagram_script') && blog_page_needs_instagram_script()): ?>
  <script async src="https://www.instagram.com/embed.js"></script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
