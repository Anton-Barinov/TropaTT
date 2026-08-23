<?php
declare(strict_types=1);

return [
    'has_assignments' => 'La grille tarifaire est affectée à un tiers ou un projet — retirez l\'affectation avant d\'archiver',
    'at_least_one_rate' => 'Indiquez au moins un taux',
    'invalid_scope' => 'Type de périmètre invalide',
    'date_range_required' => 'Indiquez une plage de dates (date_from et date_to)',
    'invalid_date_format' => 'Format de date invalide. Attendu AAAA-MM-JJ.',
    'date_from_after_to' => 'La date "de" ne peut pas être postérieure à "à".',
    'range_too_large' => 'La plage de dates ne doit pas dépasser 366 jours',
    'invalid_role' => 'Le rôle indiqué n\'existe pas',
    'invalid_activity_code' => 'Le type de travail indiqué n\'est pas dans le dictionnaire',
    'negative_rate' => 'Le taux ne peut pas être négatif',
    'invalid_date_range' => 'La date de fin ne peut pas être antérieure à la date de début',
    'duplicate_line' => 'Une ligne avec le même employé, rôle et type de travail existe déjà dans cette grille tarifaire',
    'invalid_markup_percent' => 'Le pourcentage de majoration doit être entre 0 et 1000 ou vide',
    'invalid_lag_days' => 'Le délai doit être entre 0 et 90 jours',
    'invalid_auto_close_mode' => 'Mode de clôture automatique invalide',
    'preview_scope_required' => 'Indiquez une tâche ou un projet/tiers pour prévisualiser le taux',
];
