module.exports = {
  '@tags': ['search'],

  'Confirm search results on: GP search page': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // GP search.
    browser
      .drupalRelativeURL('/services/gp-practices?search_api_views_fulltext=surgery')
      .waitForElementVisible('body', 1000)
      .expect.element('h2.view--count').to.be.present;

  },

  'GP search using GP name returns results': browser => {

    let gpName = 'Dr Norma McIlmoyle';

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Search GPs by a known GP entity title.
    browser
      .drupalRelativeURL('/services/gp-practices?search_api_views_fulltext=' + gpName.replace(' ', '+'))
      .waitForElementVisible('body', 1000)
      .expect.element('.list--gp-practice-members').text.to.contain(gpName);
  },

  'GP search by postcode prefix returns meaningful results': browser => {

    let searchTerms = {
      'BT44': 'The Frocess Medical Centre',
      'BT1': 'Dr. Crossin (and|&) Partners',
      'BT20': 'Ashley Medical Centre - Dr. Craig (and|&) Partners',
    }

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    for (let term in searchTerms) {
      const surgeryName = searchTerms[term];
      const regex = new RegExp(surgeryName);

      browser
        .drupalRelativeURL('/services/gp-practices?search_api_views_fulltext=' + term)
        .waitForElementVisible('body', 1000)
        .expect.element('.search-results--gp-practice .card h3 > a').text.to.match(regex);
    }

  },

  'GP search by full postcode returns meaningful results': browser => {

    let searchTerms = {
      'BT44 9LF': 'The Frocess Medical Centre',
      'BT1 2JR': 'Dr. Crossin (and|&) Partners',
      'BT20 5PE': 'Ashley Medical Centre - Dr. Craig (and|&) Partners',
    }

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    for (let term in searchTerms) {
      const surgeryName = searchTerms[term];
      const regex = new RegExp(surgeryName);

      browser
        .drupalRelativeURL('/services/gp-practices?search_api_views_fulltext=' + term.replace(' ', '+'))
        .waitForElementVisible('body', 1000)
        .expect.element('.search-results--gp-practice .card h3 > a').text.to.match(regex);
    }

  },

  'GP search by non-NI postcode shows zero results': browser => {

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Search GPs by a known GP entity title.
    browser
      .drupalRelativeURL('/services/gp-practices?search_api_views_fulltext=SW1A+1AA')
      .waitForElementVisible('body', 1000)
      .expect.element('.search-results--gp-practice .view--count').text.to.contain('0 GP practices');

  },

  'GP search: geospatial distance calculation working': browser => {

    const searchTerm = 'BT37';
    const surgeryName = 'Notting Hill Medical Practice';
    const knownDistance = '1.5 miles';

    // Resize default window size.
    browser.resizeWindow(1600, 1024);

    // Search GPs by a known GP entity title.
    browser
      .drupalRelativeURL('/services/gp-practices?search_api_views_fulltext=' + searchTerm)
      .waitForElementVisible('body', 1000)
      .expect.element('.search-results--gp-practice .card').text.to.contain(surgeryName);
    browser.expect.element('.search-results--gp-practice .card:nth-child(1) > h3 > small').text.to.contain(knownDistance);

  },

};
