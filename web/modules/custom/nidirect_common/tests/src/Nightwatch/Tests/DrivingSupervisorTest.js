module.exports = {
  '@tags': ['nidirect'],

  'Test login': browser => {
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_driving_super', password: process.env.TEST_PASS });

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('h1.page-title')
      .text.to.contain('Create Driving instructor');

    browser
      .drupalRelativeURL('/gp/add')
      .expect.element('h1.page-title')
      .text.to.contain('Access denied');

    browser
      .drupalRelativeURL('/admin/content')
      .expect.element('h1.page-title')
      .text.to.contain('Content');

    browser
      .drupalRelativeURL('/admin/content/scheduled')
      .expect.element('h1.page-title')
      .text.to.contain('Scheduled Content');

    browser
      .drupalRelativeURL('/admin/content/media')
      .expect.element('h1.page-title')
      .text.to.contain('Access denied');

    browser
      .drupalRelativeURL('/admin/content/drafts')
      .expect.element('h1.page-title')
      .text.to.contain('My Drafts');

    browser
      .drupalRelativeURL('/admin/content/all-drafts')
      .expect.element('h1.page-title')
      .text.to.contain('All drafts');

    browser
      .drupalRelativeURL('/admin/content/needs-review')
      .expect.element('h1.page-title')
      .text.to.contain('Needs Review');

    browser
      .drupalRelativeURL('/admin/content/needs-audit')
      .expect.element('h1.page-title')
      .text.to.contain('Needs Audit');

  }

};
