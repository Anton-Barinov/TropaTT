<?php
declare(strict_types=1);

return [
    'list' => 'Liste des idées',
    'created' => 'Idée créée avec succès',
    'updated' => 'Idée mise à jour',
    'deleted' => 'Idée supprimée',
    'voted' => 'Vote enregistré',
    'unvoted' => 'Vote retiré',
    'status_updated' => 'Statut de l\'idée mis à jour',
    'decomposed' => 'Tâches générées',
    'questions_generated' => 'Questions générées',
    'answers_saved' => 'Réponses enregistrées',
    'analysis_retried' => 'Analyse relancée',
    'title_required' => 'Le titre de l\'idée est requis',
    'notif_idea_created_title' => 'Idée créée',
    'notif_idea_created_body' => 'Votre idée',
    'notif_idea_created_body2' => 'a été créée avec succès.',
    'notif_idea_voted_title' => 'Nouveau vote pour l\'idée',
    'notif_idea_voted_body' => 'Un utilisateur a voté pour votre idée',
    'notif_idea_status_changed_title' => 'Statut de l\'idée modifié',
    'notif_idea_status_changed_body1' => 'Le statut de votre idée',
    'notif_idea_status_changed_body2' => 'modifié en',
    'current_state' => 'État actuel',
    'data_sufficient_click_analyze' => 'Données suffisantes. Cliquer sur « Lancer l\'analyse ».',
    'answer_questions_click_send' => 'Répondre aux questions de clarification et cliquer sur « Envoyer ».',
    'questions_formed_safe_mode' => 'Questions formulées (mode sûr)',
    'answer_for_quality_analysis' => 'Pour une analyse de qualité, veuillez répondre aux questions de clarification.',
    'ai_service_unavailable' => 'Service IA temporairement indisponible. Veuillez réessayer plus tard.',
    'analysis_completed' => 'Analyse terminée',
    'answer_questions_then_send' => 'Répondre aux questions de clarification et cliquer sur « Envoyer ».',
    'data_sufficient_can_analyze' => 'Données suffisantes. L\'analyse peut être lancée.',
    'ai_provider_not_responding' => 'Le fournisseur IA ne répond pas. Veuillez réessayer plus tard.',
    'data_sufficient' => 'Données suffisantes',
    'data_sufficient_for_analysis' => 'Données suffisantes pour l\'analyse.',
    'no_new_questions_remaining' => 'Aucune nouvelle question de clarification. L\'analyse peut être démarrée.',
    'no_new_questions_can_analyze' => 'Aucune nouvelle question de clarification. L\'analyse peut être lancée.',
    'need_clarification' => 'Clarification nécessaire',
    'answers_saved_need_clarification' => 'Réponses enregistrées. Clarification nécessaire.',
    'answers_saved_need_more_clarification' => 'Réponses enregistrées. Clarification supplémentaire nécessaire.',
    'answers_saved_label' => 'Réponses enregistrées',
    'answers_saved_can_analyze' => 'Réponses enregistrées. L\'analyse peut être lancée.',
    'refine_failed' => 'Échec du traitement des réponses. Veuillez réessayer.',
    'debug_logs_cleared' => 'Journaux IA effacés',
    'answer_dont_know' => 'NE SAIS PAS',
    'no_answer' => 'pas de réponse',
    'ai_analyst_system_prompt' => 'Vous êtes un analyste d\'idées. Analysez les données et trouvez les lacunes.',
    'ai_analysis_failed' => 'L\'IA n\'a pas pu lancer l\'analyse',
    'ai_card_failed' => 'L\'IA n\'a pas pu construire la fiche. Veuillez réessayer plus tard.',
    'card_first_required' => 'Commencez par construire la fiche de compréhension de l\'idée.',
    'ai_gaps_failed' => 'L\'IA n\'a pas pu trouver les lacunes',
    'ai_refine_card_failed' => 'L\'IA n\'a pas pu affiner la fiche.',
    'ai_potential_failed' => 'L\'IA n\'a pas pu calculer le potentiel.',
    'ai_risk_fallback_summary' => 'L\'IA n\'a pas pu terminer l\'analyse des risques. Essayez un autre fournisseur IA pour des résultats plus précis.',
    'ai_risks_failed' => 'L\'IA n\'a pas pu calculer les risques.',
    'ai_pitfalls_fallback_summary' => 'L\'IA n\'a pas pu terminer l\'analyse.',
    'ai_pitfalls_failed' => 'L\'IA n\'a pas pu trouver les pièges cachés.',
    'ai_plan_fallback_summary' => 'L\'IA n\'a pas pu terminer le plan.',
    'ai_plan_failed' => 'L\'IA n\'a pas pu construire le plan.',
    'ai_recommendation_failed' => 'L\'IA n\'a pas pu générer une recommandation.',
    'ai_tasks_fallback_summary' => 'L\'IA n\'a pas pu générer les tâches.',
    'ai_tasks_failed' => 'L\'IA n\'a pas pu suggérer des tâches.',
    'generate_tasks_first' => 'Commencez par générer les tâches suggérées.',
    'no_tasks_to_create' => 'Aucune tâche à créer.',
    'project_label' => 'Projet',
    'task_label' => 'Tâche',
    'project_created' => 'Projet créé',
    'project_created_tasks' => 'tâches',
    'interview_cleared' => 'Historique des questions et réponses effacé',
    'interview_limit_reached' => 'Limite de questions atteinte (25)',
    'ai_questions_generation_failed' => 'L\'IA n\'a pas pu générer les questions. Veuillez réessayer plus tard.',
    'no_answers_to_save' => 'Aucune réponse à enregistrer',
    'option_not_sure' => 'Pas encore sûr',
    'option_custom_answer' => 'Réponse personnalisée',
    'analysis_reset_failed' => 'Échec de la réinitialisation de l\'analyse IA.',
    'analysis_reset' => 'Analyse IA réinitialisée. Vous pouvez recommencer.',
    'analysis_reset_message' => 'Anciennes données d\'analyse IA effacées. Cliquer sur « Lancer » pour une nouvelle analyse.',
    'analysis_not_ready_status' => 'L\'analyse ne peut être lancée qu\'à partir du statut ready_for_analysis.',
    'answer_all_questions_first' => 'Répondez à toutes les questions de clarification avant de lancer l\'analyse.',
    'demo_analysis' => 'Analyse de démonstration (mode sûr)',
    'demo_mode_message' => 'Mode démonstration. Pour une analyse IA réelle, désactivez le mode sûr.',
    'analysis_completed_label' => 'Analyse terminée',
    'analysis_complete_full' => 'Analyse complète prête.',
    'analysis_complete_partial' => 'Terminé',
    'analysis_complete_of' => 'sur',
    'analysis_complete_steps' => 'étapes.',
    'unknown_step_key' => 'step_key inconnu.',
    'analysis_step_done' => 'Étape d\'analyse terminée',
    'empty_analysis_result' => 'result_json vide pour l\'étape d\'analyse.',
    'plan_title' => 'Plan d\'implémentation',
    'tasks_created_count' => 'tâches',
    'fallback_card_summary' => 'L\'idée n\'est pas encore décrite en détail suffisant. La fiche a été construite à partir des données CRM disponibles.',
    'fallback_fact_title' => 'Titre de l\'idée :',
    'fallback_fact_description' => 'Description de l\'idée :',
    'fallback_category' => 'Catégorie',
    'fallback_product' => 'Produit',
    'fallback_region' => 'Région',
    'fallback_target_date' => 'Date cible',
    'fallback_missing_goal' => 'Objectif d\'implémentation et critères de succès',
    'fallback_missing_audience' => 'Public cible ou utilisateurs',
    'fallback_missing_budget' => 'Budget et contraintes financières',
    'fallback_missing_timeline' => 'Calendrier et dates critiques',
    'fallback_missing_team' => 'Équipe et personnes responsables',
    'fallback_missing_legal' => 'Contraintes légales ou opérationnelles',
    'fallback_missing_risks' => 'Risques clés et dépendances',
    'fallback_early_risks' => 'Le fournisseur IA a retourné un JSON incorrect, donc la fiche a été construite à partir des faits CRM et nécessite une vérification aux étapes suivantes.',
    'fallback_factor_goal' => 'Objectif',
    'fallback_factor_timeline' => 'Calendrier',
    'fallback_factor_budget' => 'Budget',
    'fallback_factor_responsible' => 'Personnes responsables',
    'fallback_factor_impl_risks' => 'Risques d\'implémentation',
    'fallback_factor_business_effect' => 'Effet commercial attendu',
    'fallback_next_step_reason' => 'Les données disponibles suffisent pour ne pas bloquer les étapes d\'analyse suivantes ; les clarifications peuvent être collectées ultérieurement.',
    'fallback_idea_label' => 'idée',
    'fallback_potential_verdict' => 'Évaluation préliminaire : l\'idée peut être analysée plus en profondeur, mais le résultat nécessite une vérification en raison d\'une réponse incomplète du fournisseur IA.',
    'fallback_potential_summary' => 'Potentiel calculé à partir de la fiche de compréhension disponible et des réponses utilisateur. Le fournisseur IA a retourné un JSON incorrect, donc l\'évaluation n\'est pas définitive.',
    'fallback_criterion_clarity' => 'Clarté de l\'idée',
    'fallback_criterion_clarity_reason' => 'Évaluée par l\'exhaustivité de la fiche de compréhension.',
    'fallback_criterion_business' => 'Pertinence commerciale',
    'fallback_criterion_business_reason' => 'Évaluée par la description de l\'idée.',
    'fallback_criterion_data' => 'Qualité des données sources',
    'fallback_criterion_data_reason' => 'Évaluée par le nombre de réponses.',
    'fallback_criterion_readiness' => 'Préparation à l\'implémentation',
    'fallback_criterion_readiness_reason' => 'Nécessite une vérification supplémentaire des risques et du plan.',
    'fallback_missing_risk' => 'Risques',
    'fallback_missing_plan' => 'Plan d\'implémentation',
    'fallback_missing_constraints' => 'Contraintes',
    'fallback_strengths' => 'Possède une description originale et une fiche de compréhension.',
    'fallback_weaknesses' => 'L\'évaluation est préliminaire car le fournisseur IA n\'a pas retourné de réponse structurée correcte.',
    'fallback_growth_factors' => 'La clarification des objectifs, du budget, du calendrier et des personnes responsables améliorera la qualité de l\'évaluation.',
    'fallback_risk_factors' => 'Une structuration insuffisante des données peut fausser l\'évaluation du potentiel.',
    'fallback_missing_financial' => 'Contraintes financières',
    'fallback_missing_success' => 'Critères de succès',
    'fallback_missing_main_risks' => 'Risques clés',
    'fallback_missing_impl_plan' => 'Plan d\'implémentation',
    'fallback_improve_score' => 'Répondez aux questions manquantes et recalculez.',
    'fallback_reduce_score' => 'Risques critiques, budget flou ou personnes responsables manquantes.',
    'fallback_next_action_reason' => 'Ne pas bloquer le processus d\'analyse, mais considérer l\'évaluation comme préliminaire.',
    'fallback_status_refine_first' => 'Affiner l\'idée d\'abord',
    'fallback_final_short_verdict' => 'Préliminaire : l\'idée doit être affinée et l\'analyse revérifiée.',
    'fallback_final_detailed_verdict' => 'Le fournisseur IA a retourné un JSON incorrect à l\'étape finale de la recommandation. Le CRM a enregistré une recommandation préliminaire sûre basée sur les blocs déjà calculés pour ne pas bloquer l\'utilisateur.',
    'fallback_final_reason1' => 'Résultat formé à partir des blocs d\'analyse disponibles.',
    'fallback_final_reason2' => 'Une régénération est nécessaire pour une recommandation IA complète.',
    'fallback_final_negative' => 'Une partie de la réponse IA n\'a pas pu être analysée comme JSON valide.',
    'fallback_final_condition1' => 'Vérifier les données manquantes',
    'fallback_final_condition2' => 'Relancer l\'analyse IA lorsque le fournisseur répond de manière stable',
    'fallback_validate_goal' => 'Objectif',
    'fallback_validate_budget' => 'Budget',
    'fallback_validate_timeline' => 'Calendrier',
    'fallback_validate_risks' => 'Risques',
    'fallback_validate_responsible' => 'Personnes responsables',
    'fallback_action_check_card' => 'Vérifier la fiche de compréhension',
    'fallback_action_clarify_data' => 'Clarifier les données manquantes',
    'fallback_action_regenerate' => 'Relancer la génération de la recommandation finale',
    'fallback_wrong_decision' => 'La décision sera prise sur des données incomplètes.',
    'fallback_missing_ai_response' => 'Réponse structurée du fournisseur IA entièrement correcte',
    'fallback_final_summary' => 'Le système a enregistré une recommandation préliminaire, mais pour la décision finale, il est préférable de régénérer après vérification des données.',
    'status_proceed' => 'Peut procéder à l\'implémentation',
    'status_proceed_with_validation' => 'Peut procéder, mais valider les hypothèses d\'abord',
    'status_refine_first' => 'Affiner l\'idée d\'abord',
    'status_collect_more_data' => 'Collecter les données manquantes d\'abord',
    'status_postpone' => 'Mieux vaut reporter',
    'status_reject' => 'Non recommandé dans la forme actuelle',
    'sm_exact_demand' => 'Demande exacte',
    'sm_budget' => 'Budget',
    'sm_competitors' => 'Concurrents',
    'sm_q1_text' => 'Où prévoyez-vous de mettre en œuvre cette idée ?',
    'sm_q1_reason' => 'L\'emplacement détermine le marché, les clients et les coûts.',
    'sm_q1_local' => 'Marché local (quartier/ville)',
    'sm_q1_regional' => 'Régional',
    'sm_q1_national' => 'National',
    'sm_q2_text' => 'Quel budget approximatif envisagez-vous ?',
    'sm_q2_reason' => 'Le budget détermine l\'échelle et la vitesse de lancement.',
    'sm_q2_minimal' => 'Minimal (jusqu\'à 150 000 ₽)',
    'sm_q2_small' => 'Petit (150 000 – 500 000 ₽)',
    'sm_q2_medium' => 'Moyen (500 000 – 2 000 000 ₽)',
    'sm_q3_text' => 'Qui est votre public cible principal ?',
    'sm_q3_reason' => 'Le public détermine le produit, la tarification et la promotion.',
    'sm_q3_individuals' => 'Particuliers',
    'sm_q3_businesses' => 'Entreprises / Sociétés',
    'sm_q3_both' => 'Les deux',
    'sm_q4_text' => 'Avez-vous de l\'expérience dans ce domaine ?',
    'sm_q4_reason' => 'L\'expérience影响 les délais, les risques et les besoins de formation.',
    'sm_q4_experienced' => 'Oui, j\'ai de l\'expérience',
    'sm_q4_no_experience' => 'Pas d\'expérience',
    'sm_q4_have_knowledge' => 'J\'ai des connaissances théoriques',
    'sm_q5_text' => 'Qu\'est-ce qui vous préoccupe le plus dans la mise en œuvre de cette idée ?',
    'sm_q5_reason' => 'Aide à identifier les risques clés.',
    'sm_not_sure' => 'Pas encore sûr',
    'sm_analysis_summary' => 'est au stade de développement.',
    'sm_idea_described' => 'L\'idée est décrite',
    'sm_no_detailed_data' => 'Pas de données détaillées pour l\'analyse',
    'sm_check_demand' => 'Vérifier la demande dans le lieu sélectionné',
    'sm_study_competitors' => 'Étudier les concurrents à proximité',
    'sm_final_needs_verification' => 'nécessite une vérification avant investissement.',
    'sm_market_exists' => 'Le marché existe',
    'sm_few_data' => 'Données limitées',
    'sm_check_demand_before' => 'Vérifier la demande avant l\'investissement',
    'sm_insufficient_data' => 'Données insuffisantes',
    'sm_without_data_hard' => 'Difficile à évaluer sans données',
    'sm_answer_questions_action' => 'Répondre aux questions de clarification',
    'sm_no_market_analysis' => 'Pas d\'analyse de marché',
    'sm_study_demand_district' => 'Étudier la demande dans le quartier sélectionné',
    'sm_demand_exists' => 'La demande existe',
    'sm_survey_audience' => 'Interroger le public cible',
    'sm_positive_answers' => '>20 réponses positives',
    'sm_check_competitors_path' => 'Vérifier la demande, étudier les concurrents dans le quartier sélectionné.',
    'sm_action1' => '1. Étudier les concurrents dans le rayon du lieu.',
    'sm_action2' => '2. Interroger les résidents du quartier.',
    'sm_action3' => '3. Calculer le budget minimum.',
    'sm_no_demand_data' => 'Pas de données de demande',
    'sm_check_demand_label' => 'Demande',
    'sm_check_competitors_label' => 'Concurrents',
    'enough_data' => 'Données suffisantes',
    'enough_data_for_analysis' => 'Données suffisantes pour l\'analyse.',
    'enough_data_can_analyze' => 'Données suffisantes. L\'analyse peut procéder.',
    'prompt_analyst_system' => 'Vous êtes un analyste d\'idées. Analysez les données et trouvez les lacunes.',
    'system_prompt_card' => 'Vous construisez une « Fiche de Compréhension de l\'Idée » basée sur toutes les informations disponibles.

Votre tâche N\'EST PAS de faire une analyse finale de l\'idée. PAS de donner une recommandation « ça vaut le coup / ça ne vaut pas le coup ». PAS de créer un business plan.
Collectez un profil structuré de la compréhension actuelle de l\'idée pour une analyse complète ultérieure.

Entrée : données originales de l\'idée, description, toutes les questions de clarification et réponses, sujets traités, sujets où l\'utilisateur a répondu « ne sais pas ».

Règles :
1. Utilisez uniquement les données fournies. N\'inventez pas de faits.
2. Séparez les faits des hypothèses.
3. « ne sais pas » / « pas encore décidé » — c\'est un fait : l\'utilisateur n\'a pas décidé.
4. Ne transformez pas une réponse floue en une valeur spécifique.
5. Ne tirez pas de conclusion finale sur la viabilité.
6. Ne donnez pas de conseils. N\'évaluez pas « si ça vaut le coup de le faire ».
7. Déterminez dans quelle mesure l\'idée est actuellement comprise et quelles données manquent.
8. Identifiez les risques précoces comme des domaines d\'attention préliminaires.
9. Identifiez les facteurs clés pour l\'analyse ultérieure.
10. completeness.overall et confidence_score — nombres de 0 à 1.
11. next_step.action : ask_more_questions / start_analysis / preliminary_analysis.
12. INTERDIT d\'utiliser les symboles { et } dans les valeurs de texte.
13. Si des crochets sont nécessaires dans le texte — utilisez uniquement ( ) ou [ ].

Retournez UNIQUEMENT du JSON valide. Pas de markdown, pas de commentaires, pas de texte avant ou après le JSON.

JSON :',
    'system_prompt_refined_card' => 'Vous affinez la « Fiche de Compréhension de l\'Idée » basée sur toutes les données disponibles.

Vous recevrez :
1. existing_understanding_card — fiche collectée précédemment
2. all_questions_and_answers — TOUTES les questions et réponses (y compris celles clarifiées marquées comme clarifiées)
3. Données de l\'idée

Votre tâche : reconstruire la fiche en intégrant les nouvelles réponses.
- Mettre à jour known_facts basé sur les réponses.
- Déplacer les sujets de missing_facts/user_unknowns vers known_facts si les réponses les ont couverts.
- Mettre à jour completeness_score et confidence_score.
- Réviser early_risks, constraints, assumptions.
- Déterminer le nouveau next_step.

Règles :
1. Ne faites pas d\'analyse finale, ne conseillez pas.
2. Utilisez uniquement les données fournies.
3. INTERDIT d\'utiliser les symboles { et } dans les valeurs de texte.
4. Si des crochets sont nécessaires dans le texte — utilisez uniquement ( ) ou [ ].
5. Retournez uniquement du JSON, pas de markdown et pas de texte avant ou après le JSON.

JSON :',
    'system_prompt_risk' => 'Vous êtes un expert en analyse de risques. Évaluez les risques de l\'idée. Chaque risque : catégorie, probabilité 1-5, impact 1-5.

RÈGLE LA PLUS IMPORTANTE : INTERDIT d\'utiliser { et } dans le texte de description.
Si des crochets sont nécessaires dans le texte — utilisez UNIQUEMENT ( ) ou [ ].
Tout symbole { ou } dans la chaîne « description » ou « title » rendra le JSON invalide.
Votre réponse doit être UNIQUEMENT du JSON. Pas de texte avant ou après. Pas de barrières markdown.

JSON :',
    'system_prompt_pitfalls' => 'Vous analysez l\'idée et identifiez les pièges cachés. Pour chaque : catégorie, probabilité 1-5, impact 1-5.

INTERDIT d\'utiliser { ou } dans le texte de description. Uniquement ( ) ou [ ].
Votre réponse est UNIQUEMENT du JSON, pas de texte avant ou après.

JSON :',
    'system_prompt_plan' => 'Vous créez un plan d\'implémentation pour l\'idée. Identifiez 3-7 étapes, 2-4 tâches chacune.

INTERDIT d\'utiliser { ou } dans le texte de description. Uniquement ( ) ou [ ].
Votre réponse est UNIQUEMENT du JSON, pas de texte avant ou après.

JSON :',
    'system_prompt_final' => 'Vous formez la recommandation finale. Appuyez-vous sur les blocs déjà préparés, ne pas analyser à zéro.

Notez sur une échelle de 0 à 100 : potential_score, feasibility_score, risk_score, data_completeness_score, plan_quality_score, blocker_score, confidence_score.

Statut : proceed / proceed_with_validation / refine_first / collect_more_data / postpone / reject_current_form.

Règles :
1. INTERDIT d\'utiliser les symboles { et } dans les valeurs de texte.
2. Si des crochets sont nécessaires dans le texte — utilisez uniquement ( ) ou [ ].
3. Retournez uniquement du JSON, pas de markdown et pas de texte avant ou après le JSON.',
    'system_prompt_project' => 'Créez un plan de projet détaillé avec des tâches (8-15 au total).

INTERDIT d\'utiliser { ou } dans le texte de description. Uniquement ( ) ou [ ].
Votre réponse est UNIQUEMENT du JSON, pas de texte avant ou après.

JSON :',
    'default_idea_title' => 'idée',
];
