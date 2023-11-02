module.exports = {
  '@tags': ['regression'],

  'Visually hidden page titles on homepage and campaign pages': browser => {

    const pathsWithoutPageTitles = [
      '/',
      '/campaigns/state-pension',
    ];

    for (let path in pathsWithoutPageTitles) {
      // Todo: Swap to .visible once campaign pages are implemented/themed.
      // https://nightwatchjs.org/api/expect/#expect-visible
      browser.drupalRelativeURL(path).expect.element('h1.page-title').to.not.be.present;
    }

  }

};
