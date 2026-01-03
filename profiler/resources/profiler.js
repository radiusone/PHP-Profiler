let profiler_details = false;
let height_toggle = false;
let selected_log_type = null;

function hideAllTabs() {
    document.getElementById('profiler')
        .classList
        .remove('console', 'speed', 'queries', 'memory', 'files');
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.getElementById('profiler-container').style.display = 'block';
    }, 10);

    document.querySelectorAll('.query-profile h4').forEach(function (el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () {
            let table = this.parent.querySelector('table');
            if (table && table.style.display === 'none') {
                this.innerHTML = '&#187; Hide Query Profile';
                table.style.display = 'block';
            } else {
                this.innerHTML = '&#187; Show Query Profile';
                table.style.display = 'none';
            }
        });
    });

    document.querySelectorAll('.detailsToggle').forEach(function (el) {
        el.addEventListener('click', function () {
            if (profiler_details) {
                document.getElementById('profiler-container').classList.add('hideDetails');
                profiler_details = false;
            } else {
                document.getElementById('profiler-container').classList.remove('hideDetails');
                profiler_details = true;
            }

            return false;
        });
    });

    document.querySelectorAll('.heightToggle').forEach(function (el) {
        el.addEventListener('click', function () {
            height_toggle = !height_toggle;

            document.querySelectorAll('.profiler-box').forEach(function (el) {
                el.style.height = (height_toggle ? '500px' : '200px');
            });
        });
    });

    document.querySelectorAll('.tab').forEach(function(el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () {
            hideAllTabs();

            this.classList.add('active');
            document.getElementById('profiler').classList.add(this.id);

            if (!profiler_details) {
                profiler_details = true;
                document.getElementById('profiler-container').classList.remove('hideDetails');
            }
        });
    });

    document.querySelectorAll('#profiler-console .side td').forEach(function(el) {
        var log_type = el.id.split('-')[1];
        var log_count = el.querySelector('var')?.innerHTML;

        if (log_count == 0) {
            return;
        }

        el.style.cursor = 'pointer';
        el.addEventListener('click', function() {
            document.querySelectorAll('#profiler-console .main tr').forEach(function(el) {
                const row_type = el.getAttribute("class").split('-')[1];

                if (row_type === log_type || selected_log_type === log_type) {
                    el.style.display = 'table-row';
                } else {
                    el.style.display = 'none';
                }
            });

            document.querySelectorAll('#profiler-console .side td').forEach(function (el) {
                el.classList.remove('selected');
            });

            if (selected_log_type === log_type) {
                selected_log_type = null;
            } else {
                selected_log_type = log_type;
                el.classList.add('selected');
            }
        });
    });
});
