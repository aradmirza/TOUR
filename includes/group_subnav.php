<?php
// group_subnav.php — included in all group sub-pages
// Requires: $groupId (int)  $groupTab (string)
$baseUrl = getBaseUrl();
?>
<div class="group-subnav">
  <a href="<?= $baseUrl ?>group.php?id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='overview') ? 'active' : '' ?>">
    📊 Overview
  </a>
  <a href="<?= $baseUrl ?>tour-plan.php?id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='plan') ? 'active' : '' ?>">
    🗓️ Plan
  </a>
  <a href="<?= $baseUrl ?>expenses.php?id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='expenses') ? 'active' : '' ?>">
    💸 Expenses
  </a>
  <a href="<?= $baseUrl ?>settlement.php?id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='settlement') ? 'active' : '' ?>">
    💰 Settlement
  </a>
  <a href="<?= $baseUrl ?>feed.php?group_id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='feed') ? 'active' : '' ?>">
    📰 Feed
  </a>
  <a href="<?= $baseUrl ?>gallery.php?group_id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='gallery') ? 'active' : '' ?>">
    🖼️ Gallery
  </a>
  <a href="<?= $baseUrl ?>group-members.php?id=<?= $groupId ?>"
     class="gnav-item <?= ($groupTab==='members') ? 'active' : '' ?>">
    👥 Members
  </a>
</div>
