<?php
require_once __DIR__ . '/../app/i18n.php';
hobc_i18n_bootstrap();
$pageId = 'privacy';
$pageTitle = hobc_tp($pageId, 'meta.title');
$pageDescription = hobc_tp($pageId, 'meta.description');
$activePage = 'privacy';
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
      <h3><?= hobc_tpe($pageId, 'section.overview.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.overview.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.overview.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.information_we_collect.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.information_we_collect.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.information_we_collect.p2') ?></p>
      <p><?= hobc_tpe($pageId, 'section.information_we_collect.p3') ?></p>
      <p><?= hobc_tpe($pageId, 'section.information_we_collect.p4') ?></p>
      <p><?= hobc_tpe($pageId, 'section.information_we_collect.p5') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.how_we_use_information.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.how_we_use_information.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.how_we_use_information.p2') ?></p>
      <p><?= hobc_tpe($pageId, 'section.how_we_use_information.p3') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.custodial_wallet_notice.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.custodial_wallet_notice.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.custodial_wallet_notice.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.sms_and_email_communications.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.sms_and_email_communications.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.sms_and_email_communications.p2') ?></p>
      <p><?= hobc_tp($pageId, 'section.sms_and_email_communications.p3') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.no_third_party_marketing_sharing.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.no_third_party_marketing_sharing.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.no_third_party_marketing_sharing.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.cookies_sessions_and_security_logs.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.cookies_sessions_and_security_logs.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.cookies_sessions_and_security_logs.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.data_retention.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.data_retention.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.data_retention.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.user_choices.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.user_choices.p1') ?></p>
      <p><?= hobc_tpe($pageId, 'section.user_choices.p2') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.children.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.children.p1') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.changes_to_this_policy.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.changes_to_this_policy.p1') ?></p>
    </section>

    <section class="card">
      <h3><?= hobc_tpe($pageId, 'section.contact.title') ?></h3>
      <p><?= hobc_tpe($pageId, 'section.contact.p1') ?></p>
      <div class="actions"><a class="button primary" href="/contact/?section=Privacy"><?= hobc_tpe($pageId, 'section.contact.link1') ?></a><a class="button" href="/terms/"><?= hobc_tpe($pageId, 'section.contact.link2') ?></a></div>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
