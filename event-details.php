<?php
require __DIR__ . '/includes/bootstrap.php';

$ev = Event::bySlug((string) ($_GET['slug'] ?? '')) ?? abort(404, 'This event could not be found.');
$isPast = ($ev['end_date'] ?: $ev['event_date']) < date('Y-m-d');
$more = array_filter(Event::upcoming(4), fn($e) => (int) $e['id'] !== (int) $ev['id']);
$start = $ev['event_date'] . ' ' . ($ev['start_time'] ?: '00:00:00');
$end = ($ev['end_date'] ?: $ev['event_date']) . ' ' . ($ev['end_time'] ?: ($ev['start_time'] ?: '23:59:00'));
$gcal = 'https://calendar.google.com/calendar/render?' . http_build_query([
    'action' => 'TEMPLATE', 'text' => $ev['title'],
    'dates' => date('Ymd\THis', strtotime($start)) . '/' . date('Ymd\THis', strtotime($end)),
    'details' => excerpt($ev['description'], 300), 'location' => $ev['location'] ?: church_address_line(), 'ctz' => config('app.timezone'),
]);

$schema = [[
    '@context' => 'https://schema.org', '@type' => 'Event', 'name' => $ev['title'],
    'startDate' => date('c', strtotime($start)), 'endDate' => date('c', strtotime($end)),
    'eventStatus' => $ev['status'] === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'location' => ['@type' => 'Place', 'name' => $ev['location'] ?: setting('church_name'), 'address' => church_address_line()],
    'image' => media_url($ev['image']), 'description' => excerpt($ev['description'], 300),
    'organizer' => ['@type' => 'Organization', 'name' => $ev['organizer'] ?: setting('church_name'), 'url' => url()],
]];

$page = ['title' => $ev['title'], 'nav' => 'events', 'type' => 'article', 'description' => $ev['description'], 'image' => $ev['image'], 'schema' => $schema];
require __DIR__ . '/includes/header.php';
echo page_banner($ev['title'], format_date($ev['event_date'], 'l, F j, Y'), $ev['image'] ?: 'assets/images/placeholders/hero-poster.svg', ['Events' => 'events.php']);
?>
<section class="section">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-8" data-aos="fade-up">
        <?php if ($ev['status'] === 'cancelled'): ?><div class="alert alert-danger"><i class="fa-solid fa-ban" aria-hidden="true"></i> This event has been cancelled.</div><?php endif; ?>
        <img class="detail-image" src="<?= e(media_url($ev['image'])) ?>" alt="<?= e($ev['title']) ?>" width="900" height="500">
        <div class="rich-text mt-4"><?= paragraphs($ev['description']) ?></div>
      </div>
      <aside class="col-lg-4">
        <div class="side-card event-facts" data-aos="fade-left">
          <h2 class="h5">Event Details</h2>
          <ul>
            <li><i class="fa-regular fa-calendar" aria-hidden="true"></i><span><strong>Date</strong><?= e(format_date($ev['event_date'], 'l, F j, Y')) ?><?= $ev['end_date'] && $ev['end_date'] !== $ev['event_date'] ? ' – ' . e(format_date($ev['end_date'], 'F j')) : '' ?></span></li>
            <?php if ($ev['start_time']): ?><li><i class="fa-regular fa-clock" aria-hidden="true"></i><span><strong>Time</strong><?= e(time_range($ev['start_time'], $ev['end_time'])) ?></span></li><?php endif; ?>
            <?php if ($ev['location']): ?><li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><strong>Location</strong><?= e($ev['location']) ?></span></li><?php endif; ?>
            <?php if ($ev['organizer']): ?><li><i class="fa-solid fa-user-group" aria-hidden="true"></i><span><strong>Organizer</strong><?= e($ev['organizer']) ?></span></li><?php endif; ?>
            <?php if ($ev['category_name']): ?><li><i class="fa-solid fa-tag" aria-hidden="true"></i><span><strong>Category</strong><?= e($ev['category_name']) ?></span></li><?php endif; ?>
          </ul>
          <?php if (!$isPast && $ev['status'] !== 'cancelled'): ?>
            <?php if ($ev['requires_registration'] && $ev['registration_url']): ?><a class="btn btn-gold w-100 mb-2" href="<?= e($ev['registration_url']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Register Now</a><?php endif; ?>
            <a class="btn btn-outline-navy w-100" href="<?= e($gcal) ?>" target="_blank" rel="noopener"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i> Add to Google Calendar</a>
          <?php endif; ?>
          <button class="btn btn-light w-100 mt-2" type="button" data-share data-title="<?= e($ev['title']) ?>"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i> Share</button>
        </div>
        <?php if ($more): ?>
        <div class="side-card mt-4" data-aos="fade-left" data-aos-delay="100">
          <h2 class="h5">More Upcoming Events</h2>
          <?php foreach (array_slice($more, 0, 3) as $m): ?>
            <a class="related-item" href="<?= e(url('event-details.php?slug=' . rawurlencode($m['slug']))) ?>"><?= date_chip($m['event_date']) ?><span><strong><?= e($m['title']) ?></strong><small><?= e(format_time($m['start_time'])) ?></small></span></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
