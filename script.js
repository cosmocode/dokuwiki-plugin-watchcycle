/**
 * AJAX request for users and groups
 * Adapted from Struct plugin
 *
 * @param {function} fn Callback on success
 * @param {string} id Call identifier
 * @param {string} param Pass the parameter to backend
 */
function ajax_watchcycle(fn, id, param) {
    let data = {};

    data['call'] = 'plugin_watchcycle_' + id;
    data['param'] = param;

    jQuery.post(DOKU_BASE + 'lib/exe/ajax.php', data, fn, 'json')
        .fail(function (result) {
            if (result.responseJSON) {
                if (result.responseJSON.stacktrace) {
                    console.error(result.responseJSON.error + "\n" + result.responseJSON.stacktrace);
                }
                alert(result.responseJSON.error);
            } else {
                // some fatal error occurred, get a text only version of the response
                alert(jQuery(result.responseText).text());
            }
        });
}

/**
 * Autocomplete split helper
 * @param {string} val
 * @returns {string}
 */
function autcmpl_split(val) {
    return val.split(/,\s*/);
}

/**
 * Autocomplete helper returns last part of comma separated string
 * @param {string} term
 * @returns {string}
 */
function autcmpl_extractLast(term) {
    return autcmpl_split(term).pop();
}

/**
 * Attaches the mechanics on our plugin's button
 *
 * @param {jQuery} $btn the button itself
 * @param {object} props unused
 * @param {string} edid the editor's ID
 * @return {string}
 */
function addBtnActionPlugin_watchcycle($btn, props, edid) {
    'use strict';

    const pickerid = 'picker' + window.pickercounter;
    const $picker = jQuery(createPicker(pickerid, [], edid))
        .attr('aria-hidden', 'true')
        .addClass('plugin-watchcycle')
    ;
    window.pickercounter += 1;
    const l10n = LANG.plugins.watchcycle;
    const $watchCycleForm = jQuery('<form>');
    const usernameHTML =
        '<div>' +
        '<label for="plugin__watchcycle_user_input">' + l10n.label_username + '</label>' +
        '<input id="plugin__watchcycle_user_input" name="watchcycle_user" type="text" required/>' +
        '</div>';
    $watchCycleForm.append(jQuery(usernameHTML));
    const cycleHTML =
        '<div>' +
        '<label for="plugin__watchcycle_cycle_input">' + l10n.label_cycle_length + '</label>' +
        '<input id="plugin__watchcycle_cycle_input" name="watchcycle_cycle" type="number" required min="1"/>' +
        '</div>';

    $watchCycleForm.append(cycleHTML);
    $watchCycleForm.append(jQuery('<button type="submit">' + l10n.button_insert + '</button>'));
    const $cancelButton = jQuery('<button type="button">' + l10n.button_cancel + '</button>');
    $cancelButton.on('click', function () {
        $watchCycleForm.get(0).reset();
        pickerClose();
    });
    $watchCycleForm.append($cancelButton);

    // multi-value autocompletion
    $watchCycleForm.find('input#plugin__watchcycle_user_input').autocomplete({
        source: function (request, cb) {
            ajax_watchcycle(cb, 'get', autcmpl_extractLast(request.term));
        },
        focus: function() {
            // prevent value inserted on focus
            return false;
        },
        select: function(event, ui) {
            const terms = autcmpl_split(this.value);
            // remove the current input
            terms.pop();
            // add the selected item
            terms.push(ui.item.value);
            // add placeholder to get the comma-and-space at the end
            terms.push("");
            this.value = terms.join(", ");
            return false;
        }
    });

    $watchCycleForm.on('submit', function (event) {
        event.preventDefault();
        $picker.find(".error").remove();
        const maintainers = $picker.find('[name="watchcycle_user"]').val().replace(new RegExp("[, ]+?$"), "");


        const cycle = $picker.find('[name="watchcycle_cycle"]').val();

        // validate maintainers
        ajax_watchcycle(function (result) {
            if (result === true) {
                pickerInsert('~~WATCHCYCLE:' + maintainers + ':' + cycle + '~~', edid);
                $watchCycleForm.get(0).reset();
            } else {
                $picker.find("form").append('<div class="error">' + l10n.invalid_maintainers + '</div>');
            }
        }, 'validate', maintainers);

    });

    $picker.append($watchCycleForm).append($watchCycleForm);

    // when the toolbar button is clicked
    $btn.on('click', function (event) {
        // open/close the picker
        pickerToggle(pickerid, $btn);
        event.preventDefault();
    });
}

/**
 * Add watchcycle_only parameter to search tool links if it is in the search query
 *
 * This should ideally be done in the backend, but this is currently (Greebo) not possible. Future DokuWiki release
 * might include "unknown" search parameter, e.g. those from plugins like this one, by default. Then this can be
 * removed.
 */
jQuery(function () {
    const $advancedOptions = jQuery('.search-results-form .advancedOptions');
    if (!$advancedOptions.length) {
        return;
    }

    /**\
     * taken from https://stackoverflow.com/a/31412050/3293343
     * @param param
     * @return {*}
     */
    function getQueryParam(param) {
        location.search.substr(1)
            .split("&")
            .some(function(item) { // returns first occurence and stops
                return item.split("=")[0] === param && (param = item.split("=")[1])
            });
        return param
    }

    if (getQueryParam('watchcycle_only') === '1') {
        $advancedOptions.find('a').each(function (index, element) {
            const $link = jQuery(element);
            $link.attr('href', $link.attr('href') + '&watchcycle_only=1');
        });
    }
});
