<?php
declare(strict_types=1);
/** @var array $data */
$slug = (string)($data['form_slug'] ?? '');
$safeSlug = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
?>
<body data-page="public-form" data-protected="0" data-form-slug="<?= $safeSlug ?>">
<div class="crm-app"><div class="crm-main-wrap">
<main class="crm-content crm-module-page pform-public-page">
    <section class="crm-card pform-public-card" aria-labelledby="publicFormHeading">
        <div class="crm-card-body">
            <a class="crm-brand pform-public-brand" href="index.php?route=login"><span class="crm-brand-mark"></span> <?= htmlspecialchars($t('app.name', 'TropaTT'), ENT_QUOTES, 'UTF-8') ?></a>
            <div id="publicFormState" class="alert alert-info mt-4" role="status">Загружаем форму…</div>
            <div id="publicFormContent" hidden>
                <h1 class="h3" id="publicFormHeading"></h1>
                <p class="text-muted" id="publicFormDescription"></p>
                <form id="publicFormSubmission" class="pform-public-fields" novalidate>
                    <div id="publicFormBooking" class="mb-3" hidden>
                        <label class="form-label" for="publicBookingDate">Дата встречи</label>
                        <input class="form-control mb-2" id="publicBookingDate" type="date">
                        <label class="form-label" for="publicBookingSlot">Свободное время</label>
                        <select class="form-select" id="publicBookingSlot" name="_booking_slot"><option value="">Выберите дату</option></select><div class="form-text" id="publicBookingSlotStatus" role="status" aria-live="polite"></div>
                    </div>
                    <div id="publicFormFields"></div>
                    <div class="pform-honeypot" aria-hidden="true"><label for="publicFormWebsite">Website</label><input id="publicFormWebsite" type="text" name="_hp_website" tabindex="-1" autocomplete="off"></div>
                    <div class="form-check my-3"><input class="form-check-input" type="checkbox" value="1" id="publicFormConsent" required><label class="form-check-label" id="publicFormConsentLabel" for="publicFormConsent"></label></div>
                    <button class="btn crm-btn-primary" id="publicFormSubmitBtn" type="submit">Отправить обращение</button>
                </form>
                <div id="publicFormResult" class="mt-3" role="status" aria-live="polite"></div>
            </div>
            <p class="small text-muted mt-4 mb-0">Отправляя форму, вы передаёте данные организации, которая её опубликовала.</p>
        </div>
    </section>
</main></div></div>
