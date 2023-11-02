module.exports = {
  '@tags': ['nidirect'],

  'Test login': browser => {
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_admin', password: process.env.TEST_PASS });

    browser
      .drupalRelativeURL('/gp/add')
      .expect.element('h1.page-title')
      .text.to.contain('Add GP');

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
      .text.to.contain('Cold weather payment');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Contact');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Driving instructor');

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
      .text.to.contain('Feature');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Featured content list');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('GP practice');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Health condition');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Health condition: alternative');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Landing page');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Link');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('News');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Page');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Publication');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Telephone Lookup');

    browser
      .drupalRelativeURL('/node/add')
      .expect.element('ul.admin-list')
      .text.to.contain('Webform');

    browser
      .drupalRelativeURL('/admin/content/scheduled')
      .expect.element('h1.page-title')
      .text.to.contain('Scheduled Content');

    browser
      .drupalRelativeURL('/admin/content/media')
      .expect.element('h1.page-title')
      .text.to.contain('Media');

    browser
      .drupalRelativeURL('/admin/content/media')
      .expect.element('div.region-content')
      .text.to.contain('Add media');

  }

};
