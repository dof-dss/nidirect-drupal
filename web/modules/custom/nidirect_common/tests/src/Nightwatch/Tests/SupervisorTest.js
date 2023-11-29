module.exports = {
  '@tags': ['nidirect'],

  'Test login': browser => {
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_super', password: process.env.TEST_PASS });

    browser
      .drupalRelativeURL('/gp/add')
      .expect.element('h1.page-title')
      .text.to.contain('Access denied');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Application');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Article');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Cold weather payment');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Contact');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Driving instructor');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Embargoed publication');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('External link');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Feature');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Featured content list');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('GP practice');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Health condition');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Health condition: alternative');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Landing page');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Link');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('News');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Page');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Publication');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Telephone Lookup');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.not.contain('Webform');

    browser
      .drupalRelativeURL('/admin/content/scheduled')
      .expect.element('h1.page-title')
      .text.to.contain('Scheduled Content');

  }

};
