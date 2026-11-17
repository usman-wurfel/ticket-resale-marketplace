$(document).ready(function () {
  'use strict';

  // Ensure Select2 dropdown is attached to the correct container.
  // Some pages (like edit forms) might not have #createModal, so fallback to the closest modal or body.
  function getSelect2DropdownParent($select) {
    const $closestModal = $select.closest('.modal');
    if ($closestModal && $closestModal.length) return $closestModal;
    const $createModal = $('#createModal');
    if ($createModal && $createModal.length) return $createModal;
    return $(document.body);
  }

  $('body').on('change', '.country_select', function () {
    $('.request-loader').addClass('show');
    let country_id = $(this).val();

    $.ajax({
      url: getStateUrl,
      type: 'get',
      data: { country_id: country_id },
      success: function (res) {
        $('.request-loader').removeClass('show');
        if (res.states == true) {
          $('.state_div').show();
          let $stateSelect = $('.state_select');
          $stateSelect.empty().trigger('change');

          $stateSelect.select2({
            dropdownParent: getSelect2DropdownParent($stateSelect),
            placeholder: 'Select Country',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
              url: stateUrl,
              dataType: 'json',
              delay: 250,
              data: function (params) {
                return {
                  search: params.term || '',
                  page: params.page || 1,
                  country: $('select[name="country_id"]').val() || '',
                  lang: $('input[name="language_id"]').val() ?? $('select[name="language_id"]').val()
                };
              },
              processResults: function (data) {
                return {
                  results: data.results.map(function (item) {
                    return {
                      text: item.name,
                      id: item.id
                    };
                  }),
                  pagination: { more: data.more }
                };
              },
              cache: true
            }
          });
        }
      }
    });
  });


  $('.countryDropdown, .country_select').each(function () {
    const $select = $(this);
    // Prevent double-init conflicts from other scripts.
    if ($select.hasClass('select2-hidden-accessible')) {
      try {
        $select.select2('destroy');
      } catch (e) {
        // ignore
      }
    }

    const hasLocalOptions = $select.find('option').length > 1; // ignore placeholder option

    // If countries are already rendered server-side, enable local search (no AJAX).
    if (hasLocalOptions) {
      $select.select2({
        dropdownParent: getSelect2DropdownParent($select),
        placeholder: 'Select Country',
        allowClear: true,
        minimumInputLength: 0,
        minimumResultsForSearch: 0,
        width: '100%'
      });
      return;
    }

    // Fallback: if there are no local options, use AJAX search.
    $select.select2({
      dropdownParent: getSelect2DropdownParent($select),
      placeholder: 'Select Country',
      allowClear: true,
      minimumInputLength: 0,
      minimumResultsForSearch: 0,
      ajax: {
        url: countryUrl,
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            search: params.term || '',
            page: params.page || 1,
            lang: $('input[name="language_id"]').val(),
          };
        },
        processResults: function (data) {
          return {
            results: data.results.map(function (item) {
              return {
                text: item.name,
                id: item.id
              };
            }),
            pagination: { more: data.more }
          };
        },
        cache: true
      },
      width: '100%'
    });
  });

  $('.stateDropdown').each(function () {
    const $state = $(this);
    $state.select2({
      dropdownParent: getSelect2DropdownParent($state),
      placeholder: 'Select State',
      allowClear: true,
      minimumInputLength: 0,
      ajax: {
        url: stateUrl,
        dataType: 'json',
        delay: 250,
        data: function (params) {
          return {
            search: params.term || '',
            page: params.page || 1,
            lang: $('select[name="language_id"]').val(),
            country: $('select[name="country_id"]').val()
          };
        },
        processResults: function (data) {
          return {
            results: data.results.map(function (item) {
              return {
                text: item.name,
                id: item.id
              };
            }),
            pagination: { more: data.more }
          };
        },
        cache: true
      }
    });
  });
});
