function toggleChecked(status) {
	$('tbody input[type="checkbox"]').each(function() {
		$(this).prop("checked", status);
	})
}

$(document).ready(function() {
	$('.tt').tooltip();	
	
	// change all .error-message to text-error
	$('.error-message').addClass('text-error');
	$('body').on('hidden', '.modal', function () {
	  	$(this).removeData('modal');
	});
	$('#ReportDateFrom').on('	', function(e) {
		var to_date =  e.date.getMonth() + 2 + '/0/' + e.date.getFullYear();
		$('#ReportDateTo').datepicker('update', to_date);
	});
	
	$( "#PartnerAddForm #SurveyPartnerPartnerId" ).change(function() {
		$.ajax({
			type: 'GET',
			url: '/partners/ajax_get_redirects/' + this.value,
			statusCode: {
				201: function(data) {
					$('#SurveyPartnerCompleteUrl').val(data.complete_url);
					$('#SurveyPartnerNqUrl').val(data.nq_url);
					$('#SurveyPartnerOqUrl').val(data.oq_url);
				}
			}
		});
		return false;
	});
	
});
 

var MintVine = {
	ToggleQualificationActive: function(node, qualification_id) {
		var $node = $(node);
		if ($node.attr('disabled') == 'disabled') {
			return false;
		}
		$.ajax({
			type: 'POST',
			url: '/qualifications/ajax_qualification_status/' + qualification_id,
			statusCode: {
				201: function(data) {
					if (data.status) {
						$('#qualifications_table .' + qualification_id + '_children').each(function() {
							$(this).find('.action a').removeAttr('disabled');
						});
						$node.addClass('btn-success').removeClass('btn-danger');
						$('span', $node).removeClass('icon-pause').addClass('icon-play');
					}
					else {
						if ($('#qualifications_table #qualification_' + qualification_id).hasClass('parent')) {
							$('#qualifications_table .' + qualification_id + '_children').each(function() {
								if ($(this).find('.action a').hasClass('btn-success')) {
									$(this).find('.action a').trigger('click');
									$(this).find('.action a').attr('disabled', 'disabled');
								}
								else {
									$(this).find('.action a').attr('disabled', 'disabled');
								}
							});
						}
						$node.addClass('btn-danger').removeClass('btn-success');
						$('span', $node).removeClass('icon-play').addClass('icon-pause');
					}
				}
			}
		});
		return false;
	},
	SkipProjectwithAnswer: function(answer_id, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_toggle_answer_status/' + answer_id + '/skip',
			statusCode: {
				201: function(data) {
					if (data.skip) {
						$node.addClass('btn-primary').removeClass('btn-default');
					}
					else {
						$node.removeClass('btn-primary').addClass('btn-default');
					}
				}
			}
		});
		return false;
	},
	IgnoreAnswer: function(answer_id, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_toggle_answer_status/' + answer_id + '/ignore',
			statusCode: {
				201: function(data) {
					if (data.ignore) {
						$node.addClass('btn-primary').removeClass('btn-default');
					}
					else {
						$node.removeClass('btn-primary').addClass('btn-default');
					}
				}
			}
		});
		return false;
	},
	HideAnswerFromPms: function(answer_id, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_toggle_answer_status/' + answer_id + '/hide',
			statusCode: {
				201: function(data) {
					if (data.hide) {
						$node.addClass('btn-primary').removeClass('btn-default');
					}
					else {
						$node.removeClass('btn-primary').addClass('btn-default');
					}
				}
			}
		});
		return false;
	},
	IsThisRemeshChatInterview: function(node) {
		$(node).change(function() {			
			if ($(node).is(':checked')) {
				$('#schedule_block').removeClass('hide');
				$('#schedule_block select').attr('disabled', false);
			}
			else {
				$('#schedule_block').addClass('hide');
				$('#schedule_block select').attr('disabled', true);
			}
		});		
		if ($(node).is(':checked')) {
			$('#schedule_block').removeClass('hide');
			$('#schedule_block select').attr('disabled', false);
		}
		else {
			$('#schedule_block').addClass('hide');
			$('#schedule_block select').attr('disabled', true);
		}
	},
	ClientSurveyOptions: function(node) {		
		$.ajax({
			type: 'GET',
			url: '/clients/ajax_key/' + $(node).val(),
			statusCode: {
				201: function(data) {
					$('.client_url_options').show();
					$('.options-client').hide();
					if (data.key != '') {
						$('.options-'+data.key).show();
						if (data.key == 'typeform') {
							if ($('#SurveyClientSurveyLink').val() == '') {
								$('.client_url_options').hide();
							}					
						}
					}
				}
			}
		});
		return false;
	},
	RemoveQuestion: function(node) {
		var $node = $(node);
		if ($('tr', $node.closest('tbody')).length == 1) {
			$('textarea, input', $node.closest('tr')).val('');
		}
		else {
			$node.closest('tr').remove();
		}
		return false;
	},
	AddQuestion: function(node) {
		var $table = $('#prescreeners');
		var $clone = $('tbody tr:last-child', $table).clone();
		$('tbody', $table).append($clone);
		$('input, textarea', $('tbody tr:last-child', $table)).val('');
		$('input', $('tbody tr:last-child', $table)).focus();
		return false;
	},
	SetReferrer: function(node) {
		$.ajax({
			type: 'POST',
			url: '/users/ajax_referrer/',
			data: $(node).serialize(),
			statusCode: {
				201: function(data) {
					if (data.message) {
						$('#referrer-success').text(data.message).show(); 
						$('#referrer-error').hide(); 
					}
				},
				400: function(data) {
					var message = eval("("+data.responseText+")");
					$('#referrer-success').hide(); 
					$('#referrer-error').text(message.message).show(); 
				}
			}
		});
		
		return false;
	},
	RemoveReferrer: function(node) {
		$.ajax({
			type: 'POST',
			url: '/users/ajax_remove_referrer/',
			data: $(node).serialize(),
			statusCode: {
				201: function(data) {
					if (data.message) {
						$('#referrer-success').text(data.message).show(); 
						$('#referrer-error').hide(); 
					}
				},
				400: function(data) {
					var message = eval("("+data.responseText+")");
					$('#referrer-success').hide(); 
					$('#referrer-error').text(message.message).show(); 
				}
			}
		});
		
		return false;
	},
	RebuildBalance: function(node) {
		$.ajax({
			type: 'POST',
			url: '/users/ajax_rebuild_balance/',
			data: $(node).serialize(),
			statusCode: {
				201: function(data) {
					if (data.message) {
						$('#referrer-success').text(data.message).show(); 
						$('#referrer-error').hide(); 
					}
				},
				400: function(data) {
					var message = eval("("+data.responseText+")");
					$('#referrer-success').hide(); 
					$('#referrer-error').text(message.message).show(); 
				}
			}
		});
		
		return false;
	},
	RetargetUsers: function(node) {
		$node = $(node);
		var $action = $node.closest('form').attr('action');
		$.ajax({
			type: 'POST',
			url: $action,
			data: $node.closest('form').serialize(),
			statusCode: {
				201: function(data) {
					if (data.message) {
						$('#modal-retarget-users div.alert.alert-error').show().html(data.message);
					}
					else {
						$('#modal-retarget-users').modal('hide');
					  	$('#modal-retarget-users').removeData('modal');
						window.location.reload(true);
					}
				}
			}
		});
	},
	QueryData: function(node) {
		var $val = $(node).val();
		if ($val == '' || $val == '0') {
			return false;
		}
		
		$('#waiting').show();
		$('input[type="submit"]', $(node).closest('form')).attr('disabled', true);
		$.ajax({
			type: 'GET',
			url: '/queries/ajax_data/' + $val,
			statusCode: {
				201: function(data) {
					if (data.killed) {
						$('#killed').show();
						$('input[type="submit"]', $(node).closest('form')).attr('disabled', false);
					}
					else {
						$('.query_additional').show();
						$('#total').val(data.total); 
						$('#reach').val(data.suggested); 
						$('#reach-text').text("Suggested " + data.suggested); 
						$('p.matched span').text(data.total); 
						$('p.matched').show();
						if (data.total == 0) {
							$('#zero').show();
						}
						else {
							$('#zero').hide();
						}
						$('#waiting').hide();
						$('input[type="submit"]', $(node).closest('form')).attr('disabled', false);
					}
				}
			}
		});
		return false;
	},
	RunQuery: function(node) {
		
	    var formObj = $(node);
	    var formData = new FormData(node);
		var milliseconds = new Date().getTime();
		
		$.ajax({
        	url: '/queries/ajax_preview/?timestamp='+milliseconds, // IE seems to ignore cache = false
    		type: 'POST',
        	data:  formData,
    		mimeType: "multipart/form-data",
    		contentType: false,
        	cache: false,
        	processData: false,
    		success: function(data, textStatus, jqXHR) {
				$('#query-save-form').html(data);
				$.scrollTo('#query-save-form', 800);
			},
			error: function(jqXHR, textStatus, errorThrown) {
			}          
		});
	
		return false;
	},
	ToggleDeduper: function(node, project_id) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_deduper/' + project_id,
			data: '',
			statusCode: {
				201: function(data) {
					if (!data.dedupe) {
						$node.addClass('btn-danger').removeClass('btn-success');
						$('span', $node).removeClass('icon-play').addClass('icon-pause');
					}
					else {
						$node.removeClass('btn-danger').addClass('btn-success');
						$('span', $node).addClass('icon-play').removeClass('icon-pause');
					}
				}
			}
		});
		return false;
	},
	ToggleTestMode: function(node, project_id) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_test_mode/' + project_id,
			data: '',
			statusCode: {
				201: function(data) {
					if (data.test_mode) {
						$node.addClass('btn-danger').removeClass('btn-default');
						$('span', $node).removeClass('icon-play').addClass('icon-pause');
					}
					else {
						$node.removeClass('btn-danger').addClass('btn-default');
						$('span', $node).addClass('icon-play').removeClass('icon-pause');
					}
				}
			}
		});
		return false;
	},
	PauseQuery: function(node, query_history_id) {	
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/queries/ajax_status/' + query_history_id,
			data: '',
			statusCode: {
				201: function(data) {
					$node.attr('class', 'btn '+ data.button);
					$('span', $node).attr('class', data.icon);
				}
			}
		});
		return false;
	},
	PauseQueryByQuery: function(node, query_id) {	
		$node = $(node);
		query_toggle = $node.attr('data-active');
		$.ajax({
			type: 'POST',
			url: '/queries/ajax_status_by_query/' + query_id + '/' + query_toggle,
			data: '',
			statusCode: {
				201: function(data) {
					$node.attr('class', 'btn btn-small '+ data.button);
					$node.attr('data-active', data.query_status);
					$('span', $node).attr('class', data.icon);
					if (data.query_id > 0) {
						$('table.query_history a[data-parent="'+data.query_id+'"]').each(function() {
							$(this).attr('class', 'btn btn-small '+ data.button);
							$(this).attr('data-active', data.query_status);
							$('span', $(this)).attr('class', data.icon);
						});
					}
				}
			}
		});
		return false;
	},
	
	PauseProject: function(node, project_id) {		
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_pause/' + project_id,
			data: '',
			statusCode: {
				201: function(data) {
					$node.attr('class', 'btn '+ data.button);
					$('span', $node).attr('class', data.icon);
				}
			}
		});
		return false;
	},
	
	SaveProjectStatus: function(node) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_status/',
			data: $node.closest('form').serialize(),
			statusCode: {
				201: function(data) {
					$('#modal-project').modal('hide');
				  	$('#modal-project').removeData('modal');
					$link = $(data.selector);
					if ($link.hasClass('underline')) {
						if (data.status != 'Open') {
							$link.removeClass('btn-success').addClass('btn-default');
							$link.closest('tr').addClass('closed');
						}
						else {
							$link.addClass('btn-success').removeClass('btn-default');
							$link.closest('tr').removeClass('closed');
						}
					}
					$link.text(data.text);
				}
			}
		});
		return false;
	},
	
	DeleteSurveyPartner: function(node, project_id, partner_id) {
		if (confirm('Are you SURE you want to remove this partner from this survey?')) {
			$node = $(node);
			$.ajax({
				type: 'POST',
				url: '/surveys/ajax_delete_partner/' + project_id + '/' + partner_id,
				data: '',
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut();
					}
				}
			});
		}
		return false;
	},
	
	DeleteAcquisitionAlert: function(alert_id, node) {
		if (confirm('Are you SURE you want to remove this alert?')) {
			$node = $(node);
			$.ajax({
				type: 'POST',
				url: '/acquisition_alerts/delete/' + alert_id,
				data: '',
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut();
					}
				}
			});
		}
		return false;
	},
	
	SaveSurveyPartner: function(node) {
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_save_partner/',
			data: $('#PartnerAddForm').serialize(),
			statusCode: {
				201: function(data) {
					location.reload();
				},
				400: function(data) {
					var message = eval("("+data.responseText+")");
					console.log(message);
					alert(message.errors);
				}
			}
		});
		return false;
	},	
	
	SavePushedStatus: function(node, status) {
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_save_pushed/' + status,
			data: $('#PushedStatusForm').serialize(),
			statusCode: {
				201: function(data) {
					location.reload();
				},
				400: function(data) {
					var message = eval("("+data.responseText+")");
					console.log(message);
					alert(message.errors);
				}
			}
		});
		return false;
	},
	
	ToggleSecurity: function(node, survey_partner_id) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_security_partners/'+ survey_partner_id,
			data: '',
			statusCode: {
				201: function(data) {
					if (data.status == 'paused') {
						$node.addClass('btn-danger').removeClass('btn-success');
						$('span', $node).removeClass('icon-play').addClass('icon-pause');
					}
					else {
						$node.removeClass('btn-danger').addClass('btn-success');
						$('span', $node).addClass('icon-play').removeClass('icon-pause');
					}
				}
			}
		});
		return false;
	},
	
	PausePartner: function(node, survey_partner_id) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_pause_partners/'+ survey_partner_id,
			data: '',
			statusCode: {
				201: function(data) {
					if (data.status == 'paused') {
						$node.addClass('btn-danger').removeClass('btn-success');
						$('span', $node).removeClass('icon-play').addClass('icon-pause');
					}
					else {
						$node.removeClass('btn-danger').addClass('btn-success');
						$('span', $node).addClass('icon-play').removeClass('icon-pause');
					}
				}
			}
		});
		return false;
	},
	
	HellBan: function(form) {
		var data = $(form).serialize(); 
		var user_id = $(form).data('user'); 
		var success = $('div.alert', $(form));
		$.ajax({
			type: 'POST',
			url: '/users/hellban/',
			data: data,
			statusCode: {
				201: function(data) {
					var div = $("div.btn-user[data-userid='" + user_id +"']");
					div.closest('tr').addClass('hellbanned');
					$('a.modal-user', div).addClass('hellbanned');
					success.show();
				}
			}
		});
		
		return false;
	},
	
	UnHellBan: function(form) {
		var data = $(form).serialize(); 
		var user_id = $(form).data('user'); 
		var success = $('div.alert', $(form));
		$.ajax({
			type: 'POST',
			url: '/users/unhellban/' + user_id,
			data: data,
			statusCode: {
				201: function(data) {
					var div = $("div.btn-user[data-userid='" + user_id +"']");
					div.closest('tr').removeClass('hellbanned');
					success.show();
				},
			}
		});
		return false;
	},
	
	DeleteUser: function(userid, node) {
		if (confirm('Are you SURE you want to delete this user? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/users/delete/' + userid,
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	DeleteFaqCategory: function(categoryid, node) {
		if (confirm('Are you SURE you want to delete this FAQ category? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/faq_categories/delete/',
				data: {id: categoryid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	DeleteFaq: function(faqid, node) {
		if (confirm('Are you SURE you want to delete this FAQ? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/faqs/delete/',
				data: {id: faqid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	ActiveOffer: function(offerid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/offers/active/',
			data: {id: offerid},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).removeClass('btn-default');
   						$(node).addClass('btn-success');;
   						$(node).html('Active');
					} else {
						$(node).removeClass('btn-success');
   						$(node).addClass('btn-default');
   						$(node).html('Inactive');
					}
				},
			}
		});
		return false;
	},
	
	DeleteOffer: function(offerid, node) {
		if (confirm('Are you SURE you want to delete this offer? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/offers/delete/',
				data: {id: offerid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	FeaturePoll: function(pollid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/polls/ajax_feature/',
			data: {id: pollid},
			statusCode: {
				201: function(data) {
					$('.btn-feature').removeClass('btn-success').addClass('btn-default');
					$node.addClass('btn-success').removeClass('btn-default');
   					$('#poll_'+pollid).addClass('btn-success').removeClass('btn-default').html('Active');
				},
			}
		});
		return false;
	},
	
	ActivatePoll: function(pollid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/polls/ajax_active/',
			data: {id: pollid},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).removeClass('btn-default');
   						$(node).addClass('btn-success');;
   						$(node).html('Active');
					} else {
						$(node).removeClass('btn-success');
   						$(node).addClass('btn-default');
   						$(node).html('Inactive');
					}
				},
			}
		});
		return false;
	},
	
	DeletePoll: function(pollid, node) {
		if (confirm('Are you SURE you want to delete this poll? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/polls/delete/',
				data: {id: pollid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	AddToDashboard: function(node, item_id, item_title, item_type) {
		var $node = $(node);
		var checked = ($(node).is(':checked')) ? 1 : 0;
		$.ajax({
			type: 'POST',
			url: '/dashboards/add/',
			data: {item_id: item_id, item_title: item_title, item_type: item_type, checked: checked},
			statusCode: {
				201: function(data) {
					$('#dashboard_sortable').load('/dashboards/lst/');
				},
			}
		});
		return true;
	},
	
	DeletePage: function(pageid, node) {
		if (confirm('Are you SURE you want to delete this page? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/pages/delete/',
				data: {id: pageid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	DeleteGift: function(giftid, node) {
		if (confirm('Are you SURE you want to delete this gift? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/gifts/delete/',
				data: {id: giftid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	ActiveAdmin: function(adminid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/admins/active/',
			data: {id: adminid},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).removeClass('btn-default');
   						$(node).addClass('btn-success');;
   						$(node).html('Active');
					} else {
						$(node).removeClass('btn-success');
   						$(node).addClass('btn-default');
   						$(node).html('Inactive');
					}
				},
			}
		});
		return false;
	},
	
	ActiveAnswerMapping: function(mappingid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/answer_mappings/active/',
			data: {id: mappingid},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).removeClass('btn-default');
   						$(node).addClass('btn-success');;
   						$(node).html('Active');
					} else {
						$(node).removeClass('btn-success');
   						$(node).addClass('btn-default');
   						$(node).html('Inactive');
					}
				},
			}
		});
		return false;
	},
	ActiveApiUser: function(id, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/api_users/active/',
			data: {id: id},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).removeClass('btn-default');
						$(node).addClass('btn-success');;
						$(node).html('Active');
					} else {
						$(node).removeClass('btn-success');
						$(node).addClass('btn-default');
						$(node).html('Inactive');
					}
				},
			}
		});
		return false;
	},
	LivemodeApiUser: function(id, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/api_users/ajax_toggle_mode/',
			data: {id: id},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).removeClass('btn-default');
						$(node).addClass('btn-success');;
						$(node).html('On');
					} else {
						$(node).removeClass('btn-success');
						$(node).addClass('btn-default');
						$(node).html('Off');
					}
				},
			}
		});
		return false;
	},
	DeleteAdmin: function(adminid, node) {
		if (confirm('Are you SURE you want to delete this Admin? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/admins/delete/',
				data: {id: adminid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	DeleteApiUser: function(id, node) {
		if (confirm('Are you SURE you want to delete this api user? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/api_users/delete/',
				data: {id: id},
				statusCode: {
					201: function (data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	DeleteRole: function(adminid, node) {
		if (confirm('Are you SURE you want to delete this permission group? This is IRREVERSIBLE.')) {
			$.ajax({
				type: 'POST',
				url: '/roles/delete/',
				data: {id: adminid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	DeleteContact: function(contactid, node) {
		if (confirm('Are you SURE you want to delete this contact? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/contacts/delete/',
				data: {id: contactid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	DeleteClient: function(clientid, node) {
		if (confirm('Are you SURE you want to delete this client? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/clients/delete/',
				data: {id: clientid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	
	DeletePartner: function(partnerid, node) {
		if (confirm('Are you SURE you want to delete this partner? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/partners/delete/',
				data: {id: partnerid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	DeleteSubQuota: function(qualification_id, node) {
		if (confirm('Are you sure you wish to delete this child qualification? This is IRREVERSIBLE')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/surveys/ajax_delete_subquota/',
				data: {id: qualification_id},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	DeleteClickDistribution: function(distribution_id, node) {
		if (confirm('Are you SURE you want to remove this distribution?')) {
			$.ajax({
				type: 'POST',
				url: '/surveys/ajax_delete_click_distribution/',
				data: {id: distribution_id},
				statusCode: {
					201: function (data) {
						for (var row_id in data) {
							if (data[row_id] == 0) {
								$('#click_distribution_table tbody #distribution_' + row_id).remove();
							}
							else {
								$('table tbody #distribution_' + row_id + ' td').eq(2).text(data[row_id].percentage);
								$('table tbody #distribution_' + row_id + ' td').eq(3).text(data[row_id].click_quota);
								$('table tbody #distribution_' + row_id + ' td').eq(4).text(data[row_id].clicks);
							}
						}
					}
				}
			});
		}
		return false;
	},
	SaveProfileQuestion: function(node) {
		var $node = $(node).closest('form'); 
		
		$.ajax({
			type: 'POST',
			url: '/profile_questions/ajax_add/',
			data: $node.serialize(),
			statusCode: {
				201: function(data) {
					$('#modal-add-profile-question').modal('hide');
				  	$('#modal-add-profile-question').removeData('modal');
					window.location.reload(true);
				},
				400: function() {
					alert('There was an error with saving this question. Please review all required fields.');
				}
			}
		});
		
		return false;
	},
	
	AddAnswer: function(node) {
		var $tr = $('tr.base', $(node).closest('div.answers'));
		var $inserted = $tr.clone().insertBefore($tr);
		$inserted.removeClass('base').show();
		$('input', $inserted).val('');
		return false;
	},
	
	RemoveExistingAnswer: function(node, answer_id) {
		var $node = $(node);
		$.ajax({
			async: false,
			type: 'POST',
			url: '/profile_questions/ajax_remove_answer/' + answer_id,
			statusCode: {
				201: function(data) {
					$node.closest('tr').fadeOut('fast');
				},
			}
		});
	},
	
	DeleteQuestion: function(question_id, node) {
		if (confirm('WARNING: you are about to delete this question.')) {
			var $node = $(node);
			$.ajax({
				async: false,
				type: 'POST',
				url: '/profile_questions/ajax_delete/' + question_id,
				data: $node.serialize(),
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut('fast');
					},
				}
			});
			return false;
		}
		return false;
	},
	
	RemoveAnswer: function(node) {
		$(node).closest('tr').remove();
		return false;
	},
	
	ToggleProfileStatus: function(node, profile_id) {
		var $node = $(node);
		$.ajax({
			async: false,
			type: 'POST',
			url: '/profiles/ajax_status/' + profile_id,
			statusCode: {
				201: function(data) {
					$node.removeClass('btn-default').removeClass('btn-success').addClass(data.class).text(data.text);
				},
			}
		});
		return false;
	},
	AddQuery: function(node) {
		var $question_ids = []; // this should be 
		var $answer_ids = [];

		var $node = $(node);
		$.ajax({
			async: false,
			type: 'POST',
			url: '/profile_questions/ajax_add_questions_to_query/',
			data: $('#query-profile-results input:checked').serialize(),
			statusCode: {
				200: function(data) {
					var html = $('<div/>').html(data.responseText);
					$('div.block', html).each(function() {
						var id_to_add = $(this).data('id');
						if ($('#query-profile-questions div.block[data-id="' + id_to_add + '"]').length == 0) {
							$('#query-profile-questions').append($(this).parent().html());
						}
						else {
							$('#query-profile-questions div.block[data-id="' + id_to_add + '"]').html($(this).html());
						}
					});
				},
			}
		});
		$('#modal-query-profile').modal('hide');
		return false;
	},
	ShowPartners: function(node, project_id) {
		if ($('.partner' + project_id).length > 0) {
			$('.partner' + project_id).remove();
		}
		else {
			$.ajax({
				type: 'POST',
				url: '/projects/ajax_show_partners/'+project_id,
				statusCode: {
					201: function(data) {
						$('.partner'+project_id).remove();
						$(node).closest("tr").after(data.partners)
					},
					400: function() {
						alert('Operation failed');
					}
				}
			});
		}

		return false;
	},
	ShowPublishers: function(node) {
		var $node = $(node);
		$.ajax({
			type: 'GET',
			async: false,
			url: '/users/ajax_show_publishers/' + $node.val(),
			success: function(data) {
				//alert(JSON.stringify(data.publishers));
				$("#UserPubid option").remove();
				$("#UserPubid").append('<option value="">Select</option>');
				$.each(data.publishers, function() {
					$("#UserPubid").append('<option value="' + this.User.pub_id + '">' + this.User.pub_id + '</option>')
				});
				$return = true;
			}
		}).fail(function(data) {
			$return = false;
		});

		return $return;
	},

	AutoCode: function(node) {
		var $node = $(node);
		$.ajax({
			url: '/codes/autocode/',
			statusCode: {
				201: function(data) {
					$node.val(data.code);
				},
			}
		});
		return false;
	},

	ActiveCode: function(codeid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/codes/active/',
			data: {id: codeid},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$node.removeClass('btn-default');
						$node.addClass('btn-success');;
						$node.html('Active');
					} else {
						$node.removeClass('btn-success');
						$node.addClass('btn-default');
						$node.html('Inactive');
					}
				},
			}
		});
		return false;
	},

	DeleteCode: function(codeid, node) {
		if (confirm('Are you SURE you want to delete this code? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/codes/delete/',
				data: {id: codeid},
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	ImportCard: function(i) {
		$.ajax({
			type: 'POST',
			url: '/tangocards/import_card/',
			data: $('#card_' + i).serialize(),
			success: function(data) {
				if (data.errors) {
					$('#modal_import_card_' + i + ' .alert').addClass('alert-danger').show().html(data.errors);
				}
				else {
					$('#modal_import_card_' + i + ' .alert').addClass('alert-success').show().html('Card imported successfully');
					setTimeout(function() {
						$('#modal_import_card_' + i).modal('hide');
					}, 2000);
				}
			}
		}).fail(function(data) {
			alert('Response failed, please try again!');
		});
		return false;
	},
	SaveUserTimezone: function (offset) {
		var data = {};
		data['data[offset]'] = offset;
		$.ajax({
			data: data,
			type: 'POST',
			async: false,
			url: '/users/ajax_save_timezone',
			success: function (data) {
				$return = true;
			}
		}).fail(function (data) {
			$return = false;
		});
		
		return $return;
	},
	CheckMassHellban: function() {
		var checked = false;
		$.each($('input[type="checkbox"]'), function() {
			if ($(this).prop('checked') == true) {
				checked = true;
			}
		});
		if (checked) {
			return true;
		}
		alert('Please select at-least one user to perform this action.');
		return false;
	},
	PopulateNQAward: function(update) {
		$('#ProjectAward').blur(function() {
			if (update || $.trim($('#ProjectNqAward').val()) === '') {
				user_payout = $(this).val();		
				nq_award = (user_payout/100) * 5;
				if (nq_award > 5) {
					$('#ProjectNqAward').val(5)
				}
				else {
					$('#ProjectNqAward').val(Math.round(nq_award))
				}
			}
			
		})
	},
	CheckPhoneBeforeMerge: function() {
		$return = false;
		$.ajax({
			data: $('#UserMergeForm').serialize(),
			type: 'POST',
			async: false,
			url: '/users/check_phone_numbers',
			success: function (data) {
				if (data.status == true) {
					$return = true;
				}
				else {
					$('#SelectPhoneNumber').html(data);
					$('#MergeButton').removeAttr('onclick');
					$return = false;
				}
			}
		}).fail(function (data) {
			$return = false;
		});
		
		return $return;
	},
	DeleteAdvertisingSpend: function(advertising_spend_id, node) {
		if (confirm('Are you SURE you want to delete this advertising spend? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/advertising_spends/delete/',
				data: {id: advertising_spend_id},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	TangocardValue: function(node) {
		var $val = $(node).val();
		if ($val == '' || $val == '0') {
			return false;
		}
		
		$('#waiting').show();
		$('input[type="submit"]', $(node).closest('form')).attr('disabled', true);
		$('#TransactionAmount', $(node).closest('form')).attr('readonly', true);
		$.ajax({
			type: 'GET',
			url: '/tangocards/ajax_amount/' + $val,
			statusCode: {
				201: function(data) {
					if (data.amount > 0) {
						$('#TransactionAmount').val(data.amount);
					}
					else {
						$('#TransactionAmount').val('');
						$('#TransactionAmount', $(node).closest('form')).attr('readonly', false);
					}
					
					$('#waiting').hide();
					$('input[type="submit"]', $(node).closest('form')).attr('disabled', false);
				}
			}
		});
		return false;
	},
	DeleteDailyAnalysisProperty: function(propertyid, node) {
		if (confirm('Are you SURE you want to delete this Daily Analysis Property? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/daily_analysis_properties/delete/',
				data: {id: propertyid},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	DeleteDailyAnalysis: function(type, date, node) {
		if (confirm('Are you SURE you want to delete Daily Analysis Report for date '+date+'? This is IRREVERSIBLE.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/daily_analysis/ajax_delete/',
				data: {type: type, date: date},
				statusCode: {
					201: function(data) {
						$(node).closest('tr').fadeOut('fast');
					},
				}
			});
		}
		return false;
	},
	ApproveHistoryRequest: function(node) {
		$node = $(node);
		if ($node.hasClass('disabled')) {
			return;
		}
		$node.addClass('disabled');
		$.ajax({
			type: 'POST',
			url: '/history_requests/ajax_approve/',
			data: $node.closest('form').serialize(),
			statusCode: {
				201: function(data) {
					if (data.error) {
						$('#modal-approve-history_request .alert').addClass('alert-danger').show().html(data.error);
						$node.removeClass('disabled');
					}
					else {
						$request_id = $('#approve-request_id').val();
						$report_type = data.report_type;
						if (data.next_history_url) {
							window.location = data.next_history_url;
						}
						else if (data.submit_update_row) {
							// update the row in the list
							MintVine.UpdateHistoryRequestRow('#approve-history-' + $request_id, $request_id, $report_type);
							$('#modal-approve-history_request').modal('hide');
							$('#modal-approve-history_request').removeData('modal');
							$node.removeClass('disabled');
						}
						else {
							$('#modal-approve-history_request').modal('hide');
							$('#modal-approve-history_request').removeData('modal');
							$('#approve-history-' + $request_id).closest('tr').fadeOut();
							$node.removeClass('disabled');
						}
					}
				}
			}
		});
		return false;
	},
	RejectHistoryRequest: function(node) {
		$node = $(node);
		if ($node.hasClass('disabled')) {
			return;
		}
		$node.addClass('disabled');
		$.ajax({
			type: 'POST',
			url: '/history_requests/ajax_reject/',
			data: $node.closest('form').serialize(),
			statusCode: {
				201: function(data) {
					$request_id = $('#reject-request_id').val();
					$('#modal-reject-history_request').modal('hide');
				  	$('#modal-reject-history_request').removeData('modal');
					
					if (data.next_history_url) {
						window.location = data.next_history_url;
					}
					else if (data.submit_update_row) {
						// update the row in the list
						$report_type = data.report_type;
						MintVine.UpdateHistoryRequestRow('#reject-history-' + $request_id, $request_id, $report_type);
						$('#modal-reject-history_request').modal('hide');
						$('#modal-reject-history_request').removeData('modal');
						$node.removeClass('disabled');
					}
					else {
						window.location.reload(true);
					}
				}
			}
		});
		return false;
	},
	
	IgnoreQuestion: function(questionid, node) {
		var $node = $(node);
		var is_confirmed = true;
		if ($node.attr('data-ignore') == '0') {
			var is_confirmed = confirm('WARNING: By marking this question as ignore, you are hiding it from capture from panelists as well as hiding it from QEV.')
		}
		if (is_confirmed) {
			$.ajax({
				type: 'POST',
				url: '/questions/ignore/',
				data: {id: questionid},
				statusCode: {
					201: function (data) {
						if (data.status == 1) {
							$node.removeClass('btn-default');
							$node.addClass('btn-primary');
							$node.attr('data-ignore', data.status);
						} else {
							$node.removeClass('btn-primary');
							$node.addClass('btn-default');
							$node.attr('data-ignore', data.status);
						}
					},
				}
			});
		}	
		return false;
	},
	
	DeprecateQuestion: function(questionid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_deprecate/',
			data: {id: questionid},
			statusCode: {
				201: function (data) {
					if (data.status == 1) {
						$node.removeClass('btn-default');
						$node.addClass('btn-primary');
						$node.attr('data-deprecate', data.status);
					} else {
						$node.removeClass('btn-primary');
						$node.addClass('btn-default');
						$node.attr('data-deprecate', data.status);
					}
				},
			}
		});
	},
	
	StageQuestion: function(questionid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_staging/',
			data: {id: questionid},
			statusCode: {
				201: function (data) {
					if (data.status == 1) {
						$node.removeClass('btn-default');
						$node.addClass('btn-primary');
						$node.attr('data-staging', data.status);
					} else {
						$node.removeClass('btn-primary');
						$node.addClass('btn-default');
						$node.attr('data-staging', data.status);
					}
				},
			}
		});
	},
	
	CoreQuestion: function(questionid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_core/',
			data: {id: questionid},
			statusCode: {
				201: function (data) {
					if (data.status == 1) {
						$node.removeClass('btn-default');
						$node.addClass('btn-primary');
						$node.attr('data-core', data.status);
					} else {
						$node.removeClass('btn-primary');
						$node.addClass('btn-default');
						$node.attr('data-core', data.status);
					}
				},
			}
		});
	},
	
	LockQuestion: function(questionid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/ajax_lock/',
			data: {id: questionid},
			statusCode: {
				201: function (data) {
					if (data.status == 1) {
						$node.removeClass('btn-default');
						$node.addClass('btn-primary');
						$node.attr('data-locked', data.status);
					} else {
						$node.removeClass('btn-primary');
						$node.addClass('btn-default');
						$node.attr('data-locked', data.status);
					}
				},
			}
		});
	},

	SetHighUsageQuestion: function(questionid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/questions/set_high_usage/',
			data: {id: questionid},
			statusCode: {
				201: function (data) {
					if (data.status >= 1) {
						$node.removeClass('btn-default');
						$node.addClass('btn-primary');
						$node.html('Show in QEV');
					} else {
						$node.removeClass('btn-primary');
						$node.addClass('btn-default');
						$node.html('Show in QEV');
					}
				},
			}
		});
		return false;
	},

	LockNotificationProfile: function(notification_shedule_id, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/notification_schedules/ajax_lock_profile/',
			data: {notification_shedule_id: notification_shedule_id},
			statusCode: {
				201: function(data) {
					if (data.status) {
						$node.addClass('btn-danger').removeClass('btn-default');
						$node.text('Unlock Profile');
					}
					else {
						$node.removeClass('btn-danger').addClass('btn-default');
						$node.text('Lock Profile');
					}
				}
			}
		});
		return false;
	},

	HideClient: function(clientid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/clients/hide/',
			data: {id: clientid},
			statusCode: {
				201: function(data) {
					if (data.hide_status == 1) {
						$node.removeClass('btn-warning');
						$node.addClass('btn-danger');
						$node.html('Show In Reports');
					} 
					else {
						$node.removeClass('btn-danger');
						$node.addClass('btn-warning');
						$node.html('Hide From Reports');
					}
				}
			}
		});
		return false;
	},
	DownloadReportedAttachment: function(node) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/history_requests/download/',
			data: $node.closest('form').serialize(),
			statusCode: {
				201: function(data) {
					window.location.href = data.screenshot_url;
				}
			}
		});
		return false;
	},
	UpdateHistoryRequestRow: function(node, request_id, report_type) {
		$.ajax({
			type: 'POST',
			url: '/history_requests/ajax_update_request_row/' + request_id + '/' + report_type,
			statusCode: {
				201: function(data) {
					$(node).closest('tr').html(data);
				}
			}
		});
		return false;
	},
	ChangeProject: function(node) {
		$.ajax({
			type: 'POST',
			url: '/history_requests/ajax_change_project/',
			data: $(node).serialize(),
			statusCode: {
				201: function(data) {
					if (data.message) {
						$('#change-success').text(data.message).show(); 
						$('#change-error').hide(); 
						window.location.href = '/history_requests/info/' + data.history_request_id;
					}
				},
				400: function(data) {
					var message = eval("("+data.responseText+")");
					$('#change-success').hide(); 
					$('#change-error').text(message.message).show(); 
				}
			}
		});
		
		return false;
	},
	validateProjectSave: function () {
		var client_rate = $('#ProjectClientRate').val();
		var award = $('#ProjectAward').val();
		var message = false;
		
		client_rate = client_rate * 100; // convert client rate to cents
		client_rate = (client_rate * 80) / 100; // 80% 
		if (award >= client_rate) {
			message = "The user payout exceeds 80% of the client rate; please confirm this value is correct.";
		}
		else if (award >= 500) {
			message = 'The user payout exceeds 500 points; please confirm this value is correct.';
		}

		if (message) {
			var status = confirm(message);
			if (status == false) {
				return false;
			}
			else {
				return true;
			}
		}
	},
	ToggleSoftLaunch: function(node, project_id) {
		$node = $(node);
		$.ajax({
			type: 'POST',
			url: '/surveys/ajax_soft_launch/' + project_id,
			data: '',
			statusCode: {
				201: function(data) {
					if (data.soft_launch) {
						$node.addClass('btn-success').removeClass('btn-default');
					}
					else {
						$node.removeClass('btn-success').addClass('btn-default');
					}
				}
			}
		});
		return false;
	},

	TangocardAmount: function(node) {
		var $val = $(node).val();
		if ($val == '' || $val == '0') {
			return false;
		}
		
		$('#waiting').show();
		$('input[type="submit"]', $(node).closest('form')).attr('disabled', true);
		$('#WithdrawalAmount', $(node).closest('form')).attr('readonly', true);
		$.ajax({
			type: 'GET',
			url: '/tangocards/ajax_amount/' + $val,
			statusCode: {
				201: function(data) {
					if (data.amount > 0) {
						$('#WithdrawalAmount').val(data.amount);
					}
					else {
						$('#WithdrawalAmount').val('');
						$('#WithdrawalAmount', $(node).closest('form')).attr('readonly', false);
					}
					
					$('#waiting').hide();
					$('input[type="submit"]', $(node).closest('form')).attr('disabled', false);
				}
			}
		});
		return false;
	},
	AuthyRegister: function(adminid, node) {
		var $node = $(node);
		$.ajax({
			type: 'POST',
			url: '/admins/authy_register/',
			data: {id: adminid},
			statusCode: {
				201: function(data) {
					if (data.status == 1) {
						$(node).parent('p').html('<span class="label label-success">Registered</span>');
					}
					else {
						window.location = data.redirect;
					}
				},
			}
		});
		return false;
	},
	
	ShowAnswerMappingRow: function(node) {
		var $new = $('div.new', $(node).closest('div.row-fluid'));
		$new.css('display', 'block');
		$new.before($new.clone().removeClass('new'));
		$new.hide();
		return false;
	},
};

var MintVineInvoice = {
	ShowInvoiceRow: function(node) {
		var $new = $('tr.new', $(node).closest('table'));
		$new.css('display', 'table-row');
		$new.before($new.clone().removeClass('new'));
		$new.hide();
		return false;
	},
	ChangeInvoiceRow: function(node) {
		var $tr = $(node).closest('tr');
		var quantity = $('.quantity', $tr).val();
		var unit_price = $('.unit_price', $tr).val();
		var val = quantity * unit_price;
		$('.line_total', $tr).attr('data-value', val).val(val.toFixed(2));
		MintVineInvoice.CalculateTotal();
	},
	CalculateTotal: function(node) {
		var total = $('#InvoiceSubtotal').val();
		var new_total = 0;

		$('.line_total').each(function() {
			//new_total = parseInt($(this).attr('data-value')) + new_total;
			new_total = parseFloat($(this).attr('data-value')) + new_total;
		});
		$('#InvoiceSubtotal').attr('data-value', new_total).val(new_total.toFixed(2));
	}
}

$(document).ready(function() {
// used for delay search on profile questions for queries	
	$.widget("ui.onDelayedKeyup", {
	    _init : function() {
	        var self = this;
	        $(this.element).keyup(function() {
	            if(typeof(window['inputTimeout']) != "undefined"){
	                window.clearTimeout(inputTimeout);
	            }  
	            var handler = self.options.handler;
	            window['inputTimeout'] = window.setTimeout(function() {
	                handler.call(self.element) }, self.options.delay);
	        });
	    },
	    options: {
	        handler: $.noop(),
	        delay: 500
	    }

	});
});
