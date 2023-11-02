let newsContentTitles = [];
let homepageFeaturedTitles = [];

module.exports = {
  '@tags': ['regression'],

  before: browser => {
    // Gather the titles of the current 'latest/featured news' content.
    browser.drupalRelativeURL('/news');
    browser.elements('css selector', '.card .card__title', function (result) {
      for (let item in result.value) {
        browser.elementIdText(result.value[item].ELEMENT, function (result) {
          newsContentTitles.push(result.value);
        });
      }
    });

    // Gather the titles of the current featured items on the homepage.
    browser.drupalRelativeURL('/');
    browser.elements('css selector', '.card--featured .card__title', function (result) {
      for (let item in result.value) {
        browser.elementIdText(result.value[item].ELEMENT, function (result) {
          homepageFeaturedTitles.push(result.value);
        });
      }
    });
  },

  '[Regression] D8NID-812: ensure featured content list render array switch for news does not affect homepage': browser => {
    // Check if the two arrays match, or not.
    console.log('Featured news items: ' + newsContentTitles.join(', '));
    console.log('Homepage featured content items: ' + homepageFeaturedTitles.join(', '));

    browser.assert.notEqual(newsContentTitles, homepageFeaturedTitles, 'Featured news items and featured homepage items should not match');
  }

};
