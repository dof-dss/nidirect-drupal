module.exports = {
  '@tags': ['regression'],

  before: function (browser) {
    // Resize default window size.
    browser.resizeWindow(1600, 2048);
    browser.drupalLogout();
  },

  '[Regression] D8NID-847: Ensure that anonymous users can view taxonomy canonical page content': browser => {

    // View the 'help' term page. It should show a menu link item for this.
    browser.drupalRelativeURL('/taxonomy/term/402');

    const termName = 'Help';

    browser.expect.element('nav ul.nav-menu > li:nth-child(4)').text.to.equal(termName);

  }

};
