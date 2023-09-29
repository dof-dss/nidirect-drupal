module.exports = {
  '@tags': ['regression'],

  before: function (browser) {
    // Resize default window size.
    browser.resizeWindow(1600, 2048);
  },

  '[Regression] D8NID-1388: Ensure that taxonomy lists are purged when a landing page theme/subtheme value changes': browser => {

    // Login as an editor.
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_editor', password: process.env.TEST_PASS })
      .drupalRelativeURL('/node/add/landing_page');

    // Create a new landing page, take over Environment and the outdoors theme (field_subtheme)
    // and also appear as a subtheme under 'Travel transport and roads' (field_site_themes).
    browser
      .setValue('input#edit-title-0-value', ['A new landing page [regression test for D8NID-1388]', browser.Keys.TAB])
      .pause(2000)
      .setValue('input#edit-field-teaser-0-value', 'D8NID-1388.')
      .click('select[id="edit-field-subtheme-shs-0-0"]')
      .click('select[id="edit-field-subtheme-shs-0-0"] option[value="26"]')
      .click('select[id="edit-field-site-themes-shs-0-0"]')
      .click('select[id="edit-field-site-themes-shs-0-0"] option[value="15"]')
      .click('select[id="edit-moderation-state-0-state"] option[value="published"]')
      .click('input#edit-submit');

    // Go to theme page - the landing page should take over and no taxonomy list should show.
    browser.drupalRelativeURL('/')
      .click('xpath', '//*[@id="main-content"]//a/h3[normalize-space()="Environment and the outdoors"]')
      .expect.element('.list-content ul').not.to.be.present;

    // Go to the subtheme page - the node should appear in the list.
    browser.drupalRelativeURL('/')
      .click('xpath', '//*[@id="main-content"]//a/h3[normalize-space()="Travel, transport and roads"]')
      .expect.element('.list-content ul').to.be.present;

    // Go back and edit the landing page to change the theme and subtheme value.
    // Theme: Education
    // Subtheme: Family, home and community
    browser.drupalRelativeURL('/admin/content')
      .click('xpath', '//table[contains(@class,"views-table")]//tr[1]//td[6]//a[text()="Edit"]')
      .click('select[id="edit-field-subtheme-shs-0-0"]')
      .click('select[id="edit-field-subtheme-shs-0-0"] option[value="19"]')
      .click('select[id="edit-field-site-themes-shs-0-0"]')
      .click('select[id="edit-field-site-themes-shs-0-0"] option[value="20"]')
      .click('select[id="edit-moderation-state-0-state"] option[value="published"]')
      .click('input#edit-submit');

    // Has the last theme page reverted back to a regular taxonomy listing?
    browser.drupalRelativeURL('/')
      .click('xpath', '//*[@id="main-content"]//a/h3[normalize-space()="Environment and the outdoors"]')
      .expect.element('.list-content ul').to.be.present;
    // Has the landing page node disappeared from the taxo listing on the subthenme page?
    browser.drupalRelativeURL('/')
      .click('xpath', '//*[@id="main-content"]//a/h3[normalize-space()="Travel, transport and roads"]')
      .expect.element('.list-content ul').text.not.to.contain('D8NID-1388');

    // Has the new theme page lost its taxonomy listing as the landing page takes over?
    browser.drupalRelativeURL('/')
      .click('xpath', '//*[@id="main-content"]//a/h3[normalize-space()="Education"]')
      .expect.element('.list-content ul').not.to.be.present;
    // Does the node now appear in the subtheme taxo listing?
    browser.drupalRelativeURL('/')
      .click('xpath', '//*[@id="main-content"]//a/h3[normalize-space()="Family, home and community"]')
      .expect.element('.list-content ul').text.to.contain('D8NID-1388');

  }

};
