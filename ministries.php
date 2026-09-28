<?php
require __DIR__ . '/includes/bootstrap.php';

$ministries = Department::publicList();

$page = ['title' => 'Ministries', 'nav' => 'ministries', 'description' => 'Discover the ministries of ' . setting('church_name') . ' — worship, prayer, youth, women, men, children, evangelism and more.'];
require __DIR__ . '/includes/header.php';
echo page_banner('Our Ministries', 'Find your place to grow, serve and belong.');
?>
<section class="section">
  <div class="container">
    <?= section_heading('Get Involved', 'Serving Together', 'Every ministry is a family. Reach out to a department head to join.') ?>
    <?php if (!$ministries): echo empty_state('fa-people-group', 'Ministries coming soon');
    else: ?>
    <div class="ministry-list">
      <?php foreach ($ministries as $i => $m):
          $head = $m['head_user_name'] ?: $m['head_name']; ?>
        <article class="ministry-row" id="<?= e($m['slug']) ?>" data-aos="<?= $i % 2 ? 'fade-left' : 'fade-right' ?>">
          <div class="mr-media"><img src="<?= e(media_url($m['image'])) ?>" alt="<?= e($m['name']) ?>" loading="lazy" width="560" height="380">
            <span class="mr-icon"><i class="fa-solid <?= e($m['icon'] ?: 'fa-church') ?>" aria-hidden="true"></i></span></div>
          <div class="mr-body">
            <h2><?= e($m['name']) ?></h2>
            <p><?= nl2br_e($m['description']) ?></p>
            <ul class="mr-meta">
              <?php if ($head): ?><li><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span><strong>Department Head</strong><?= e($head) ?></span></li><?php endif; ?>
              <?php if ($m['meeting_schedule']): ?><li><i class="fa-regular fa-clock" aria-hidden="true"></i><span><strong>Meetings</strong><?= e($m['meeting_schedule']) ?></span></li><?php endif; ?>
              <?php if ($m['contact_phone'] || $m['contact_email']): ?><li><i class="fa-solid fa-phone" aria-hidden="true"></i><span><strong>Contact</strong>
                <?php if ($m['contact_phone']): ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $m['contact_phone'])) ?>"><?= e($m['contact_phone']) ?></a><?php endif; ?>
                <?php if ($m['contact_email']): ?><a href="mailto:<?= e($m['contact_email']) ?>"><?= e($m['contact_email']) ?></a><?php endif; ?></span></li><?php endif; ?>
            </ul>
            <a class="btn btn-outline-navy btn-sm" href="<?= e(url('contact.php?subject=' . rawurlencode('Joining the ' . $m['name']))) ?>">I'd like to join <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
