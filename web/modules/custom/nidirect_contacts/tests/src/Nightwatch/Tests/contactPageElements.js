module.exports = {
  '@tags': ['nidirect-contacts'],

  'A-Z block is not present on search term results page': browser => {
    browser
      .drupalRelativeURL('/contacts?query_contacts_az=test')
      .expect.element('#contacts-az')
      .to.not.be.present;
  },

  'Search by relevancy link is not present on default search by term page': browser => {
    browser
      .drupalRelativeURL('/contacts?query_contacts_az=test')
      .expect.element('.view-contacts')
      .text.to.not.contain('Sort by relevancy');
  },

  'Contacts by letter does not use a pager': browser => {
    browser
      .drupalRelativeURL('/contacts/letter/a')
      .expect.element('.view-contacts-a-z nav.pager')
      .to.not.be.present;
  },

  'Contacts by letter confirms: "Showing entries for A"': browser => {
    browser
      .expect.element('#contacts-az--title')
      .text.to.contain('Showing entries for A');
  },

  'Contacts by letter displays Reset search convenience link': browser => {
    browser
      .expect.element('#contact-links')
      .text.to.contain('reset a-z');
  },

  'Contacts by letter displays Show A-Z convenience link': browser => {
    browser
      .expect.element('#contact-links')
      .text.to.contain('show search');
  },

  'Contacts by letter does not show any sorting links': browser => {
    browser
      .expect.element('#contact-links')
      .text.to.not.match(/sort .+/);
  },

  'Contact landing page should not show any sorting or convenience links': browser => {
    browser
      .drupalRelativeURL('/contacts')
      .expect.element('#contact-links')
      .to.not.be.present;
  },

  'Contact landing page should show the A-Z block': browser => {
    browser
      .expect.element('#contacts-az')
      .to.be.present;
  },

  'Contact landing page should show the A-Z block': browser => {
    browser
      .expect.element('#contacts-az')
      .to.be.present;
  },

  'Contact search SORT BY TITLE should hide link to sort by title': browser => {
    browser
      .drupalRelativeURL('/contacts?query_contacts_az=test&sort_by=title')
      .expect.element('.view-contacts .view-links')
      .text.to.not.contain('Sort by title');
  },

  'Contact search SORT BY RELEVANCY should hide link to sort by relevancy': browser => {
    browser
      .drupalRelativeURL('/contacts?query_contacts_az=test&sort_by=search_api_relevance')
      .expect.element('.view-contacts .view-links')
      .text.to.not.contain('Sort by relevancy');
  },

};
