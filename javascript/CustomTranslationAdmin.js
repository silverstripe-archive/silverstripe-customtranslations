// Override the default delete behaviour. We don't want to delete the row client side, but rather refresh
// the RHS
TableListField.prototype.deleteRecord = function(e) {
		var img = Event.element(e);
		var link = Event.findElement(e,"a");
		var row = Event.findElement(e,"tr");

		// TODO ajaxErrorHandler and loading-image are dependent on cms, but formfield is in sapphire
		var confirmed = confirm(ss.i18n._t('TABLEFIELD.DELETECONFIRMMESSAGE', 'Are you sure you want to delete this record?'));
		if(confirmed)
		{
			img.setAttribute("src",'cms/images/network-save.gif'); // TODO doesn't work
			new Ajax.Request(
				link.getAttribute("href"),
				{
					method: 'post',
					postBody: 'forceajax=1' + ($('SecurityID') ? '&SecurityID=' + $('SecurityID').value : ''),
					onComplete: function(){
						$('Form_ResultsForm_CustomLanguageTranslation').refresh();
//						$('Form_ResultsForm_CustomLanguageTranslation').refresh();
					}.bind(this),
					onFailure: this.ajaxErrorHandler
				}
			);
		}
		Event.stop(e);
	}

/*
// This goes to the next page
	http://localhost/wf/admin/translations/CustomLanguageTranslation/ResultsForm/field/CustomLanguageTranslation?Locale=en&Entity=&Translation=%25&ResultAssembly%5BLocale%5D=Locale&ResultAssembly%5BTranslation%5D=Translation&ResultAssembly%5BEntity%5D=Entity&ctf[CustomLanguageTranslation][start]=30
*/