<?php
declare(strict_types=1);

return [
    'list' => 'Ideas list',
    'created' => 'Idea created successfully',
    'updated' => 'Idea updated',
    'deleted' => 'Idea deleted',
    'voted' => 'Vote recorded',
    'unvoted' => 'Vote removed',
    'status_updated' => 'Idea status updated',
    'decomposed' => 'Tasks generated',
    'questions_generated' => 'Questions generated',
    'answers_saved' => 'Answers saved',
    'analysis_retried' => 'Analysis retried',
    'title_required' => 'Idea title is required',
    'notif_idea_created_title' => 'Idea created',
    'notif_idea_created_body' => 'Your idea',
    'notif_idea_created_body2' => 'has been successfully created.',
    'notif_idea_voted_title' => 'New vote for idea',
    'notif_idea_voted_body' => 'A user voted for your idea',
    'notif_idea_status_changed_title' => 'Idea status changed',
    'notif_idea_status_changed_body1' => 'Status of your idea',
    'notif_idea_status_changed_body2' => 'changed to',
    'current_state' => 'Current state',
    'data_sufficient_click_analyze' => 'Sufficient data. Click "Run Analysis".',
    'answer_questions_click_send' => 'Answer clarification questions and click "Submit".',
    'questions_formed_safe_mode' => 'Questions formed (safe mode)',
    'answer_for_quality_analysis' => 'For quality analysis, please answer the clarification questions.',
    'ai_service_unavailable' => 'AI service temporarily unavailable. Please try again later.',
    'analysis_completed' => 'Analysis completed',
    'answer_questions_then_send' => 'Answer clarification questions and click "Submit".',
    'data_sufficient_can_analyze' => 'Sufficient data. Analysis can be run.',
    'ai_provider_not_responding' => 'AI provider is not responding. Please try again later.',
    'data_sufficient' => 'Sufficient data',
    'data_sufficient_for_analysis' => 'Sufficient data for analysis.',
    'no_new_questions_remaining' => 'No new clarification questions remaining. Analysis can be started.',
    'no_new_questions_can_analyze' => 'No new clarification questions remaining. Analysis can be run.',
    'need_clarification' => 'Clarification needed',
    'answers_saved_need_clarification' => 'Answers saved. Clarification needed.',
    'answers_saved_need_more_clarification' => 'Answers saved. Additional clarification needed.',
    'answers_saved_label' => 'Answers saved',
    'answers_saved_can_analyze' => 'Answers saved. Analysis can be run.',
    'refine_failed' => 'Failed to process answers. Please try again.',
    'debug_logs_cleared' => 'AI logs cleared',
    'answer_dont_know' => 'DON\'T KNOW',
    'no_answer' => 'no answer',
    'ai_analyst_system_prompt' => 'You are an ideas analyst. Analyze the data and find gaps.',
    'ai_analysis_failed' => 'AI was unable to run the analysis',
    'ai_card_failed' => 'AI was unable to build the card. Please try again later.',
    'card_first_required' => 'First build the idea understanding card.',
    'ai_gaps_failed' => 'AI was unable to find gaps',
    'ai_refine_card_failed' => 'AI was unable to refine the card.',
    'ai_potential_failed' => 'AI was unable to calculate potential.',
    'ai_risk_fallback_summary' => 'AI was unable to complete risk analysis. Try another AI provider for more accurate results.',
    'ai_risks_failed' => 'AI was unable to calculate risks.',
    'ai_pitfalls_fallback_summary' => 'AI was unable to complete analysis.',
    'ai_pitfalls_failed' => 'AI was unable to find hidden pitfalls.',
    'ai_plan_fallback_summary' => 'AI was unable to complete the plan.',
    'ai_plan_failed' => 'AI was unable to build the plan.',
    'ai_recommendation_failed' => 'AI was unable to generate a recommendation.',
    'ai_tasks_fallback_summary' => 'AI was unable to generate tasks.',
    'ai_tasks_failed' => 'AI was unable to suggest tasks.',
    'generate_tasks_first' => 'First generate the suggested tasks.',
    'no_tasks_to_create' => 'No tasks to create.',
    'project_label' => 'Project',
    'task_label' => 'Task',
    'project_created' => 'Project created',
    'project_created_tasks' => 'tasks',
    'interview_cleared' => 'Question and answer history cleared',
    'interview_limit_reached' => 'Question limit reached (25)',
    'ai_questions_generation_failed' => 'AI was unable to generate questions. Please try again later.',
    'no_answers_to_save' => 'No answers to save',
    'option_not_sure' => 'Not sure yet',
    'option_custom_answer' => 'Custom answer',
    'analysis_reset_failed' => 'Failed to reset AI analysis.',
    'analysis_reset' => 'AI analysis reset. You can start again.',
    'analysis_reset_message' => 'Old AI analysis data cleared. Click "Run" for a new analysis.',
    'analysis_not_ready_status' => 'Analysis can only be started from ready_for_analysis status.',
    'answer_all_questions_first' => 'Answer all clarification questions before starting the analysis.',
    'demo_analysis' => 'Demo analysis (safe mode)',
    'demo_mode_message' => 'Demo mode. For real AI analysis, disable safe_mode.',
    'analysis_completed_label' => 'Analysis completed',
    'analysis_complete_full' => 'Full analysis ready.',
    'analysis_complete_partial' => 'Completed',
    'analysis_complete_of' => 'of',
    'analysis_complete_steps' => 'steps.',
    'unknown_step_key' => 'Unknown step_key.',
    'analysis_step_done' => 'Analysis step completed',
    'empty_analysis_result' => 'Empty result_json for analysis step.',
    'plan_title' => 'Implementation Plan',
    'tasks_created_count' => 'tasks',
    'fallback_card_summary' => 'The idea is not yet described in sufficient detail. The card was built from available CRM data.',
    'fallback_fact_title' => 'Idea title:',
    'fallback_fact_description' => 'Idea description:',
    'fallback_category' => 'Category',
    'fallback_product' => 'Product',
    'fallback_region' => 'Region',
    'fallback_target_date' => 'Target date',
    'fallback_missing_goal' => 'Implementation goal and success criteria',
    'fallback_missing_audience' => 'Target audience or users',
    'fallback_missing_budget' => 'Budget and financial constraints',
    'fallback_missing_timeline' => 'Timeline and critical dates',
    'fallback_missing_team' => 'Team and responsible persons',
    'fallback_missing_legal' => 'Legal or operational constraints',
    'fallback_missing_risks' => 'Key risks and dependencies',
    'fallback_early_risks' => 'AI provider returned incorrect JSON, so the card was built from CRM facts and needs verification at the next steps.',
    'fallback_factor_goal' => 'Goal',
    'fallback_factor_timeline' => 'Timeline',
    'fallback_factor_budget' => 'Budget',
    'fallback_factor_responsible' => 'Responsible persons',
    'fallback_factor_impl_risks' => 'Implementation risks',
    'fallback_factor_business_effect' => 'Expected business effect',
    'fallback_next_step_reason' => 'Available data is sufficient to not block further analysis stages; clarifications can be gathered later.',
    'fallback_idea_label' => 'idea',
    'fallback_potential_verdict' => 'Preliminary assessment: the idea can be further analyzed, but the result requires verification due to incomplete AI provider response.',
    'fallback_potential_summary' => 'Potential calculated from available understanding card and user answers. AI provider returned incorrect JSON, so the assessment is not final.',
    'fallback_criterion_clarity' => 'Idea clarity',
    'fallback_criterion_clarity_reason' => 'Assessed by understanding card completeness.',
    'fallback_criterion_business' => 'Business relevance',
    'fallback_criterion_business_reason' => 'Assessed by idea description.',
    'fallback_criterion_data' => 'Source data quality',
    'fallback_criterion_data_reason' => 'Assessed by number of answers.',
    'fallback_criterion_readiness' => 'Implementation readiness',
    'fallback_criterion_readiness_reason' => 'Requires further risk and plan verification.',
    'fallback_missing_risk' => 'Risks',
    'fallback_missing_plan' => 'Implementation plan',
    'fallback_missing_constraints' => 'Constraints',
    'fallback_strengths' => 'Has an original description and idea understanding card.',
    'fallback_weaknesses' => 'Assessment is preliminary because the AI provider did not return a correct structured response.',
    'fallback_growth_factors' => 'Clarifying goals, budget, timeline and responsible persons will improve assessment quality.',
    'fallback_risk_factors' => 'Insufficient data structuring may distort the potential assessment.',
    'fallback_missing_financial' => 'Financial constraints',
    'fallback_missing_success' => 'Success criteria',
    'fallback_missing_main_risks' => 'Key risks',
    'fallback_missing_impl_plan' => 'Implementation plan',
    'fallback_improve_score' => 'Answer missing questions and recalculate.',
    'fallback_reduce_score' => 'Critical risks, unclear budget or missing responsible persons.',
    'fallback_next_action_reason' => 'Do not block the analysis pipeline, but consider the assessment preliminary.',
    'fallback_status_refine_first' => 'Refine the idea first',
    'fallback_final_short_verdict' => 'Preliminary: the idea should be refined and the analysis re-verified.',
    'fallback_final_detailed_verdict' => 'AI provider returned incorrect JSON at the final recommendation stage. CRM saved a safe preliminary recommendation based on already calculated blocks to not block the user.',
    'fallback_final_reason1' => 'Result formed from available analysis blocks.',
    'fallback_final_reason2' => 'Re-generation is needed for a full AI recommendation.',
    'fallback_final_negative' => 'Part of the AI response could not be parsed as valid JSON.',
    'fallback_final_condition1' => 'Verify missing data',
    'fallback_final_condition2' => 'Re-run AI analysis when the provider responds stably',
    'fallback_validate_goal' => 'Goal',
    'fallback_validate_budget' => 'Budget',
    'fallback_validate_timeline' => 'Timeline',
    'fallback_validate_risks' => 'Risks',
    'fallback_validate_responsible' => 'Responsible persons',
    'fallback_action_check_card' => 'Check the understanding card',
    'fallback_action_clarify_data' => 'Clarify missing data',
    'fallback_action_regenerate' => 'Re-run final recommendation generation',
    'fallback_wrong_decision' => 'Decision will be made on incomplete data.',
    'fallback_missing_ai_response' => 'Fully correct structured AI provider response',
    'fallback_final_summary' => 'The system saved a preliminary recommendation, but for the final decision it is better to re-generate after verifying the data.',
    'status_proceed' => 'Can proceed to implementation',
    'status_proceed_with_validation' => 'Can proceed, but validate hypotheses first',
    'status_refine_first' => 'Refine the idea first',
    'status_collect_more_data' => 'Collect missing data first',
    'status_postpone' => 'Better to postpone',
    'status_reject' => 'Not recommended in current form',
    'sm_exact_demand' => 'Exact demand',
    'sm_budget' => 'Budget',
    'sm_competitors' => 'Competitors',
    'sm_q1_text' => 'Where do you plan to implement this idea?',
    'sm_q1_reason' => 'Location determines the market, customers and costs.',
    'sm_q1_local' => 'Local market (district/city)',
    'sm_q1_regional' => 'Regional',
    'sm_q1_national' => 'Nationwide',
    'sm_q2_text' => 'What approximate budget are you considering?',
    'sm_q2_reason' => 'Budget determines the scale and speed of launch.',
    'sm_q2_minimal' => 'Minimal (up to 150,000 ₽)',
    'sm_q2_small' => 'Small (150,000 – 500,000 ₽)',
    'sm_q2_medium' => 'Medium (500,000 – 2,000,000 ₽)',
    'sm_q3_text' => 'Who is your main target audience?',
    'sm_q3_reason' => 'Audience determines the product, pricing and promotion.',
    'sm_q3_individuals' => 'Individuals',
    'sm_q3_businesses' => 'Businesses / Companies',
    'sm_q3_both' => 'Both',
    'sm_q4_text' => 'Do you have experience in this field?',
    'sm_q4_reason' => 'Experience affects timelines, risks and training needs.',
    'sm_q4_experienced' => 'Yes, I have experience',
    'sm_q4_no_experience' => 'No experience',
    'sm_q4_have_knowledge' => 'I have theoretical knowledge',
    'sm_q5_text' => 'What concerns you most about implementing this idea?',
    'sm_q5_reason' => 'Helps identify key risks.',
    'sm_not_sure' => 'Not sure yet',
    'sm_analysis_summary' => 'is in the development stage.',
    'sm_idea_described' => 'Idea is described',
    'sm_no_detailed_data' => 'No detailed data for analysis',
    'sm_check_demand' => 'Check demand in the selected location',
    'sm_study_competitors' => 'Study nearby competitors',
    'sm_final_needs_verification' => 'requires verification before investment.',
    'sm_market_exists' => 'Market exists',
    'sm_few_data' => 'Limited data',
    'sm_check_demand_before' => 'Check demand before investment',
    'sm_insufficient_data' => 'Insufficient data',
    'sm_without_data_hard' => 'Hard to assess without data',
    'sm_answer_questions_action' => 'Answer clarification questions',
    'sm_no_market_analysis' => 'No market analysis',
    'sm_study_demand_district' => 'Study demand in the selected district',
    'sm_demand_exists' => 'Demand exists',
    'sm_survey_audience' => 'Survey the target audience',
    'sm_positive_answers' => '>20 positive responses',
    'sm_check_competitors_path' => 'Check demand, study competitors in the selected district.',
    'sm_action1' => '1. Study competitors within the location radius.',
    'sm_action2' => '2. Survey district residents.',
    'sm_action3' => '3. Calculate the minimum budget.',
    'sm_no_demand_data' => 'No demand data',
    'sm_check_demand_label' => 'Demand',
    'sm_check_competitors_label' => 'Competitors',
    'enough_data' => 'Enough data',
    'enough_data_for_analysis' => 'Enough data for analysis.',
    'enough_data_can_analyze' => 'Enough data. Analysis can proceed.',
    'prompt_analyst_system' => 'You are an idea analyst. Analyze the data and find gaps.',
    'system_prompt_card' => 'You are building an "Idea Understanding Card" based on all available information.

Your task is NOT to do a final idea analysis. NOT to give a recommendation "worth it / not worth it". NOT to create a business plan.
Collect a structured profile of the current understanding of the idea for subsequent comprehensive analysis.

Input: original idea data, description, all clarification questions and answers, covered topics, topics where the user answered "don\'t know".

Rules:
1. Use only the provided data. Do not invent facts.
2. Separate facts from assumptions.
3. "don\'t know" / "not yet decided" — this is a fact: the user has not decided.
4. Do not turn an unclear answer into a specific value.
5. Do not make a final conclusion about viability.
6. Do not give advice. Do not evaluate "whether it\'s worth doing".
7. Determine how well the idea is currently understood and what data is missing.
8. Identify early risks as preliminary areas of attention.
9. Identify key factors for subsequent analysis.
10. completeness.overall and confidence_score — numbers from 0 to 1.
11. next_step.action: ask_more_questions / start_analysis / preliminary_analysis.
12. FORBIDDEN to use { and } symbols inside text values.
13. If brackets are needed in text — use only ( ) or [ ].

Return ONLY valid JSON. No markdown, no comments, no text before or after JSON.

JSON:
{"idea_profile":{"summary":"brief summary","idea_type":"business","specificity_level":"medium","known_facts":[],"user_unknowns":[],"missing_facts":[],"assumptions":[],"constraints":[],"early_risks":[],"key_decision_factors":[],"completeness":{"overall":0.5,"goal":0.5,"product_or_service":0.5,"audience":0.5,"region":0.5,"finance":0.5,"timeline":0.5,"operations":0.5,"team":0.5,"market":0.5,"legal":0.5,"risks":0.5},"confidence_score":0.5},"next_step":{"action":"start_analysis","reason":"reason","recommended_missing_topics":[],"can_continue_without_more_questions":true}}',
    'system_prompt_refined_card' => 'You are refining the "Idea Understanding Card" based on all available data.

You will receive:
1. existing_understanding_card — card collected earlier
2. all_questions_and_answers — ALL questions and answers (including clarified ones marked as clarified)
3. Idea data

Your task: rebuild the card incorporating new answers.
- Update known_facts based on answers.
- Move topics from missing_facts/user_unknowns to known_facts if answers covered them.
- Update completeness_score and confidence_score.
- Review early_risks, constraints, assumptions.
- Determine the new next_step.

Rules:
1. Do not do a final analysis, do not advise.
2. Use only the provided data.
3. FORBIDDEN to use { and } symbols inside text values.
4. If brackets are needed in text — use only ( ) or [ ].
5. Return only JSON, no markdown and no text before or after JSON.

JSON:
{"idea_profile":{"summary":"brief summary","idea_type":"business","specificity_level":"medium","known_facts":[],"user_unknowns":[],"missing_facts":[],"assumptions":[],"constraints":[],"early_risks":[],"key_decision_factors":[],"completeness":{"overall":0.5,"goal":0.5,"product_or_service":0.5,"audience":0.5,"region":0.5,"finance":0.5,"timeline":0.5,"operations":0.5,"team":0.5,"market":0.5,"legal":0.5,"risks":0.5},"confidence_score":0.5},"next_step":{"action":"start_analysis","reason":"reason","recommended_missing_topics":[],"can_continue_without_more_questions":true}}',
    'system_prompt_risk' => 'You are a risk analysis expert. Assess the idea risks. Each risk: category, probability 1-5, impact 1-5.

MOST IMPORTANT RULE: FORBIDDEN to use { and } symbols in description text.
If brackets are needed in text — use ONLY ( ) or [ ].
Any { or } symbol inside "description" or "title" string will make JSON invalid.
Your response must be ONLY JSON. No text before or after. No markdown fences.

JSON:
{"risk_report":{"summary":"Brief summary","overall_risk_score":12,"overall_risk_level":"high","risks":[{"title":"Risk name","category":"finance","description":"Description without { or }","probability_score":3,"impact_score":4,"risk_score":12,"risk_level":"high","mitigation_actions":["Action 1","Action 2"]}],"recommended_first_actions":["First","Second"]}}',
    'system_prompt_pitfalls' => 'You are analyzing the idea and identifying hidden pitfalls. For each: category, probability 1-5, impact 1-5.

FORBIDDEN to use { or } in description text. Only ( ) or [ ].
Your response is ONLY JSON, no text before or after.

JSON:
{"overall_hidden_complexity":"medium","overall_summary":"Brief summary","data_confidence":0.7,"pitfalls":[{"title":"Name","category":"finance","description":"Description without { }","consequence":"Consequence","probability_score":3,"impact_score":3,"hiddenness_score":3,"urgency_score":2,"mitigation_steps":["Step 1","Step 2"]}]}',
    'system_prompt_plan' => 'You are creating an implementation plan for the idea. Identify 3-7 stages, 2-4 tasks each.

FORBIDDEN to use { or } in description text. Only ( ) or [ ].
Your response is ONLY JSON, no text before or after.

JSON:
{"implementation_plan":{"summary":"General description","stages":[{"title":"Stage name","goal":"Goal","description":"Description","tasks":[{"title":"Task","description":"Description","priority":"high","expected_result":"Result"}]}],"next_7_days":{"summary":"Next steps","tasks":[{"title":"Task","description":"Description","priority":"high"}]},"recommended_next_action":"First action"}}',
    'system_prompt_final' => 'You are forming the final recommendation. Rely on already prepared blocks, do not analyze from scratch.

Rate on a 0-100 scale: potential_score, feasibility_score, risk_score, data_completeness_score, plan_quality_score, blocker_score, confidence_score.

Status: proceed / proceed_with_validation / refine_first / collect_more_data / postpone / reject_current_form.

Rules:
1. FORBIDDEN to use { and } symbols inside text values.
2. If brackets are needed in text — use only ( ) or [ ].
3. Return only JSON, no markdown and no text before or after JSON.',
    'system_prompt_project' => 'Create a detailed project plan with tasks (8-15 total).

FORBIDDEN to use { or } in description text. Only ( ) or [ ].
Your response is ONLY JSON, no text before or after.

JSON:
{"summary":"Project overview","projects":[{"id":"p1","title":"Project name","description":"Description","tasks":[{"id":"t1","title":"Task","description":"Description without curly braces","priority":"high","estimated_time":"2-3 hours","expected_outcome":"Result","subtasks":[{"id":"t1.1","title":"Subtask","description":"Description","priority":"high","estimated_time":"1 hour","expected_outcome":"Result"}]}]}]}',
    'default_idea_title' => 'idea',
    'tasks_created' => 'Aufgaben erstellt',
    'dont_know_yet' => 'Weiß noch nicht',
];
