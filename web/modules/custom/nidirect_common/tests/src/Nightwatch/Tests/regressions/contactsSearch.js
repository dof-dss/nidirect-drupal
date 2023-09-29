module.exports = {
  '@tags': ['regression'],

  before: function (browser) {
    // Resize default window size.
    browser.resizeWindow(1600, 2048);
    browser.drupalLogout();
  },

  '[Regression] D8NID-835: Ensure that searches for short phrases do not throw keyword length errors': browser => {

    // View the 'help' term page. It should show both a menu link item and not show 'Page not found'.
    for (const term of ['G9', 'Go Kids Go', 'A Wee Job']) {
      browser.drupalRelativeURL('/contacts?query_contacts_az=' + term)
        .expect.element('.form-item--error-message').to.not.be.present;
    }

  }

};
