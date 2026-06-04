(function ($, Drupal, once) {
  Drupal.behaviors.prisonerPaymentsValidateMethods = {
    attach: function (context, settings) {

      if (typeof $.validator === 'undefined') {
        console.warn('jQuery Validate is not available. Skipping prisonerPaymentsValidateMethods.');
        return;
      }

      // Add validation methods once globally for the page.
      once('prisonerPaymentsValidateMethods', 'html').forEach(function (element) {

        // Custom method for valid characters in names
        $.validator.addMethod("fullNameValidCharacters", function (value, element) {
          const regex = /^[\p{Script=Latin}\-.'\s]+$/u;
          return this.optional(element) || regex.test(value);
        }, "Name must contain letters (A–Z), hyphens (-), periods (.), apostrophes (') and spaces only.");

        // Custom method to check if both first and last names are provided
        $.validator.addMethod("firstAndLastName", function (value, element) {
          const nameParts = value.split(" ").map(part => part.replace(/[^\p{Script=Latin} ]/ug, '').trim()).filter(part => part !== "");
          return this.optional(element) || nameParts.length >= 2;
        }, "Name must include first and last name separated by a space.");

      });

      // Apply jquery validation methods once to individual elements.
      const $prisonerPaymentAmount = $(once('prisonerPaymentAmount', 'form input[name="prisoner_payment_amount"]', context));
      const $visitorFullname = $(once('visitorFullName', 'form input[name="visitor_fullname"]', context));
      const $prisonerFullname = $(once('prisonerFullName', 'form input[name="prisoner_fullname"]', context));

      if ($prisonerPaymentAmount.length) {

        // Ensure prisonerMaxAmount rounded to 2 decimal places.
        const prisonerMaxAmount = Math.round(
          parseFloat(settings.prisonerPayments.prisonerMaxAmount ?? 0) * 100
        ) / 100;

        $prisonerPaymentAmount.rules("add", {
          min: {
            param: 0.01
          },
          max: {
            param: prisonerMaxAmount
          },
          messages: {
            min: "Amount must be more than &pound;0",
            max: "Amount must be &pound;" + prisonerMaxAmount + " or less"
          }
        });
      }

      if ($visitorFullname.length) {
        $visitorFullname.rules("add", {
          fullNameValidCharacters: true,
          firstAndLastName: true,
          messages: {
            fullNameValidCharacters: "Your name must contain letters (A–Z), hyphens (-), periods (.), apostrophes (') and spaces only.",
            firstAndLastName: "Your name must include first and last name separated by a space."
          }
        });
      }

      if ($prisonerFullname.length) {
        $prisonerFullname.rules("add", {
          fullNameValidCharacters: true,
          firstAndLastName: true,
          messages: {
            fullNameValidCharacters: "Prisoner name must contain letters (A–Z), hyphens (-), periods (.), apostrophes (') and spaces only.",
            firstAndLastName: "Prisoner name must include first and last name separated by a space."
          }
        });
      }

    }
  }
})(jQuery, Drupal, once);
