<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'terms';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'terms';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main id="main-content">
  <div class="page">
    <section class="hero">
      <div class="hero-content">
        <span class="eyebrow"><?= hobc_tpe($pageId, 'hero.eyebrow') ?></span>
        <h1><?= hobc_tpe($pageId, 'hero.title') ?></h1>
        <p><?= hobc_tpe($pageId, 'hero.lead') ?></p>
      </div>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.agreement_to_these_terms.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.agreement_to_these_terms.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.agreement_to_these_terms.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.use_at_your_own_risk.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.use_at_your_own_risk.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.use_at_your_own_risk.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.no_financial_advice.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.no_financial_advice.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.no_financial_advice.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.accounts_and_security.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.accounts_and_security.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.accounts_and_security.p2') ?></p>
      <p><?= hobc_tpe($pageId, 'section.accounts_and_security.p3') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.custodial_wallet_terms.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.custodial_wallet_terms.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.custodial_wallet_terms.p2') ?></p>
      <p><?= hobc_tpe($pageId, 'section.custodial_wallet_terms.p3') ?></p>
      <p><?= hobc_tpe($pageId, 'section.custodial_wallet_terms.p4') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.sms_program_terms.title') ?></h3>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p1') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p2') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p3') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p4') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p5') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p6') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_program_terms.p7') ?></p>
      <p><?= hobc_tpe($pageId, 'section.sms_program_terms.p8') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.mining_and_pool_use.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.mining_and_pool_use.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.mining_and_pool_use.p2') ?></p>
      <p><?= hobc_tpe($pageId, 'section.mining_and_pool_use.p3') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.explorer_stats_reserve_and_burn_data.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.explorer_stats_reserve_and_burn_data.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.explorer_stats_reserve_and_burn_data.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.availability_and_maintenance.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.availability_and_maintenance.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.availability_and_maintenance.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.prohibited_uses.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.prohibited_uses.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.prohibited_uses.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.support_and_abuse.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.support_and_abuse.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.support_and_abuse.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.third_party_services.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.third_party_services.p1') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.disclaimers_and_limitation_of_liability.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.disclaimers_and_limitation_of_liability.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.disclaimers_and_limitation_of_liability.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.changes_to_these_terms.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.changes_to_these_terms.p1') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.contact.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.contact.p1') ?></p>
      <div class="actions"><a class="button primary" href="/contact/?section=Terms"><?= hobc_tpe($pageId, 'section.contact.link1') ?></a><a class="button" href="/privacy/"><?= hobc_tpe($pageId, 'section.contact.link2') ?></a></div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
