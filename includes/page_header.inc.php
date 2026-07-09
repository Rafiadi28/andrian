<?php
/**
 * Reusable page header — set $page_title (required), $page_subtitle, $page_actions (optional HTML)
 */
if (empty($page_title)) {
    return;
}
?>
<div class="page-header">
    <div class="page-header-text">
        <h1><?= htmlspecialchars($page_title) ?></h1>
        <?php if (!empty($page_subtitle)): ?>
            <p class="text-muted"><?= htmlspecialchars($page_subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($page_actions)): ?>
        <div class="page-header-actions"><?= $page_actions ?></div>
    <?php endif; ?>
</div>
