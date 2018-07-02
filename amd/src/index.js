define(['jquery', 'core/str'], function($, str) {
    // Initial variables.
    var cmid,
        link,
        code,
        message    = '',
        assignment = '-',
        assignmentdescription = '',
        course,
        duedate,
        idformat,
        studentid,
        studentname,
        submissiontime,
        submitted = 'Not Submitted',
        islate,
        revert = '0',
        ontime = '0',
        hasReverted = 0,
        strings = [];

    /**
     * When the page loads then focus on the barcode input and listen for keypresses.
     * Additionally, create the barcode table and set the web token
     * @return {void}
     */
    function load() {
        $('#id_barcode').focus();
        document.getElementById('id_barcode').addEventListener('keypress', preventOnEnterSubmission, false);
        document.getElementById('id_submitbutton').addEventListener('click', preventSubmission, false);

        var langStrings = str.get_strings([
            {key: 'assignmentdetails', component: 'local_barcode'},
            {key: 'barcodes', component: 'local_barcode'},
            {key: 'draft', component: 'local_barcode'},
            {key: 'draftandsubmissionerror', component: 'local_barcode'},
            {key: 'due', component: 'local_barcode'},
            {key: 'notsubmitted', component: 'local_barcode'},
            {key: 'scanned', component: 'local_barcode'},
            {key: 'student', component: 'local_barcode'},
            {key: 'submitted', component: 'local_barcode'}
        ]);

        $.when(langStrings).done(function(localizedStrings) {
            strings = localizedStrings;
            createTable();
        });
    }

    /**
     * Submit the barcode and prevent the form from submitting to the server
     * @param  {event} ev   The standard submit event
     * @return {boolean}    Return false
     */
    function submitBarcode(ev) {
        ev.stopPropagation();
        ev.preventDefault();

        var barcode = getBarcode();

        if (barcode && !formIsValid()) {
            $('#feedback').html(strings[3]);
            $('#feedback-group').addClass('bc-has-inform');
            $('#feedback-group').removeClass('bc-has-danger');
            $('#feedback-group').removeClass('bc-has-success');
        }
        // Make the ajax call to the webservice.
        if (barcode && formIsValid()) {
            saveBarcode(barcode);
        }
        $('#id_barcode').focus();
        return false;
    }

    /**
     * Get the barcode entered into the text field
     *
     * @return {string}     the new entered barcode
     */
    function getBarcode() {
        return document.getElementById('id_barcode').value.trim();
    }

    /**
     * Reset the input text field to an empty value for the next entry
     */
    function resetBarcode() {
        $('#id_barcode').val('');
    }

    /**
     * Prevent the form from submmitting while the user is hitting enter during the
     * process of entering more than one barcode
     *
     * @param  {object} ev      the keypress event
     * @return {boolean}
     */
    function preventOnEnterSubmission(ev) {
        var key = ev.which || ev.keyCode;
        if (key === 13) {
            ev.stopPropagation();
            ev.preventDefault();
            setRevert();
            setOnTime();
            submitBarcode(ev);
        }
        return false;
    }

    /**
     * Prevent the form from submmitting while the user is submitting the form by interacting
     * with the submit button
     *
     * @param  {object} ev   The submit event
     * @return {boolean}
     */
    function preventSubmission(ev) {
        ev.stopPropagation();
        ev.preventDefault();
        setRevert();
        setOnTime();
        submitBarcode(ev);
        return false;
    }

    /**
     * Prevent the user submitting the form if both revert to draft and allow late submission
     * has been selected, you can't have both
     * @return {boolean} Whether or not the form is valid
     */
    function formIsValid() {
        if (revert !== '0' && ontime !== '0') {
            return false;
        }
        return true;
    }

    /**
     * Save the barcode to the database
     * @param  {string} wstoken  The auth web token
     * @param  {string} barcode  The barcode
     * @return {void}
     */
    function saveBarcode(barcode) {
        var uploadUrl = 'service/upload.php?barcode=';
        if (link) {
            uploadUrl = '../service/upload.php?barcode=';
        }
        $.ajax({
            type: "POST",
            url: uploadUrl + barcode + '&id=' + cmid + '&revert=' + revert + '&ontime=' + ontime,
            data: {
                barcode: barcode
            },
            success: function(response) {
                if (typeof response.faultCode !== 'undefined') {
                    code = response.faultString;
                    message = response.faultString;
                } else {
                    code           = response.data.code;
                    message        = response.data.message;
                    assignment     = response.data.assignment;
                    assignmentdescription = response.data.assignmentdescription;
                    course         = response.data.course;
                    duedate        = response.data.duedate;
                    idformat       = response.data.idformat;
                    studentid      = response.data.studentid;
                    studentname    = response.data.studentname;
                    submissiontime = response.data.submissiontime;
                    islate         = response.data.islate;
                    hasReverted    = response.data.reverted;

                    if (! getAllowMultipleScans()) {
                        resetRevert();
                        resetOnTime();
                    }
                }
                feedback();
            },
            error: function(response) {

            },
            dataType: "json"
        });
    }

    /**
     * Display the feedback message to the user
     * @return {void}
     */
    function feedback() {
        var feedback = $('#feedback');
        feedback.html(message);

        if (code === 200) {
            outputSubmittedCount();
            if (hasReverted) {
                submitted = strings[2];
                $('#feedback-group').addClass('bc-has-success');
                $('#feedback-group').removeClass('bc-has-danger');
                $('#feedback-group').removeClass('bc-has-inform');
                addTableRow('revert');
            } else {
                submitted = strings[8];
                $('#feedback-group').removeClass('bc-has-danger');
                $('#feedback-group').removeClass('bc-has-inform');
                $('#feedback-group').addClass('bc-has-success');
                addTableRow('success');
            }
        }

        if (code === 404) {
            assignment = '-';
        }

        if (code !== 200) {
            submitted  = strings[6];
            $('#feedback-group').removeClass('bc-has-success');
            $('#feedback-group').removeClass('bc-has-inform');
            $('#feedback-group').addClass('bc-has-danger');
            addTableRow('fail');
        }
        resetBarcode();
        outputBarcodeCount();
    }

    /**
     * Create the barcode submissions table
     */
    function createTable() {
        var main = $('#region-main');
        var table = $('<table></table>');
        table.attr('id', 'barcode-table');
        table.addClass('generaltable');
        table.addClass('barcode-table');

        var thead = table.append('<thead></thead>');
        var header = thead.append('<tr></tr>');
        header.html('<th colspan="8">' + strings[1] + ' - (<span id="id_count">' +
                '0</span> ' + strings[6] + ')</th>' +
                '<th colspan="17">' + strings[0] + '</th>' +
                '<th colspan="5">' + strings[8] + '(<span id="submit_count">0</span>)</th>');
        table.append('<tbody id="tbody"></tbody>');

        main.append(table);
    }

    /**
     * Add a new row to the barcodes table
     * @param {string} css  The css class condition
     */
    function addTableRow(css) {
        var cssClass = 'bc-' + css;
        var colspans = [8, 17, 5];
        var arr = getData();
        var tbody = $('#tbody');
        var row = $('<tr></tr>');

        for (var i = 0; i <= 2; i++) {
            var cell = $('<td></td>');
            var span = $('<span></span>');

            cell.attr('colspan', colspans[i]);
            span.html(arr[i]);
            cell.append(span);

            if (i === 2)  {
                span.addClass('visuallyhidden');
                cell.addClass(cssClass);
            }
            row.append(cell);
        }
        tbody.prepend(row);
    }

    /**
     * Get the data for displaying in the table
     * @return {array} The data in an array
     */
    function getData() {
        var submissionClass = 'bc-ontime';
        if (islate) {
            submissionClass = 'bc-islate';
        }
        return [
            getBarcode(),
            assignment + '<br />' +
            '<small>' + assignmentdescription.substring(0, 30) + '</small><br />' +
            '<small>' + course + '</small><br />' +
            '<small>' + strings[7] + ': ' + studentname + ' / ' + idformat + ': ' + studentid + '</small><br />' +
            '<small>' + strings[4] + ': ' + duedate + '&nbsp; ' + strings[6] + ': <span class="' +
                submissionClass + '">' + submissiontime +
            '</span></small><br />',
            submitted
        ];
    }

    /**
     * Display the total number of scanned barcodes in the barcode table
     * @return {void}
     */
    function outputBarcodeCount() {
        $('#id_count').html(totalScanned());
        return false;
    }

    /**
     * Display the number of successfully submitted barcodes
     * @return {void}
     */
    function outputSubmittedCount() {
        $('#submit_count').html(submittedBarcodes());
        return false;
    }

    /**
     * Checks whether or not the submission is to revert to draft or not
     * @return {[type]} [description]
     */
    function setRevert() {
        if (document.getElementById('id_reverttodraft').checked === true) {
            revert = '1';
        } else {
            revert = '0';
        }
    }

    /**
     * Reset the revert to draft checkbox
     * @return {void}
     */
    function resetRevert() {
        document.getElementById('id_reverttodraft').checked = false;
    }

    function setOnTime() {
        if (document.getElementById('id_submitontime') &&
                document.getElementById('id_submitontime').checked === true) {
            ontime = '1';
        } else {
            ontime = '0';
        }
    }

    /**
     * Reset the allow late submission checkbox
     * @return {void}
     */
    function resetOnTime() {
        document.getElementById('id_submitontime').checked = false;
    }

    /**
     * Enable multiple scans at the same time
     * @return boolean
     */
    function getAllowMultipleScans() {
        return document.getElementById('id_multiplescans').checked;
    }

    // Closure to calculate the total number of scanned barcodes
    // @return {counter}   Returns the number of times all barcodes have been scanned.
    var totalScanned = (function () {
        var counter = 0;
        // Increment the counter.
        function increment() {
            counter += 1;
        }
        // Return the updated counter value.
        return function () {
            increment();
            return counter;
        };
    })();

    // Calculate the number of submitted barcodes
    // Return the number of times a barcode has been submitted.
    // A submitted barcode will only be submitted if it's a valid barcode.
    // @return {counter}.
    var submittedBarcodes = (function() {
        var counter = 0;
        // Increment the counter.
        function increment() {
            counter += 1;
        }
        // Return the updated counter value.
        return function () {
            increment();
            return counter;
        };
    })();

    /**
     * On initialisation call the load function and set the course module id and the type of url to use on ajax call.
     */
    return {
        init: function(id, direct) {
            load();
            cmid = id;
            link = direct;
        },
    };
});
