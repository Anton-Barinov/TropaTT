<?php
declare(strict_types=1);

if (!function_exists('crm_page_head')) {
    /**
     * @param array<int,array{label:string,href?:string,active?:bool}> $breadcrumbs
     */
    function crm_page_head(array $breadcrumbs, string $title, string $subtitle = '', string $actionsHtml = '', string $className = '', string $subtitleAttributes = ''): void
    {
        $classes = trim('crm-page-head ' . $className);
        echo '<div class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"><div>';
        if ($breadcrumbs !== []) {
            echo '<ol class="breadcrumb mb-1">';
            foreach ($breadcrumbs as $item) {
                $label = htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                $href = trim((string)($item['href'] ?? ''));
                $active = (bool)($item['active'] ?? false);
                if ($active && trim((string)($item['label'] ?? '')) === $title) {
                    continue;
                }
                echo '<li class="breadcrumb-item' . ($active ? ' active' : '') . '">';
                if ($href !== '' && !$active) {
                    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
                } else {
                    echo $label;
                }
                echo '</li>';
            }
            echo '</ol>';
        }
        echo '<h1 class="crm-page-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        if ($subtitle !== '') {
            $attributes = trim($subtitleAttributes);
            echo '<p class="crm-subtitle"' . ($attributes !== '' ? ' ' . $attributes : '') . '>' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '</div>';
        if ($actionsHtml !== '') {
            echo '<div class="crm-page-actions">' . $actionsHtml . '</div>';
        }
        echo '</div>';
    }
}
