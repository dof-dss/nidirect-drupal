module.exports = {
  '@tags': ['search'],

  'Spelling suggestions for site search': browser => {

    let spellingCorrections = {
      'covod': 'covid',
      'benfit': 'benefit',
      'medcal': 'medical',
      'belfst': 'belfast',
    };

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Go through our spelling mistakes and check we get a suggestion we expect/have seen before.
    for (let typo in spellingCorrections) {
      const suggestion = spellingCorrections[typo];

      browser
        .waitForElementVisible('body', 1000)
        .drupalRelativeURL('/search?query=' + typo)
        .expect.element('.sapi-did-you-mean').text.to.contain('Did you mean');
      browser.expect.element('.sapi-did-you-mean > a').text.to.contain(suggestion);
    }

  },

  'Confirm search results on: site search page': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Site search.
    browser
      .drupalRelativeURL('/search?query=nidirect')
      .waitForElementVisible('body', 1000)
      .expect.element('h2.view--count').to.be.present;

  },

};
