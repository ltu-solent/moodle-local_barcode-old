define(['jquery'], function($) {
    // Initial variables.
    var cmid,
        code,
        message    = '',
        assignment = '-',
        assignmentdescription = '',
        course,
        duedate,
        idformat,
        participantid,
        studentid,
        studentname,
        submissiontime,
        submitted = 'Not Submitted',
        islate;

    /**
     * When the page loads then focus on the barcode input and listen for keypresses.
     * Additionally, create the barcode table and set the web token
     * @return {void}
     */
    function load() {
        $('#id_barcode').focus();
        document.getElementById('id_barcode').addEventListener('keypress', preventSubmission, false);
        createTable();
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

        // Make the ajax call to the webservice.
        if (barcode) {
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
        $('#id_barcode').value = '';
    }

    /**
     * Prevent the form from submmitting while the user is hitting enter during the
     * process of entering more than one barcode
     *
     * @param  {object} ev      the keypress event
     * @return {boolean}
     */
    function preventSubmission(ev) {
        var key = ev.which || ev.keyCode;
        if (key === 13) {
            ev.stopPropagation();
            ev.preventDefault();
            submitBarcode(ev);
        }
        return false;
    }

    /**
     * Save the barcode to the database
     * @param  {string} wstoken  The auth web token
     * @param  {string} barcode  The barcode
     * @return {void}
     */
    function saveBarcode(barcode) {
        $.ajax({
            type: "POST",
            url: 'service/upload.php?barcode=' + barcode + '&id=' + cmid,
            data: {
                barcode: barcode
            },
            success: function(response) {
                code           = response.data.code;
                message        = response.data.message;
                assignment     = response.data.assignment;
                assignmentdescription = response.data.assignmentdescription;
                course         = response.data.course;
                duedate        = response.data.duedate;
                idformat       = response.data.idformat;
                participantid  = response.data.participantid;
                studentid      = response.data.studentid;
                studentname    = response.data.studentname;
                submissiontime = response.data.submissiontime;
                islate         = response.data.islate;
                feedback();
            },
            error: function(response) {
                code           = response.data.code;
                message        = response.data.message;
                assignment     = response.data.assignment;
                assignmentdescription = response.data.assignmentdescription;
                course         = response.data.course;
                duedate        = response.data.duedate;
                idformat       = response.data.idformat;
                participantid  = response.data.participantid;
                studentid      = response.data.studentid;
                studentname    = response.data.studentname;
                submissiontime = response.data.submissiontime;
                islate         = response.data.islate;
                feedback();
            },
            dataType: "json"
        });
    }

    /**
     * Display the feedback message to the user
     * @return {void}
     */
    function feedback() {
        var feedback       = $('#feedback');
        feedback.html(message);

        if (code === 200) {
            outputSubmittedCount();
            submitted = 'Submitted';
            $('#feedback-group').removeClass('bc-has-danger');
            $('#feedback-group').addClass('bc-has-success');
            addTableRow('success');
            resetBarcode();
        }

        if (code === 404) {
            assignment = '-';
        }

        if (code !== 200) {
            submitted  = 'Not Submitted';
            $('#feedback-group').removeClass('bc-has-success');
            $('#feedback-group').addClass('bc-has-danger');
            addTableRow('fail');
        }
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
        header.html('<th colspan="8">Barcodes - (<span id="id_count">' +
                '0</span> Scanned)</th>' +
                '<th colspan="17">Assignment Details</th>' +
                '<th colspan="5">Submitted (<span id="submit_count">0</span>)</th>');
        table.append('<tbody id="tbody"></tbody>');

        main.append(table);
    }

    /**
     * Add a new row to the barcodes table
     * @param {string} css  The css class condition
     */
    function addTableRow(css) {
        var cssClass = 'bc-fail';

        if (css === 'success') {
            cssClass = 'bc-success';
        }

        var colspans = [8, 17, 5];
        var arr = getData();
        var tbody = $('#tbody');
        var row = $('<tr></tr>');

        for (var i = 0; i <= 2; i++) {
            var cell = $('<td></td>');
            var span = $('<span></span>');
            // var content = document.createTextNode(arr[i]);

            cell.attr('colspan', colspans[i]);
            span.html(arr[i]);//append(content);
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
            '<small>Student: ' + studentname + ' / ' + idformat + ': ' + studentid + '</small><br />' +
            '<small>Due: ' + duedate + '&nbsp; Scanned: <span class="' +
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
     * On initialisation set the token and call the load function
     */
    return {
        init: function(id) {
            load();
            cmid = id;
        },
    };
});
