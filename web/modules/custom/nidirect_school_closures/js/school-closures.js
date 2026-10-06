/*
 * School closures results filtering
 *
 * Add a search input to allow users to filter the list of school closures.
 * No jquery - using pure javascript for performance.
 *
 * Neil Blair 02/07/2018
 * Updated: 21/09/2026 - Support for new API.
 */

const scMin = 20,                              // enable filtering if school closures exceed this number.
  scContainerID = 'school-closure-results',     // id of element containing all school closure records.
  scItemsClass = 'school-closure',              // class for each individual school closure.
  scTitleClass = 'school-closure__name',        // class for element containing school title.
  scLocationClass = 'school-closure__location'; // class for element containing school location.

let scParent,
  scResults,
  scResultsProcessed = [],
  scForm, scFormLabel, scFilter,
  scAriaAlert, scStatusDefault;

scParent = document.getElementById(scContainerID);
scResults = scParent.getElementsByClassName(scItemsClass);
scStatusDefault = '<span class="element-invisible">Showing </span><span class="count">' + scResults.length + '</span> ';
(scResults.length !== 1) ? scStatusDefault += "schools" : scStatusDefault += "school";

// enable result filter when listing more than n results
if (scResults.length > scMin) {

  let result, name, location, nameText, locationText, transliterated;

  for (let i = 0; i < scResults.length; i++) {
    result = scResults[i];
    name = result.getElementsByClassName(scTitleClass)[0];
    location = result.getElementsByClassName(scLocationClass)[0];

    nameText = scCleanText(name.innerText);
    locationText = location ? scCleanText(location.innerText) : '';
    transliterated = '';

    if (name.hasAttribute("data-transliterated")) {
      transliterated = scCleanText(name.getAttribute("data-transliterated"));
    }

    scResultsProcessed[i] = {
      "name" : nameText,
      "location" : locationText,
      "transliterated" : transliterated
    };
  }

  // Create search form.
  scForm = document.createElement("form");
  scForm.setAttribute("id", "sc-form");
  scForm.addEventListener('submit', function (event) {
    event.preventDefault();
  });
  scForm.setAttribute("role", "search");

  scFormLabel = document.createElement("label");
  scFormLabel.innerText = "Search by school or town";
  scFormLabel.setAttribute("for", "sc-filter");
  scFormLabel.setAttribute("id", "sc-form-label");

  scFilter = document.createElement("input");
  scFilter.setAttribute("id", "sc-filter");
  scFilter.setAttribute("type", "search");
  scFilter.setAttribute("class", "form-text");
  scFilter.setAttribute("autocomplete", "off");
  scFilter.setAttribute("maxlength", "128");
  scFilter.setAttribute("size", "50");

  scFilter.addEventListener('input', function () {
    scProcessFilter(this.value);
  });

  // aria alert for filter matches - will be announced every time its contents are changed
  scAriaAlert = document.createElement("p");
  scAriaAlert.setAttribute("id", "sc-aria-alert");
  scAriaAlert.setAttribute("role", "status");
  scAriaAlert.innerHTML = scStatusDefault;

  // now assemble and append everything to the DOM
  scForm.appendChild(scFormLabel);
  scForm.appendChild(scFilter);
  scParent.parentNode.insertBefore(scAriaAlert, scParent);
  scParent.parentNode.insertBefore(scForm, scAriaAlert);
}

function scUpdateStatus(count, filter) {
  let plural, status;
  plural = (count !== 1) ? "s" : "";
  if (filter.length > 0) {
    status = '<span class="element-invisible">Showing </span><span class="count">' + count + "</span> school" + plural;
  } else {
    status = scStatusDefault;
  }
  if (scAriaAlert.innerHTML !== status) {
    scAriaAlert.innerHTML = status;
  }
}

function scProcessFilter(value) {
  const filter = scCleanText(value);
  let count = 0;

  for (let i = 0; i < scResultsProcessed.length; i++) {
    let result = scResultsProcessed[i];

    if (
      !filter ||
      scMatch(filter, result.name) ||
      scMatch(filter, result.location) ||
      scMatch(filter, result.transliterated)
    ) {
      scResults[i].hidden = false;
      count++;
    }
    else {
      scResults[i].hidden = true;
    }
  }

  scUpdateStatus(count, filter);
}

function scCleanText(text) {

  /*
   * Returns text cleansed to remove punctuation, double-spacing, etc
   * useful for text comparisons between entered search filter and items to be searched
   */

  let cleansed = text.toLowerCase().trim();

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
  const arrNeedle = needle.split(' ');

  for (let i = 0; i < arrNeedle.length; i++) {
    const pattern = '\\b' + arrNeedle[i];

    if (haystack.search(new RegExp(pattern, 'g')) < 0) {
      return false;
    }
  }

  return true;
}
