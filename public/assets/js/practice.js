/* Behaviour for /practice. Elements are found once, before challenge mode
   may rename their ids, so the page keeps working either way. */
$(function () {
    var $name = $('[name="enter-name"]');
    var $displayed = $('[name="show-hide"]');

    $('#select-class-example input').autocomplete({
        minLength: 2,
        source: function (request, response) {
            var term = request.term.toLowerCase();
            var matches = window.PRACTICE_COUNTRIES.filter(function (c) { return c.toLowerCase().indexOf(term) !== -1; });
            Chaos.later(function () { response(matches); }, 300, 1500);
        }
    });

    $('#openwindow').on('click', function () {
        window.open('/practice/window?from=window', 'practiceWindow', 'width=1100,height=600,top=100,left=100,scrollbars=yes,resizable=yes');
    });

    $('#alertbtn').on('click', function () {
        alert('Hello ' + $name.val() + ', share this practice page and share your knowledge');
        $name.val('');
    });

    $('#confirmbtn').on('click', function () {
        var accepted = confirm('Hello ' + $name.val() + ', Are you sure you want to confirm?');
        $('#confirm-result').text(accepted ? 'You pressed OK' : 'You pressed Cancel');
        $name.val('');
    });

    $('#hide-textbox').on('click', function () { Chaos.later(function () { $displayed.hide(); }, 300, 1500); });
    $('#show-textbox').on('click', function () { Chaos.later(function () { $displayed.show(); }, 300, 1500); });
});
