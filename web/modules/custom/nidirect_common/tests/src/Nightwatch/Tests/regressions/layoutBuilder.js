module.exports = {
  '@tags': ['regression'],

  before: function (browser) {
    // Resize default window size.
    browser.resizeWindow(1600, 2048);
  },

  '[Regression] D8NID-836: Check that clientside validation module does not trigger on layout builder block form': browser => {

    // Login as an editor.
    browser
      .drupalLogin({ name: process.env.NW_TEST_USER_PREFIX + '_editor', password: process.env.TEST_PASS })
      .drupalRelativeURL('/node/add/landing_page');


    // Create a published landing page, with obscure/deep theme so it doesn't clash with an existing one.
    // We'll choose 'Help' here... tid 402.
    browser
      .setValue('input#edit-title-0-value', ['Test landing page [regression test for D8NID-836]', browser.Keys.TAB])
      .pause(2000)
      .setValue('input#edit-field-teaser-0-value', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.')
      .click('select[id="edit-field-subtheme-shs-0-0"]')
      .click('select[id="edit-field-subtheme-shs-0-0"] option[value="402"]')
      .click('select[id="edit-moderation-state-0-state"] option[value="published"]')
      .click('input#edit-submit');

    // Should now be on the node display page. Open the sidebar.
    browser.pause(2000).click('.moderation-sidebar-toolbar-tab.toolbar-tab > a');

    // Click the Layout button link. We're selecting by order of button appearance;
    // would be better to find some way of reading the link text value instead.
    browser.click('xpath', "//*[@class=\"moderation-sidebar-secondary-tasks\"]//a[text()='Layout']");

    // Now at /node/NID/layout, need to add a section.
    browser.click('#layout-builder a.layout-builder__link--add');

    // Click the 'Cards standard x 3' option in the sidebar.
    browser.useXpath();
    browser.expect.element('//a[contains(@href, "teasers_x3")]').text.to.equal('Cards standard x3');
    browser.click('//a[contains(@href, "teasers_x3")]');
    browser.useCss();

    // Populate the Admin label with something.
    browser.setValue('form.layout-builder-configure-section input[data-drupal-selector="edit-layout-settings-label"]', 'Regression test for D8NID-836');
    browser.click('form.layout-builder-configure-section input[data-drupal-selector="edit-actions-submit"]');

    // Add a block.
    browser.click('section[data-region="one"] > .layout-builder__add-block a.layout-builder__link--add');
    browser.click('#drupal-off-canvas .inline-block-create-button');
    // Click the 'Card - standard' link.
    browser.useXpath();
    browser.expect.element('//a[contains(@href, "inline_block%3Acard_standard")]').text.to.equal('Card - standard');
    browser.click('//a[contains(@href, "inline_block%3Acard_standard")]');
    browser.useCss();

    // If we can see the media browser, then we aren't getting any clientside JS validation errors blocking our progress.
    browser.click('#layout-builder-modal .js-media-library-open-button');
    browser.expect.element('form.js-media-library-add-form').to.be.visible;

  }

};
