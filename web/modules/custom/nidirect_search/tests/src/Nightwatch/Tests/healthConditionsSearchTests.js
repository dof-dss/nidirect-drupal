module.exports = {
  '@tags': ['search'],

  'Confirm search results on: health conditions search page': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Health conditions: search term (A-Z uses db search).
    browser
      .drupalRelativeURL('/services/health-conditions-a-z?query_health_az=cough')
      .waitForElementVisible('body', 1000)
      .expect.element('.view-display-id-search_page .view-empty').not.to.be.present;

  },

  'Spelling suggestions for health conditions search': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    browser
      .waitForElementVisible('body', 1000)
      .drupalRelativeURL('/services/health-conditions-a-z?query_health_az=cancre')
      .expect.element('.sapi-did-you-mean').text.to.contain('Did you mean');
    browser.expect.element('.sapi-did-you-mean > a').text.to.contain('cancer');

  },

};
