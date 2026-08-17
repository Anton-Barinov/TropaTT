<?php
declare(strict_types=1);

return [
    'list' => 'Список идей',
    'created' => 'Идея успешно создана',
    'updated' => 'Идея обновлена',
    'deleted' => 'Идея удалена',
    'voted' => 'Голос учтён',
    'unvoted' => 'Голос снят',
    'status_updated' => 'Статус идеи обновлён',
    'decomposed' => 'Задачи сформированы',
    'questions_generated' => 'Вопросы сгенерированы',
    'answers_saved' => 'Ответы сохранены',
    'analysis_retried' => 'Анализ повторён',
    'title_required' => 'Название идеи обязательно',
    'notif_idea_created_title' => 'Идея создана',
    'notif_idea_created_body' => 'Ваша идея',
    'notif_idea_created_body2' => 'успешно создана.',
    'notif_idea_voted_title' => 'Новый голос за идею',
    'notif_idea_voted_body' => 'Пользователь проголосовал за вашу идею',
    'notif_idea_status_changed_title' => 'Статус идеи изменён',
    'notif_idea_status_changed_body1' => 'Статус вашей идеи',
    'notif_idea_status_changed_body2' => 'изменён на',
    'current_state' => 'Текущее состояние',
    'data_sufficient_click_analyze' => 'Данных достаточно. Нажмите «Провести анализ».',
    'answer_questions_click_send' => 'Ответьте на уточняющие вопросы и нажмите «Отправить».',
    'questions_formed_safe_mode' => 'Вопросы сформированы (safe mode)',
    'answer_for_quality_analysis' => 'Для качественного анализа нужно ответить на уточняющие вопросы.',
    'ai_service_unavailable' => 'AI-сервис временно недоступен. Попробуйте позже.',
    'analysis_completed' => 'Анализ выполнен',
    'answer_questions_then_send' => 'Ответьте на уточняющие вопросы и нажмите «Отправить».',
    'data_sufficient_can_analyze' => 'Данных достаточно. Можно провести анализ.',
    'ai_provider_not_responding' => 'AI-провайдер не отвечает. Попробуйте позже.',
    'data_sufficient' => 'Данных достаточно',
    'data_sufficient_for_analysis' => 'Данных достаточно для анализа.',
    'no_new_questions_remaining' => 'Новых уточняющих вопросов не осталось. Можно запускать анализ.',
    'no_new_questions_can_analyze' => 'Новых уточняющих вопросов не осталось. Можно провести анализ.',
    'need_clarification' => 'Нужны уточнения',
    'answers_saved_need_clarification' => 'Ответы сохранены. Нужны уточнения.',
    'answers_saved_need_more_clarification' => 'Ответы сохранены. Нужны дополнительные уточнения.',
    'answers_saved_label' => 'Ответы сохранены',
    'answers_saved_can_analyze' => 'Ответы сохранены. Можно провести анализ.',
    'refine_failed' => 'Не удалось обработать ответы. Попробуйте ещё раз.',
    'debug_logs_cleared' => 'Логи AI очищены',
    'answer_dont_know' => 'НЕ ЗНАЮ',
    'no_answer' => 'нет ответа',
    'ai_analyst_system_prompt' => 'Ты — аналитик идей. Проанализируй данные и найди пробелы.',
    'ai_analysis_failed' => 'AI не смог провести анализ',
    'ai_card_failed' => 'AI не смог собрать карточку. Попробуйте позже.',
    'card_first_required' => 'Сначала соберите карточку понимания идеи.',
    'ai_gaps_failed' => 'AI не смог найти пробелы',
    'ai_refine_card_failed' => 'AI не смог уточнить карточку.',
    'ai_potential_failed' => 'AI не смог рассчитать потенциал.',
    'ai_risk_fallback_summary' => 'AI не смог завершить анализ рисков. Попробуйте другой провайдер AI для более точных результатов.',
    'ai_risks_failed' => 'AI не смог рассчитать риски.',
    'ai_pitfalls_fallback_summary' => 'AI не смог завершить анализ.',
    'ai_pitfalls_failed' => 'AI не смог найти подводные камни.',
    'ai_plan_fallback_summary' => 'AI не смог завершить план.',
    'ai_plan_failed' => 'AI не смог собрать план.',
    'ai_recommendation_failed' => 'AI не смог сформировать рекомендацию.',
    'ai_tasks_fallback_summary' => 'AI не смог сгенерировать задачи.',
    'ai_tasks_failed' => 'AI не смог предложить задачи.',
    'generate_tasks_first' => 'Сначала сформируйте предлагаемые задачи.',
    'no_tasks_to_create' => 'Нет задач для создания.',
    'project_label' => 'Проект',
    'task_label' => 'Задача',
    'project_created' => 'Создан проект',
    'project_created_tasks' => 'задачами',
    'interview_cleared' => 'История вопросов и ответов очищена',
    'interview_limit_reached' => 'Достигнут лимит вопросов (25)',
    'ai_questions_generation_failed' => 'AI не смог сгенерировать вопросы. Попробуйте позже.',
    'no_answers_to_save' => 'Нет ответов для сохранения',
    'option_not_sure' => 'Пока не знаю',
    'option_custom_answer' => 'Свой ответ',
    'analysis_reset_failed' => 'Не удалось сбросить AI-анализ.',
    'analysis_reset' => 'AI-анализ сброшен. Можно запустить заново.',
    'analysis_reset_message' => 'Старые данные AI-анализа очищены. Нажмите «Запустить» для нового анализа.',
    'analysis_not_ready_status' => 'Анализ можно запускать только из статуса ready_for_analysis.',
    'answer_all_questions_first' => 'Ответьте на все уточняющие вопросы перед запуском анализа.',
    'demo_analysis' => 'Демо-анализ (safe mode)',
    'demo_mode_message' => 'Демо-режим. Для реального AI-анализа отключите safe_mode.',
    'analysis_completed_label' => 'Анализ завершён',
    'analysis_complete_full' => 'Полный анализ готов.',
    'analysis_complete_partial' => 'Готово',
    'analysis_complete_of' => 'из',
    'analysis_complete_steps' => 'шагов.',
    'unknown_step_key' => 'Неизвестный step_key.',
    'analysis_step_done' => 'Шаг анализа выполнен',
    'empty_analysis_result' => 'Пустой result_json для шага анализа.',
    'plan_title' => 'План реализации',
    'tasks_created_count' => 'задачами',
    'fallback_card_summary' => 'Идея пока описана недостаточно подробно. Карточка собрана из доступных данных CRM.',
    'fallback_fact_title' => 'Название идеи:',
    'fallback_fact_description' => 'Описание идеи:',
    'fallback_category' => 'Категория',
    'fallback_product' => 'Продукт',
    'fallback_region' => 'Регион',
    'fallback_target_date' => 'Целевая дата',
    'fallback_missing_goal' => 'Цель внедрения и критерии успеха',
    'fallback_missing_audience' => 'Целевая аудитория или пользователи',
    'fallback_missing_budget' => 'Бюджет и финансовые ограничения',
    'fallback_missing_timeline' => 'Сроки и критичные даты',
    'fallback_missing_team' => 'Команда и ответственные',
    'fallback_missing_legal' => 'Юридические или операционные ограничения',
    'fallback_missing_risks' => 'Основные риски и зависимости',
    'fallback_early_risks' => 'AI-провайдер вернул некорректный JSON, поэтому карточка собрана по фактам из CRM и требует проверки на следующих шагах.',
    'fallback_factor_goal' => 'Цель',
    'fallback_factor_timeline' => 'Сроки',
    'fallback_factor_budget' => 'Бюджет',
    'fallback_factor_responsible' => 'Ответственные',
    'fallback_factor_impl_risks' => 'Риски внедрения',
    'fallback_factor_business_effect' => 'Ожидаемый бизнес-эффект',
    'fallback_next_step_reason' => 'Доступных данных достаточно, чтобы не блокировать дальнейшие этапы анализа; уточнения можно собрать позже.',
    'fallback_idea_label' => 'идея',
    'fallback_potential_verdict' => 'Предварительная оценка: идею можно анализировать дальше, но результат требует проверки из-за неполного ответа AI-провайдера.',
    'fallback_potential_summary' => 'Потенциал рассчитан по доступной карточке понимания идеи и ответам пользователя. AI-провайдер вернул некорректный JSON, поэтому оценка не является финальной.',
    'fallback_criterion_clarity' => 'Понятность идеи',
    'fallback_criterion_clarity_reason' => 'Оценено по полноте карточки понимания.',
    'fallback_criterion_business' => 'Бизнес-значимость',
    'fallback_criterion_business_reason' => 'Оценено по описанию идеи.',
    'fallback_criterion_data' => 'Качество исходных данных',
    'fallback_criterion_data_reason' => 'Оценено по количеству ответов.',
    'fallback_criterion_readiness' => 'Готовность к реализации',
    'fallback_criterion_readiness_reason' => 'Требуется дальнейшая проверка рисков и плана.',
    'fallback_missing_risk' => 'Риски',
    'fallback_missing_plan' => 'План реализации',
    'fallback_missing_constraints' => 'Ограничения',
    'fallback_strengths' => 'Есть исходное описание и карточка понимания идеи.',
    'fallback_weaknesses' => 'Оценка предварительная, потому что AI-провайдер не вернул корректный структурированный ответ.',
    'fallback_growth_factors' => 'Уточнение целей, бюджета, сроков и ответственных повысит качество оценки.',
    'fallback_risk_factors' => 'Недостаточная структурированность данных может исказить оценку потенциала.',
    'fallback_missing_financial' => 'Финансовые ограничения',
    'fallback_missing_success' => 'Критерии успеха',
    'fallback_missing_main_risks' => 'Основные риски',
    'fallback_missing_impl_plan' => 'План внедрения',
    'fallback_improve_score' => 'Ответить на недостающие вопросы и повторить расчет.',
    'fallback_reduce_score' => 'Критичные риски, неясный бюджет или отсутствие ответственных.',
    'fallback_next_action_reason' => 'Не блокировать конвейер анализа, но считать оценку предварительной.',
    'fallback_status_refine_first' => 'Сначала доработать идею',
    'fallback_final_short_verdict' => 'Предварительно: идею стоит доработать и перепроверить анализ.',
    'fallback_final_detailed_verdict' => 'AI-провайдер вернул некорректный JSON на этапе итоговой рекомендации. CRM сохранила безопасную предварительную рекомендацию по уже рассчитанным блокам, чтобы не блокировать работу пользователя.',
    'fallback_final_reason1' => 'Итог сформирован по доступным блокам анализа.',
    'fallback_final_reason2' => 'Нужна повторная генерация для полноценной AI-рекомендации.',
    'fallback_final_negative' => 'Часть AI-ответа не удалось разобрать как валидный JSON.',
    'fallback_final_condition1' => 'Проверить недостающие данные',
    'fallback_final_condition2' => 'Повторить AI-анализ при стабильном ответе провайдера',
    'fallback_validate_goal' => 'Цель',
    'fallback_validate_budget' => 'Бюджет',
    'fallback_validate_timeline' => 'Сроки',
    'fallback_validate_risks' => 'Риски',
    'fallback_validate_responsible' => 'Ответственные',
    'fallback_action_check_card' => 'Проверить карточку понимания',
    'fallback_action_clarify_data' => 'Уточнить недостающие данные',
    'fallback_action_regenerate' => 'Запустить повторную генерацию итоговой рекомендации',
    'fallback_wrong_decision' => 'Решение будет принято на неполных данных.',
    'fallback_missing_ai_response' => 'Полностью корректный структурированный ответ AI-провайдера',
    'fallback_final_summary' => 'Система сохранила предварительную рекомендацию, но для финального решения лучше повторить генерацию после проверки данных.',
    'status_proceed' => 'Можно рассматривать к реализации',
    'status_proceed_with_validation' => 'Можно рассматривать, но сначала проверить гипотезы',
    'status_refine_first' => 'Сначала доработать идею',
    'status_collect_more_data' => 'Сначала собрать недостающие данные',
    'status_postpone' => 'Лучше отложить',
    'status_reject' => 'Не рекомендуется в текущем виде',
    'sm_exact_demand' => 'Точный спрос',
    'sm_budget' => 'Бюджет',
    'sm_competitors' => 'Конкуренты',
    'sm_q1_text' => 'Где вы планируете реализовать эту идею?',
    'sm_q1_reason' => 'Локация определяет рынок, клиентов и затраты.',
    'sm_q1_local' => 'Местный рынок (район/город)',
    'sm_q1_regional' => 'Региональный',
    'sm_q1_national' => 'Вся страна',
    'sm_q2_text' => 'Какой ориентировочный бюджет вы рассматриваете?',
    'sm_q2_reason' => 'Бюджет определяет масштаб и скорость запуска.',
    'sm_q2_minimal' => 'Минимальный (до 150 000 ₽)',
    'sm_q2_small' => 'Небольшой (150 000 – 500 000 ₽)',
    'sm_q2_medium' => 'Средний (500 000 – 2 000 000 ₽)',
    'sm_q3_text' => 'Кто ваша основная целевая аудитория?',
    'sm_q3_reason' => 'Аудитория определяет продукт, цены и продвижение.',
    'sm_q3_individuals' => 'Частные лица',
    'sm_q3_businesses' => 'Бизнес / компании',
    'sm_q3_both' => 'И те и другие',
    'sm_q4_text' => 'Есть ли у вас опыт в этой сфере?',
    'sm_q4_reason' => 'Опыт влияет на сроки, риски и необходимость обучения.',
    'sm_q4_experienced' => 'Да, есть опыт',
    'sm_q4_no_experience' => 'Нет опыта',
    'sm_q4_have_knowledge' => 'Есть теоретические знания',
    'sm_q5_text' => 'Что вас больше всего беспокоит в реализации идеи?',
    'sm_q5_reason' => 'Помогает понять ключевые риски.',
    'sm_not_sure' => 'Пока не знаю',
    'sm_analysis_summary' => 'на стадии проработки.',
    'sm_idea_described' => 'Идея описана',
    'sm_no_detailed_data' => 'Нет детальных данных для Анализа',
    'sm_check_demand' => 'Проверить спрос в выбранной локации',
    'sm_study_competitors' => 'Изучить конкурентов рядом',
    'sm_final_needs_verification' => 'требует проверки перед вложениями.',
    'sm_market_exists' => 'Рынок существует',
    'sm_few_data' => 'Мало данных',
    'sm_check_demand_before' => 'Проверить спрос перед вложениями',
    'sm_insufficient_data' => 'Недостаток данных',
    'sm_without_data_hard' => 'Без данных сложно оценить',
    'sm_answer_questions_action' => 'Ответить на уточняющие вопросы',
    'sm_no_market_analysis' => 'Отсутствие анализа рынка',
    'sm_study_demand_district' => 'Изучить спрос в выбранном районе',
    'sm_demand_exists' => 'Спрос существует',
    'sm_survey_audience' => 'Опрос целевой аудитории',
    'sm_positive_answers' => '>20 положительных ответов',
    'sm_check_competitors_path' => 'Проверить спрос, изучить конкурентов в выбранном районе.',
    'sm_action1' => '1. Изучить конкурентов в радиусе локации.',
    'sm_action2' => '2. Провести опрос жителей района.',
    'sm_action3' => '3. Посчитать минимальный бюджет.',
    'sm_no_demand_data' => 'Нет данных о спросе',
    'sm_check_demand_label' => 'Спрос',
    'sm_check_competitors_label' => 'Конкуренты',
    'enough_data' => 'Данных достаточно',
    'enough_data_for_analysis' => 'Данных достаточно для анализа.',
    'enough_data_can_analyze' => 'Данных достаточно. Можно провести анализ.',
    'prompt_analyst_system' => 'Ты — аналитик идей. Проанализируй данные и найди пробелы.',
    'system_prompt_card' => 'Ты собираешь "Карточку понимания идеи" на основе всей доступной информации.

Твоя задача — НЕ делать финальный анализ идеи. НЕ давать рекомендацию "стоит / не стоит". НЕ составлять бизнес-план.
Собери структурированный профиль текущего понимания идеи для последующего всестороннего анализа.

На вход: исходные данные идеи, описание, все уточняющие вопросы и ответы, раскрытые темы, темы где пользователь ответил "не знаю".

Правила:
1. Используй только предоставленные данные. Не выдумывай факты.
2. Отделяй факты от предположений.
3. "не знаю" / "пока не определился" — это факт: пользователь не определился.
4. Не превращай неопределенный ответ в конкретное значение.
5. Не делай финальный вывод о перспективности.
6. Не давай советов. Не оценивай "стоит ли заниматься".
7. Определи, насколько идея сейчас понятна и каких данных не хватает.
8. Определи ранние риски как предварительные зоны внимания.
9. Определи ключевые факторы для последующего анализа.
10. completeness.overall и confidence_score — числа от 0 до 1.
11. next_step.action: ask_more_questions / start_analysis / preliminary_analysis.
12. ЗАПРЕЩЕНО использовать символы { и } внутри текстовых значений.
13. Если нужны скобки в тексте — используй только ( ) или [ ].

Верни ТОЛЬКО валидный JSON. Без markdown, без комментариев, без текста до или после JSON.

JSON:
{"idea_profile":{"summary":"краткое резюме","idea_type":"business","specificity_level":"medium","known_facts":[],"user_unknowns":[],"missing_facts":[],"assumptions":[],"constraints":[],"early_risks":[],"key_decision_factors":[],"completeness":{"overall":0.5,"goal":0.5,"product_or_service":0.5,"audience":0.5,"region":0.5,"finance":0.5,"timeline":0.5,"operations":0.5,"team":0.5,"market":0.5,"legal":0.5,"risks":0.5},"confidence_score":0.5},"next_step":{"action":"start_analysis","reason":"почему","recommended_missing_topics":[],"can_continue_without_more_questions":true}}',
    'system_prompt_refined_card' => 'Ты уточняешь "Карточку понимания идеи" на основе всех доступных данных.

Ты получишь:
1. existing_understanding_card — карточка, собранная ранее
2. all_questions_and_answers — ВСЕ вопросы и ответы (включая уточнённые, помеченные как уточнённые)
3. Данные идеи

Твоя задача: пересобрать карточку с учётом новых ответов.
- Обнови known_facts на основе ответов.
- Перенеси темы из missing_facts/user_unknowns в known_facts если ответы их закрыли.
- Обнови completeness_score и confidence_score.
- Пересмотри early_risks, constraints, assumptions.
- Определи новый next_step.

Правила:
1. Не делай финальный анализ, не советуй.
2. Используй только предоставленные данные.
3. ЗАПРЕЩЕНО использовать символы { и } внутри текстовых значений.
4. Если нужны скобки в тексте — используй только ( ) или [ ].
5. Верни только JSON, без markdown и без текста до или после JSON.

JSON:
{"idea_profile":{"summary":"краткое резюме","idea_type":"business","specificity_level":"medium","known_facts":[],"user_unknowns":[],"missing_facts":[],"assumptions":[],"constraints":[],"early_risks":[],"key_decision_factors":[],"completeness":{"overall":0.5,"goal":0.5,"product_or_service":0.5,"audience":0.5,"region":0.5,"finance":0.5,"timeline":0.5,"operations":0.5,"team":0.5,"market":0.5,"legal":0.5,"risks":0.5},"confidence_score":0.5},"next_step":{"action":"start_analysis","reason":"почему","recommended_missing_topics":[],"can_continue_without_more_questions":true}}',
    'system_prompt_risk' => 'Ты эксперт по риск-анализу. Оцени риски идеи. Каждый риск: category, probability 1-5, impact 1-5.

ВАЖНЕЙШЕЕ ПРАВИЛО: ЗАПРЕЩЕНО использовать символы { и } в тексте описаний. 
Если в тексте нужны скобки — используй ТОЛЬКО ( ) или [ ].
Любой символ { или } внутри строки "description" или "title" сделает JSON невалидным.
Твой ответ должен быть ТОЛЬКО JSON. Никакого текста до или после. Никаких markdown-ограждений.

JSON:
{"risk_report":{"summary":"Краткая сводка","overall_risk_score":12,"overall_risk_level":"high","risks":[{"title":"Название риска","category":"finance","description":"Описание без символов { или }","probability_score":3,"impact_score":4,"risk_score":12,"risk_level":"high","mitigation_actions":["Действие 1","Действие 2"]}],"recommended_first_actions":["Первое","Второе"]}}',
    'system_prompt_pitfalls' => 'Ты анализируешь идею и выявляешь подводные камни. Для каждого: category, probability 1-5, impact 1-5.

ЗАПРЕЩЕНО использовать { или } в тексте описаний. Только ( ) или [ ].
Твой ответ — ТОЛЬКО JSON, без текста до или после.

JSON:
{"overall_hidden_complexity":"medium","overall_summary":"Краткая сводка","data_confidence":0.7,"pitfalls":[{"title":"Название","category":"finance","description":"Описание без { }","consequence":"Последствие","probability_score":3,"impact_score":3,"hiddenness_score":3,"urgency_score":2,"mitigation_steps":["Шаг 1","Шаг 2"]}]}',
    'system_prompt_plan' => 'Ты составляешь план реализации идеи. Выдели 3-7 этапов, для каждого 2-4 задачи.

ЗАПРЕЩЕНО использовать { или } в тексте описаний. Только ( ) или [ ].
Твой ответ — ТОЛЬКО JSON, без текста до или после.

JSON:
{"implementation_plan":{"summary":"Общее описание","stages":[{"title":"Название этапа","goal":"Цель","description":"Описание","tasks":[{"title":"Задача","description":"Описание","priority":"high","expected_result":"Результат"}]}],"next_7_days":{"summary":"Ближайшие шаги","tasks":[{"title":"Задача","description":"Описание","priority":"high"}]},"recommended_next_action":"Первое действие"}}',
    'system_prompt_final' => 'Ты формируешь итоговую рекомендацию. Опирайся на уже подготовленные блоки, не делай анализ с нуля.

Оцени по шкале 0-100: potential_score, feasibility_score, risk_score, data_completeness_score, plan_quality_score, blocker_score, confidence_score.

Статус: proceed / proceed_with_validation / refine_first / collect_more_data / postpone / reject_current_form.

Правила:
1. ЗАПРЕЩЕНО использовать символы { и } внутри текстовых значений.
2. Если нужны скобки в тексте — используй только ( ) или [ ].
3. Верни только JSON, без markdown и без текста до или после JSON.',
    'system_prompt_project' => 'Создай детальный план проекта с задачами (8-15 штук).

ЗАПРЕЩЕНО использовать { или } в тексте описаний. Только ( ) или [ ].
Твой ответ — ТОЛЬКО JSON, без текста до или после.

JSON:
{"summary":"Обзор проекта","projects":[{"id":"p1","title":"Название проекта","description":"Описание","tasks":[{"id":"t1","title":"Задача","description":"Описание без фигурных скобок","priority":"high","estimated_time":"2-3 часа","expected_outcome":"Результат","subtasks":[{"id":"t1.1","title":"Подзадача","description":"Описание","priority":"high","estimated_time":"1 час","expected_outcome":"Результат"}]}]}]}',
    'default_idea_title' => 'идея',
];
