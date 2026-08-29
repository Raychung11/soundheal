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

  <div class="mt-12 pt-8 border-t border-white/5 flex items-center justify-between text-sm">
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
