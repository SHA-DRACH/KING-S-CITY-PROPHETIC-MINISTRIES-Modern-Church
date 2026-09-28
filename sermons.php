<?php
require __DIR__ . '/includes/bootstrap.php';

$categories = Sermon::categories();
$filters = ['all' => 'All', 'video' => 'Video', 'audio' => 'Audio', 'featured' => 'Featured'] + array_column($categories, 'name', 'slug');
$filter = (string) ($_GET['filter'] ?? 'all');
if (!isset($filters[$filter])) {
    $filter = 'all';
}
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$p = paginate(Sermon::countPublished($filter, $search), 9);
$sermons = Sermon::published($filter, $search, $p['per_page'], $p['offset']);

// "Load More" requests just the cards
if (!empty($_GET['partial'])) {
    foreach ($sermons as $s) {
        echo '<div class="col-md-6 col-lg-4">' . sermon_card($s) . '</div>';
    }
    exit;
}

$page = ['title' => 'Sermons', 'nav' => 'sermons', 'description' => 'Watch, listen to and download sermons by Prophet Mark Dorbor and guest ministers at ' . setting('church_name') . '.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Sermons', 'Be blessed by the Word of God.', 'assets/images/placeholders/bible.svg', [], 'sermons');
?>
<section class="section">
  <div class="container">
    <form class="sermon-toolbar" method="get" role="search" data-aos="fade-up">
      <div class="filter-pills" role="group" aria-label="Filter sermons">
        <?php foreach ($filters as $key => $label): ?>
          <a class="pill<?= $filter === $key ? ' active' : '' ?>" href="?<?= e(http_build_query(array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $search ?: null]))) ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="search-box">
        <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
        <label class="visually-hidden" for="sermonSearch">Search sermons</label>
        <input type="search" id="sermonSearch" name="q" value="<?= e($search) ?>" placeholder="Search title, scripture, speaker…">
        <button aria-label="Search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
      </div>
    </form>

    <?php if ($search): ?><p class="text-muted mb-4"><?= (int) $p['total'] ?> result<?= $p['total'] === 1 ? '' : 's' ?> for “<?= e($search) ?>” · <a href="?<?= e(http_build_query(array_filter(['filter' => $filter === 'all' ? null : $filter]))) ?>">Clear search</a></p><?php endif; ?>

    <?php if (!$sermons): echo empty_state('fa-book-bible', 'No sermons found', 'Try a different filter or search term.');
    else: ?>
      <div class="row g-4" id="sermonGrid">
        <?php foreach ($sermons as $i => $s): ?><div class="col-md-6 col-lg-4"><?= sermon_card($s, ($i % 3) * 100) ?></div><?php endforeach; ?>
      </div>
      <?php if ($p['page'] < $p['pages']): ?>
        <div class="text-center mt-5">
          <button class="btn btn-navy btn-lg" data-load-more="#sermonGrid" data-next-page="<?= $p['page'] + 1 ?>" data-pages="<?= $p['pages'] ?>">Load More Sermons</button>
          <noscript><?= pagination_links($p, 'mt-3 d-flex justify-content-center') ?></noscript>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
