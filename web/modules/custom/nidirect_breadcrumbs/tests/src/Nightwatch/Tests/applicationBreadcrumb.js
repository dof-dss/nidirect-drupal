module.exports = {
  '@tags': ['breadcrumbs', 'breadcrumbs-application'],

  'Test that Application node shows correct breadcrumb pattern': browser => {
    // Log in as an admin user.
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_admin', password: process.env.TEST_PASS })
      .drupalRelativeURL('/admin/content?type=application&status=1')
      .waitForElementVisible('body', 2000)
      .expect.element('.view-id-content table td.views-empty').to.not.be.present;

    // Follow link to first matching item of published content and check it has a breadcrumb.
    // TODO: gather theme/field information first, then compare to rendered trail.
    browser
      .click('.view-id-content table > tbody > tr:nth-child(1) > td.views-field-title > a')
      .waitForElementVisible('body', 2000)
      .expect.element('nav.breadcrumb').to.be.present;
  }

};
