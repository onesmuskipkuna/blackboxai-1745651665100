document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery != 'undefined' && typeof jQuery.fn.select2 != 'undefined') {
        jQuery('#student_id').select2({
            placeholder: 'Search by Name, Admission No. or Phone',
            allowClear: true,
            width: '100%',
            minimumInputLength: 1,
            ajax: {
                url: 'search_students.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term // search term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.results
                    };
                },
                cache: true
            }
        });
    } else {
        console.error('Select2 or jQuery is not loaded properly');
    }
});
