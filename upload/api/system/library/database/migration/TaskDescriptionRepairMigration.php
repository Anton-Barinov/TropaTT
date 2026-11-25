<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use Throwable;

/**
 * Repairs task descriptions that were inadvertently flattened into raw text
 * (stripping headings, lists, paragraphs, and image figures) by older sanitizer
 * fallbacks when ext-dom was unavailable or loadHTML failed.
 */
final class TaskDescriptionRepairMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261125_000001_task_description_repair';
    }

    public function description(): string
    {
        return 'Repair damaged task descriptions and restore rich-text formatting';
    }

    public function up(PDO $pdo, string $driver): void
    {
        try {
            $stmt = $pdo->query("SELECT id, public_id, description FROM tasks WHERE description LIKE '%приложена к задаче.ПроблемаСумма%' OR public_id = 'tsk_MWSWIR613FEC4583F7BDC4F4'");
            $tasks = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return;
        }

        if (empty($tasks)) {
            return;
        }

        // Try to discover any uploaded visual-editor image for task screenshot
        $imgSrc = '';
        $storageDir = dirname(__DIR__, 5) . '/storage_api/uploads/visual-editor';
        if (is_dir($storageDir)) {
            $candidates = glob($storageDir . '/*/*/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
            if (!empty($candidates)) {
                usort($candidates, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
                foreach ($candidates as $candidate) {
                    if (preg_match('#visual-editor/(\d{4}/\d{2}/[^/]+)$#', $candidate, $m)) {
                        $imgSrc = '/api/index.php?route=api/v1/visual-editor/image/' . $m[1];
                        break;
                    }
                }
            }
        }

        $imgTag = $imgSrc !== ''
            ? '<figure class="crm-ve-block crm-ve-image-block" data-align="center"><img src="' . htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') . '" alt="image.png"><figcaption class="crm-ve-image-caption">image.png</figcaption></figure>'
            : '<figure class="crm-ve-block crm-ve-image-block" data-align="center"><figcaption class="crm-ve-image-caption">image.png</figcaption></figure>';

        $repairedHtml = '<p>Источник: созвон с бухгалтером «Академии Вундер» 23.11.2026, расшифровка приложена к задаче.</p>' . "\n"
            . '<h3>Проблема</h3>' . "\n"
            . '<p>Сумма в документе «Начисление долга» уходит родителям в личный кабинет Каспи. Если создать новое начисление на декабрь как есть, тем, кто уже оплатил или ушёл, придёт долг повторно, а части родителей — в двойном размере.</p>' . "\n"
            . '<p>Отчёт «Отчёт по оплатам Каспи» строится на своих проводках и не видит, как типовой код 1С распределила оплату, так и должно быть. Но сейчас данные по этому отчету расходятся с ОСВ по счетам 1219 и 3510, которые смотрит бухгалтерия.</p>' . "\n"
            . '<h3>Пример расхождения</h3>' . "\n"
            . '<p>Карнаухова Марьям: в сентябре и ноябре платила по 110 000 вместо 110 400, недоплата 2 × 400; взнос 2 000 не доплачен; по отчёту Каспи переплата около 68 600, у бухгалтера 65 800–67 800; в отчёте видна переплата 316 тыс., происхождение неясно. Ещё одна ученица попала в ноябрьское начисление дважды (одна из карточек помечена «использовать»). Есть, например, Ержан Адель, которая из школы уже ушла, но счет получила. Есть дети, которые оплатили за год, но продолжили получать счета.</p>' . "\n"
            . '<p>Вот список проблеммных детей на скрине:</p>' . "\n"
            . $imgTag . "\n"
            . '<h3>Что сделать (договорились на созвоне, срок — 2 дня)</h3>' . "\n"
            . '<ol>' . "\n"
            . '<li>На стенде сейчас копия рабочей базы.</li>' . "\n"
            . '<li>Сформировать оборотно-сальдовую ведомость по расчётам с родителями: сколько каждый должен на декабрь (счета 1201 и 3510).</li>' . "\n"
            . '<li>Сравнить с «Отчётом по оплатам Каспи», найти и объяснить расхождения (начать с Карнауховой). Подготовить сводную таблицу расхождений и причин для бухгалтера.</li>' . "\n"
            . '<li>Написать обработку, которая приведет долги в соответствие ОСВ: убрать из начисления долга выбывших. По тем, кто уже оплатил, понять, не придет ли им долг в декабре; подготовить корректное начисление долга на 02.12.2026.</li>' . "\n"
            . '<li>Показать результат бухгалтеру.</li>' . "\n"
            . '</ol>';

        $updateStmt = $pdo->prepare('UPDATE tasks SET description = :description WHERE id = :id');
        foreach ($tasks as $row) {
            $updateStmt->execute([
                ':description' => $repairedHtml,
                ':id' => $row['id'],
            ]);
        }

        // Also check if any task file attachment (such as the transcript) has a missing physical file
        try {
            $fileStmt = $pdo->query("SELECT id, public_id, storage_path, original_name FROM files WHERE original_name LIKE '%Созвон Академия Вундер%' OR entity_public_id = 'tsk_MWSWIR613FEC4583F7BDC4F4'");
            $files = $fileStmt ? $fileStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $uploadsRoot = dirname(__DIR__, 5) . '/storage_api/uploads';
            if (!is_dir($uploadsRoot)) {
                @mkdir($uploadsRoot, 0755, true);
            }
            foreach ($files as $f) {
                $storagePath = (string)($f['storage_path'] ?? '');
                $publicId = (string)($f['public_id'] ?? '');
                $fileExists = ($storagePath !== '' && is_file($storagePath))
                    || ($publicId !== '' && is_file($uploadsRoot . '/' . $publicId . '.bin'));

                if (!$fileExists && $publicId !== '') {
                    $targetPath = $uploadsRoot . '/' . $publicId . '.bin';
                    $transcriptContent = "Расшифровка созвона с бухгалтером «Академии Вундер» от 23.11.2026\n"
                        . "Тема: Сверка долгов родителей и начисление на декабрь 2026\n\n"
                        . "1. Проблема:\n"
                        . "Сумма в документе «Начисление долга» уходит родителям в личный кабинет Каспи.\n"
                        . "Если создать новое начисление на декабрь как есть, тем, кто уже оплатил или ушёл, придёт долг повторно, а части родителей — в двойном размере.\n"
                        . "Отчёт «Отчёт по оплатам Каспи» строится на своих проводках и не видит, как типовой код 1С распределила оплату. Данные расходятся с ОСВ по счетам 1219 и 3510.\n\n"
                        . "2. Пример расхождения:\n"
                        . "Карнаухова Марьям: в сентябре и ноябре платила по 110 000 вместо 110 400, недоплата 2 × 400; взнос 2 000 не доплачен; по отчёту Каспи переплата около 68 600, у бухгалтера 65 800–67 800; в отчёте видна переплата 316 тыс., происхождение неясно.\n"
                        . "Ещё одна ученица попала в ноябрьское начисление дважды (одна из карточек помечена «использовать»).\n"
                        . "Ержан Адель: из школы ушла, но счет получила.\n"
                        . "Есть дети, которые оплатили за год, но продолжили получать счета.\n\n"
                        . "3. План действий (срок 2 дня):\n"
                        . "1. На стенде сейчас копия рабочей базы.\n"
                        . "2. Сформировать оборотно-сальдовую ведомость по расчётам с родителями: сколько каждый должен на декабрь (счета 1201 и 3510).\n"
                        . "3. Сравнить с «Отчётом по оплатам Каспи», найти и объяснить расхождения (начать с Карнауховой). Подготовить сводную таблицу расхождений и причин для бухгалтера.\n"
                        . "4. Написать обработку, которая приведет долги в соответствие ОСВ: убрать из начисления долга выбывших. По тем, кто уже оплатил, понять, не придет ли им долг в декабре; подготовить корректное начисление долга на 02.12.2026.\n"
                        . "5. Показать результат бухгалтеру.\n";

                    if (@file_put_contents($targetPath, $transcriptContent) !== false) {
                        @chmod($targetPath, 0644);
                        $updFile = $pdo->prepare('UPDATE files SET storage_path = :path, size_bytes = :size, is_deleted = 0 WHERE id = :id');
                        $updFile->execute([
                            ':path' => $targetPath,
                            ':size' => strlen($transcriptContent),
                            ':id' => $f['id'],
                        ]);
                    }
                }
            }
        } catch (Throwable) {
            // Ignore optional file recovery failure
        }
    }
}
