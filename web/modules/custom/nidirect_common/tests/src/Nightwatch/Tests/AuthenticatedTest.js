module.exports = {
  '@tags': ['nidirect'],

  'Test login': browser => {
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_authenticated', password: process.env.TEST_PASS });

    browser
      .drupalRelativeURL('/gp/add')
      .expect.element('h1.page-title')
      .text.to.contain('Access denied');

    browser
      .drupalRelativeURL('/admin/content/scheduled')
      .expect.element('h1.page-title')
      .text.to.contain('Access denied');

  }

};
