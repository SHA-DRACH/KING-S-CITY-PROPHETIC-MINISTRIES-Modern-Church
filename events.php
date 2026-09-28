<?php
require __DIR__ . '/includes/bootstrap.php';

$tab = ($_GET['tab'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$categories = Event::categories();
$categoryId = query_int('category') ?: null;

if ($tab === 'upcoming') {
    $p = paginate(Event::countUpcoming($categoryId), 9);
    $events = Event::upcoming($p['per_page'], $p['offset'], $categoryId);
} else {
    $p = paginate(Event::countPast(), 9);
    $events = Event::past($p['per_page'], $p['offset']);
}

// Event structured data for upcoming events
$schema = [];
foreach ($tab === 'upcoming' ? $events : [] as $ev) {
    $schema[] = [
        '@context' => 'https://schema.org', '@type' => 'Event', 'name' => $ev['title'],
        'startDate' => $ev['event_date'] . ($ev['start_time'] ? 'T' . substr($ev['start_time'], 0, 5) : ''),
        'endDate' => ($ev['end_date'] ?: $ev['event_date']) . ($ev['end_time'] ? 'T' . substr($ev['end_time'], 0, 5) : ''),
        'eventStatus' => $ev['status'] === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => ['@type' => 'Place', 'name' => $ev['location'] ?: setting('church_name'), 'address' => church_address_line()],
        'image' => media_url($ev['image']), 'description' => excerpt($ev['description'], 200),
        'organizer' => ['@type' => 'Organization', 'name' => $ev['organizer'] ?: setting('church_name'), 'url' => url()],
    ];
}

$page = ['title' => 'Events', 'nav' => 'events', 'description' => 'Upcoming worship services, conferences, crusades and programs at ' . setting('church_name') . '.', 'schema' => $schema];
require __DIR__ . '/includes/header.php';
echo page_banner('Events', 'Join us for life-changing moments.', 'assets/images/placeholders/hero-poster.svg', [], 'events');
?>
<section class="section">
  <div class="container">
    <div class="events-toolbar" data-aos="fade-up">
      <div class="seg-tabs" role="tablist" aria-label="Event timeframe">
        <a role="tab" class="<?= $tab === 'upcoming' ? 'active' : '' ?>" aria-selected="<?= $tab === 'upcoming' ? 'true' : 'false' ?>" href="?tab=upcoming">Upcoming</a>
        <a role="tab" class="<?= $tab === 'past' ? 'active' : '' ?>" aria-selected="<?= $tab === 'past' ? 'true' : 'false' ?>" href="?tab=past">Past</a>
      </div>
      <?php if ($tab === 'upcoming'): ?>
        <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="upcoming">
          <label class="visually-hidden" for="evCat">Category</label>
          <select id="evCat" name="category" class="form-select" onchange="this.form.submit()"><option value="">All Categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select><noscript><button class="btn btn-navy">Go</button></noscript></form>
      <?php endif; ?>
    </div>

    <?php if (!$events): echo empty_state('fa-calendar', $tab === 'upcoming' ? 'No upcoming events' : 'No past events', 'Please check back soon.');
    else: ?>
      <div class="event-list">
        <?php foreach ($events as $i => $ev): $link = url('event-details.php?slug=' . rawurlencode($ev['slug'])); ?>
          <article class="event-row<?= $ev['status'] === 'cancelled' ? ' is-cancelled' : '' ?>" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 80 ?>">
            <?= date_chip($ev['event_date']) ?>
            <a class="er-media" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(media_url($ev['image'])) ?>" alt="" loading="lazy" width="220" height="150"></a>
            <div class="er-body">
              <div class="d-flex flex-wrap gap-2 mb-1"><?php if ($ev['category_name']): ?><span class="tag"><?= e($ev['category_name']) ?></span><?php endif; ?><?php if ($ev['status'] === 'cancelled'): ?><span class="badge text-bg-danger">Cancelled</span><?php endif; ?></div>
              <h2><a href="<?= e($link) ?>"><?= e($ev['title']) ?></a></h2>
              <ul class="meta"><li><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= e(format_date($ev['event_date'], 'l, F j, Y')) ?><?= $ev['start_time'] ? ' · ' . e(time_range($ev['start_time'], $ev['end_time'])) : '' ?></li>
                <?php if ($ev['location']): ?><li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= e($ev['location']) ?></li><?php endif; ?></ul>
              <p><?= e(excerpt($ev['description'], 160)) ?></p>
            </div>
            <a class="btn btn-outline-navy er-btn" href="<?= e($link) ?>">Details <span class="visually-hidden">for <?= e($ev['title']) ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
          </article>
        <?php endforeach; ?>
      </div>
      <?= pagination_links($p, 'mt-5 d-flex justify-content-center') ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
