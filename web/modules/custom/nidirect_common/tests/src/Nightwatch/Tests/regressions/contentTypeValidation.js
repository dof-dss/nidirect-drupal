const fs = require('fs');
var types = [];
const configDir = '../../config/sync';

module.exports = {
  '@tags': [
    'nidirect',
    'nidirect_common',
    'content-type-validation'
  ],

  'Test whether content types found in config folder are present in the UI': browser => {
    browser
      .pause(9000, function () {
        fs.readdirSync(configDir).forEach(file => {
          if (match = file.match(/node.type.(\w+)/)) {
            // Open a readstream to the file.
            var content = fs.readFileSync(configDir + '/' + file, 'utf8');
            // Convert the string with line breaks into an array to ease
            // iteration/regex matching without having to work around single quotes
            // or control characters nestled alongside the values we want to try and extract.
            lines = content.split(/\n/);

            for (var i = 0; i < lines.length; i++) {
              // Drop single quotes as they make word matching/detection much harder.
              lines[i] = lines[i].replace(/'/g,'');
              matches = lines[i].match(/^name: (.+)/);

              if (matches) {
                types.push(matches[1]);
              }
            }
          }
        });

        browser
          .drupalLogin({name: process.env.NW_TEST_USER_PREFIX + '_admin', password: process.env.TEST_PASS})
          .drupalRelativeURL('/admin/structure/types')
          .waitForElementVisible('body', 9000)

        for (var i = 0; i < types.length; i++) {
          var regex_pattern = new RegExp(types[i], 'g');
          browser.expect.element('.region-content table tbody').text.to.match(regex_pattern);
        }
      });
  }

};
