$(document).ready(function() {
	$('#ReportProject').blur(function() {
		partner_list();
	});
	partner_list();

	$('#ReportUseCompareDate').click(function() {
		toggle_compare_date();
	});
    	toggle_compare_date();
});

var toggle_compare_date = function() {
        if ($('#ReportUseCompareDate').is(':checked')) {
            $('#compare_date_wrapper').css('display', 'block');
        } else {
            $('#compare_date_wrapper').css('display', 'none');
        }
}
var partner_list = function() {
	var report_id = $('#ReportProject').val();
	if (report_id > 0) {
		$.ajax({
			type: "GET",
			url: "/reports/ajax_partners/" + report_id,
			statusCode: {
				200: function(data) {
					$('#ReportPartnerId').show();
					$('#ReportPartnerId').html(data.responseText);
				}
			}
		});
	}
};
