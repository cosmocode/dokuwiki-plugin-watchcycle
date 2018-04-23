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

    const $watchCycleForm = jQuery('<form>');
    const usernameHTML =
        '<div>' +
        '<label for="plugin__watchcycle_user_input">'+ LANG.plugins.watchcycle.label_username + '</label>' +
        '<input id="plugin__watchcycle_user_input" name="watchcycle_user" type="text" required/>' +
        '</div>';
    $watchCycleForm.append(jQuery(usernameHTML));
    const cycleHTML =
        '<div>' +
        '<label for="plugin__watchcycle_cycle_input">'+ LANG.plugins.watchcycle.label_cycle_length + '</label>' +
        '<input id="plugin__watchcycle_cycle_input" name="watchcycle_cycle" type="number" required min="1"/>' +
        '</div>';

    $watchCycleForm.append(cycleHTML);
    $watchCycleForm.append(jQuery('<button type="submit">Submit</button>'));
    const $cancelButton = jQuery('<button type="button">Cancel</button>');
    $cancelButton.on('click', function() {
        $watchCycleForm.get(0).reset();
        pickerClose();
    });
    $watchCycleForm.append($cancelButton);

    $watchCycleForm.on('submit', function (event) {
        event.preventDefault();

        const username = $picker.find('[name="watchcycle_user"]').val();
        const cycle = $picker.find('[name="watchcycle_cycle"]').val();

        pickerInsert('~~WATCHCYCLE:' + username + ':' + cycle + '~~', edid);

        $watchCycleForm.get(0).reset();
    });

    $picker.append($watchCycleForm).append($watchCycleForm);

    // when the toolbar button is clicked
    $btn.on('click', function(event) {
        // open/close the picker
        pickerToggle(pickerid, $btn);
        event.preventDefault();
    });
}
