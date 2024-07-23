function initAutocomplete(formId = '') {
  let autocomplete;
  let address1Field = document.querySelector(`${formId}-address-line1`);

  // Create a bounding box that roughly wraps the martime provinces
  const defaultBounds = {
    north: 48.1,
    south: 43.25,
    west: -69.5,
    east: -59.5,
  };

  // Create the autocomplete object, restricting the search predictions to
  // addresses in Canada.
  autocomplete = new google.maps.places.Autocomplete(address1Field, {
    bounds: defaultBounds,
    componentRestrictions: { country: ["ca"] },
    fields: ["address_components"],
    types: ["address"],
    strictBounds: true,
  });
  // address1Field.focus();

  // When the user selects an address from the drop-down, populate the
  // address fields in the form.
  autocomplete.addListener("place_changed", function() {
    fillInAddress(autocomplete, formId);
  });
}

function fillInAddress(autocomplete, formId) {

  // Get the place details from the autocomplete object.
  const place = autocomplete.getPlace();
  let address1 = "";

  // Capture address fields for init
  let address1Field = document.querySelector(`${formId}-address-line1`);
  let address2Field = document.querySelector(`${formId}-address-line2`);

  // Get each component of the address from the place details,
  // and then fill-in the corresponding field on the form.
  for (const component of place.address_components) {
    // @ts-ignore remove once typings fixed
    const componentType = component.types[0];

    switch (componentType) {
      case "street_number": {
        address1 = `${component.long_name} ${address1}`;
        break;
      }

      case "route": {
        address1 += component.short_name;
        break;
      }

      case "postal_code": {
        (document.querySelector(`${formId}-postal-code`)).value = `${component.long_name}`;
        break;
      }

      case "locality":
        (document.querySelector(`${formId}-locality`)).value =
        component.long_name;
        break;

      case "administrative_area_level_1": {
        (document.querySelector(`${formId}-administrative-area`)).value =
          component.short_name;
        break;
      }
    }
  }

  address1Field.value = address1;
  address2Field.focus();
}

initAutocomplete('#edit-field-pickup-address-0-address');
initAutocomplete('#edit-field-destination-address-0-address');
