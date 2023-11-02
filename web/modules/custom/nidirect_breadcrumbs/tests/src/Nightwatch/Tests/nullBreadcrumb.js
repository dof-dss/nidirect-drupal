module.exports = {
  '@tags': ['breadcrumbs', 'breadcrumbs-null'],

  'Test that key routes/paths do not show a breadcrumb': browser => {
    const routes = {
      'feedback-form': '/form/site-feedback',
      // 'entity.node.webform.test_form': '',
      'nidirect_news.news_listing': '/news',
      // 'entity.user.canonical': '',
      'user.register': '/user/register',
      'user.login': '/user/login',
      // 'user.logout': '/user/logout',
      // 'user.pass': '/user/password',
      'user.reset': '/user/password',
      // 'user.reset.login': '',
      'nidirect_contacts.default': '/contacts',
      'nidirect_contacts.letter': '/contacts/letter/a'
    };

    for (let routeId in routes) {
      const path = routes[routeId];

      console.log('Checking route ' + routeId + ' with path ' + path);
      browser.drupalRelativeURL(path).expect.element('nav.breadcrumb').to.not.be.present;
    }

  }

};
