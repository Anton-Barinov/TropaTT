<?php
/** @var array<string, mixed> $form */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($form['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container" style="max-width: 600px;">
        <div class="card shadow-sm p-4">
            <h2 class="mb-3"><?= htmlspecialchars($form['title']) ?></h2>
            <?php if (!empty($form['description'])): ?>
                <p class="text-muted"><?= htmlspecialchars($form['description']) ?></p>
            <?php endif; ?>

            <form id="public-form" action="/api/v1/public-forms/<?= htmlspecialchars($form['slug']) ?>/submit" method="POST">
                <!-- Honeypot field (hidden from real users) -->
                <div style="display:none;" aria-hidden="true">
                    <input type="text" name="_hp_website" tabindex="-1" autocomplete="off">
                </div>

                <?php foreach (($form['fields_schema'] ?? []) as $f): ?>
                    <div class="mb-3">
                        <label class="form-label"><?= htmlspecialchars($f['label'] ?? '') ?> <?= !empty($f['required']) ? '<span class="text-danger">*</span>' : '' ?></label>
                        <input type="<?= htmlspecialchars($f['type'] ?? 'text') ?>" name="<?= htmlspecialchars($f['name'] ?? '') ?>" class="form-control" <?= !empty($f['required']) ? 'required' : '' ?>>
                    </div>
                <?php endforeach; ?>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="consent_agreed" value="1" id="consentCheck" required>
                    <label class="form-check-label small text-muted" for="consentCheck">
                        <?= htmlspecialchars($form['consent_text'] ?? 'Я согласен на обработку персональных данных') ?>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100">Отправить заявку</button>
            </form>
        </div>
    </div>
</body>
</html>
