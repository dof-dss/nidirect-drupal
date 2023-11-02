module.exports = {
  '@tags': ['breadcrumbs'],

  'Test that the Search page does not show a breadcrumb': browser => {
    browser
      .pause(2000, function () {
        browser.drupalRelativeURL('/search').waitForElementVisible('body', 2000);
        browser.expect.element('nav.breadcrumb').to.not.be.present;
      })
  }

};
