/*
 * School closures results filtering
 *
 * Add a search input to allow users to filter the list of school closures.
 * No jquery - using pure javascript for performance.
 *
 * Neil Blair 02/07/2018
 */

var scMin = 20,                       // enable filtering if school closures exceed this number
    scContainerID = 'school-closure-results', // id of element containing school closure items
    scItemsClass = 'columnItem',      // class of school closure item containers
    scTagToFilter = 'h3',             // tag in each item we want to filter on
    scParent,
    scResults,
    scResultsProcessed = [],
    scForm, scFormLabel, scFilter,
    scFilterProcessor,
    scProcessStarted = false,
    scProcessBusy = false,
    scAriaAlert, scStatusDefault;

scParent = document.getElementById(scContainerID);
scResults = scParent.getElementsByClassName(scItemsClass);
scStatusDefault = '<span class="element-invisible">Showing </span><span class="count">' + scResults.length + '</span> school ';
// plural closures in status?
(scResults.length != 1) ? scStatusDefault += "closures" : scStatusDefault += "closure";

// enable result filter when listing more than n results
if (scResults.length > scMin) {

  var elem, text, transliterated;

  // Get all the searchable text and clean it up (lowercase, no punctuation, etc)
  for (var i = 0; i < scResults.length; i++) {
    elem = scResults[i].getElementsByTagName(scTagToFilter)[0];
    text = scCleanText(elem.innerText);
    transliterated = "";
    scResultsProcessed[i] = { "text" : text };
    if (elem.hasAttribute("data-transliterated")) {
      transliterated = scCleanText(elem.getAttribute("data-transliterated"));
    }
    scResultsProcessed[i] = {
      "text" : text,
      "transliterated" : transliterated
    };
  }

  /* Add filter form into DOM above the list of closures */

  // need a form
  scForm = document.createElement("form");
  scForm.setAttribute("id", "sc-form");
  scForm.onsubmit = function (event) {
    event.preventDefault();
    scParent.focus(); // keyboarders hitting submit will set focus on list of closures
  }
  scForm.setAttribute("role", "search");

  // need a title/label - decided to use H2 rather than fieldset/legend
  scFormLabel = document.createElement("h2");
  scFormLabel.innerText = "Search schools";

  // need an input to enter search filters
  scFilter = document.createElement("input");
  scFilter.setAttribute("id", "sc-filter");
  scFilter.setAttribute("type", "search");
  scFilter.setAttribute("class", "form-text");
  scFilter.setAttribute("autocomplete", "off");
  scFilter.setAttribute("placeholder", "Enter a school name ...");
  scFilter.setAttribute("aria-label", "Enter the name of school and press return or enter.");
  scFilter.setAttribute("maxlength", "128");
  scFilter.onfocus = function () {
    // make sure first result visible in browser viewport (so user can see things change as they type)
    if (scResults[0].getBoundingClientRect().top >= window.innerHeight) {
      scResults[0].scrollIntoView(false);
    }
  };
  scFilter.oninput = function () {
    // start filter processing when something is input and stop when no input
    if (this.value.length >= 1 && !scProcessStarted) {
      scProcessFilterStart();
    } else if (this.value.length == 0) {
      //(scProcessBusy)? setTimeout(scReset, 2000) : scReset();
      setTimeout(scReset, 500)
    }
  };
  scFilter.onblur = function () {
    // stop the filter processing when focus leaves input
    clearInterval(scFilterProcessor);
  };

  // aria alert for filter matches - will be announced every time its contents are changed
  scAriaAlert = document.createElement("h2");
  scAriaAlert.setAttribute("id", "sc-aria-alert");
  scAriaAlert.setAttribute("aria-live", "assertive");  // alert user when results are updated (politely - i.e. when the user is idle)
  scAriaAlert.setAttribute("aria-atomic", "false");  // read out results as a complete unit when updated
  scAriaAlert.setAttribute("tabindex", "0");
  scAriaAlert.innerHTML = scStatusDefault;

  // now assemble and append everything to the DOM
  scForm.appendChild(scFormLabel);
  scForm.appendChild(scFilter);
  scParent.parentNode.insertBefore(scAriaAlert, scParent);
  scParent.parentNode.insertBefore(scForm, scAriaAlert);

  // make sure when keyboarders tab out of the search input, they end up focused on results
  scParent.setAttribute('tabindex', '0');

  // make sure screen readers announce focus on search results
  scParent.setAttribute('aria-label', 'Search results');

}

function scProcessFilterStart() {

  scProcessStarted = true; // every time search filter input gets focus, we attempt to start the filter processing unless it has already started ...

  var filter, i, result, count, plural, newstatus = '';

  // process filter input every so often ...
  scFilterProcessor = setInterval(function () {

    // but only if its not busy
    if (!scProcessBusy) {

      scProcessBusy = true; // now its busy

      count = 0; // number of search results matching current filter input

      // grab query text
      filter = scCleanText(document.getElementById('sc-filter').value);

      // Loop through all closure items, find schools whose names contain matches to the filter
      for (i = 0; i < scResultsProcessed.length; i++) {
        result = scResultsProcessed[i];
        if (scMatch(filter, result.text)||scMatch(filter, result.transliterated)) {
          count++;
          scResults[i].style.display = "";
        } else {
          scResults[i].style.display = "none";
        }
      }

      // How many matches are we showing?
      plural = (count != 1) ? "s" : "";
      if (filter.length > 0) {
        newstatus = '<span class="element-invisible">Showing </span><span class="count">' + count + "</span> school closure" + plural;
      } else {
        newstatus = scStatusDefault;
      }

      // Update the status (if has changed) after a brief delay
      if (scAriaAlert.innerHTML !== newstatus) {
        scAriaAlert.innerHTML = newstatus;
        scAriaAlert.classList.add('blink');
      } else {
        //scAriaAlert.classList.remove('blink');
      }

      scProcessBusy = false;

    }

  }, 1000);
}

function scReset() {

  /* reset everything - clear all filter processing and show all closures again */

  clearInterval(scFilterProcessor);
  scProcessStarted = false;

  // make sure all results displayed and status is correct
  for (i = 0; i < scResults.length; i++) {
    scResults[i].style.display = "";
  }
  //scAriaAlert.classList.remove('blink');
  scAriaAlert.innerHTML = scStatusDefault;

}


function scCleanText(text) {

  /*
   * Returns text cleansed to remove punctuation, double-spacing, etc
   * useful for text comparisons between entered search filter and items to be searched
   */

  var cleansed = text.toLowerCase().trim();

  // CET think it may be common for people to search for "allsaints" instead of "allsaint" ...
  cleansed = cleansed.replace("allsaint", "all saint");

  // normalise "st." and "saint" to "st "
  cleansed = cleansed.replace(/\bst\.|\bsaint(?!s)/gu, "st ");

  // remove any other non-letter/space characters
  cleansed = cleansed.replace(/[^A-Za-zÀ-ÿ0-9\s]/gu, "");

  // replace double spaces with single space
  cleansed = cleansed.replace(/\s{2,}/g, ' ');

  //console.log('scCleanText(' + text + ') returns "' + cleansed +'"');

  return cleansed;
}

function scMatch(needle, haystack) {

  var arrNeedle = needle.split(" ");
  var pattern;
  var matches = 0;

  for (var i = 0; i < arrNeedle.length; i++) {
    pattern = '\\b' + arrNeedle[i];
    if (haystack.search(new RegExp(pattern, 'g')) >= 0) {
      matches++;
    }
  }

  if (matches >= arrNeedle.length) {
    return true;
  }

  return false;
}
