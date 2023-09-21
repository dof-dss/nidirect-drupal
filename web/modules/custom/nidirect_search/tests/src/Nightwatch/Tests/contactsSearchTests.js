module.exports = {
  '@tags': ['search'],

  'Spelling suggestions for contacts search': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    browser
      .waitForElementVisible('body', 1000)
      .drupalRelativeURL('/contacts?query_contacts_az=marne')
      .expect.element('.sapi-did-you-mean').text.to.contain('Did you mean');
    // Swapping previous test to check for string 'marine' to a presence test
    // because the Solr suggestions can vary over time as the index grows, breaking
    // the tests defined here in a more static manner.
    browser.expect.element('.sapi-did-you-mean > a').to.be.present;

  },

  'Confirm search results on: contacts search page': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Contacts search.
    browser
      .drupalRelativeURL('/contacts?query_contacts_az=services')
      .waitForElementVisible('body', 1000)
      .expect.element('h2.view--count').to.be.present;

  },

};
